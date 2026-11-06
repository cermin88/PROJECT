<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CsaEvent;
use Illuminate\Http\Request;

class PostMortemController extends Controller
{
    public const STATUSES = ['POST_MORTEM', 'CLOSED'];

    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = CsaEvent::with(['owner', 'invitations', 'opportunities', 'pmAuthor'])
            ->whereIn('status', self::STATUSES);
        if ($term = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }
        if (in_array($v = $request->query('status'), self::STATUSES, true)) {
            $q->where('status', $v);
        }
        if (in_array($v = $request->query('pm_objective_met'), ['Yes', 'Partly', 'No'], true)) {
            $q->where('pm_objective_met', $v);
        }
        if ($v = $request->query('from')) $q->whereDate('event_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('event_date', '<=', $v);
        return view('events.postmortem', [
            'events' => $q->orderByDesc('event_date')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function save(Request $request, CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if ($event->status !== 'POST_MORTEM') {
            return back()->with('err', $event->status === 'CLOSED'
                ? 'Event is closed; the post-mortem is locked.'
                : 'Post-mortem opens once the event moves to POST_MORTEM.');
        }
        $d = $request->validate([
            'pm_objective_met' => 'required|in:Yes,Partly,No',
            'pm_issues' => 'nullable|string|max:3000',
            'pm_lessons' => 'required|string|max:3000',
            'pm_follow_up' => 'nullable|string|max:3000',
        ]);
        $event->update($d + ['pm_by' => auth()->id(), 'pm_saved_at' => now()]);
        AuditLog::record('EVENT_POSTMORTEM_SAVED', 'event', $event->id, ['objective_met' => $d['pm_objective_met']]);
        return back()->with('ok', 'Post-mortem saved.');
    }
}
