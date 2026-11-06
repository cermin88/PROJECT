<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\CsaEvent;
use App\Models\CsaEventInvitation;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        CsaEvent::syncApprovals();
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $base = CsaEvent::with('invitations')->whereNotIn('status', ['REJECTED']);
        $q = clone $base;
        if ($term = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }
        // 'status' is the guest-list filter below, so the event status uses its own key
        if (in_array($v = $request->query('event_status'), self::eventStatuses(), true)) {
            $q->where('status', $v);
        }
        if ($v = $request->query('from')) $q->whereDate('event_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('event_date', '<=', $v);
        $events = $q->orderBy('event_date')->paginate(config('csa.per_page'))->withQueryString();
        // the opened event stays visible even when it is not on the current page
        $selected = $request->query('event') ? (clone $base)->find((int) $request->query('event')) : null;
        $invites = $selected?->invitations()->with(['customer', 'contact'])->get();
        if ($selected && ($s = $request->query('status'))) {
            $invites = $invites->where('status', $s);
        }
        return view('events.attendance', [
            'events' => $events,
            'eventStatuses' => self::eventStatuses(),
            'selected' => $selected,
            'invites' => $invites,
            'canManage' => $selected?->canManage(auth()->user()) ?? false,
            'contacts' => $selected ? Contact::with('customer')->whereNotIn('id', $selected->invitations->pluck('contact_id')->filter())->orderBy('name')->get() : collect(),
            'statuses' => CsaEvent::INVITE_STATUSES,
        ]);
    }

    private static function eventStatuses(): array
    {
        return array_values(array_diff(CsaEvent::STATUSES, ['REJECTED']));
    }

    // Invite an existing customer contact, or an external guest by name/email
    public function invite(Request $request, CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if (! $event->invitationsOpen()) {
            return back()->with('err', "Invitations are closed for an event in status {$event->status}.");
        }
        $d = $request->validate([
            'contact_id' => 'nullable|exists:contacts,id',
            'name' => 'required_without:contact_id|nullable|string|max:150',
            'email' => 'nullable|email|max:150',
        ]);
        if (! empty($d['contact_id'])) {
            if ($event->invitations()->where('contact_id', $d['contact_id'])->exists()) {
                return back()->with('err', 'This contact is already invited.');
            }
            $c = Contact::findOrFail($d['contact_id']);
            $row = ['contact_id' => $c->id, 'customer_id' => $c->customer_id, 'name' => $c->name, 'email' => $c->email];
        } else {
            $row = ['name' => $d['name'], 'email' => $d['email'] ?? null];
        }
        $inv = $event->invitations()->create($row + ['status' => 'Invited']);
        AuditLog::record('EVENT_INVITED', 'event', $event->id, ['invitation' => $inv->id, 'name' => $inv->name]);
        return back()->with('ok', "{$inv->name} invited.");
    }

    public function status(Request $request, CsaEventInvitation $invitation)
    {
        $event = $invitation->event;
        abort_unless($event->canManage(auth()->user()), 403);
        $d = $request->validate(['status' => 'required|in:' . implode(',', CsaEvent::INVITE_STATUSES)]);
        $isAttendance = in_array($d['status'], ['Attended', 'No Show'], true);

        // RSVP before/around the event; attendance only once the event is held
        if ($isAttendance && ! $event->attendanceOpen()) {
            return back()->with('err', 'Attendance can be marked only after the event is Active/Held.');
        }
        if (! $isAttendance && ! $event->invitationsOpen()) {
            return back()->with('err', "RSVP changes are closed for an event in status {$event->status}.");
        }
        $old = $invitation->status;
        $invitation->update(['status' => $d['status']]);
        AuditLog::record('EVENT_ATTENDANCE', 'event', $event->id, ['invitation' => $invitation->id, 'from' => $old, 'to' => $d['status']]);
        return back()->with('ok', "{$invitation->name}: {$d['status']}.");
    }
}
