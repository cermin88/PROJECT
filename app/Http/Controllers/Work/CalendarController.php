<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CsaEvent;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Unified month calendar: activities, task due dates, MoM meetings, events and tender closing dates
class CalendarController extends Controller
{
    use WorkAccess;

    public function index(Request $request)
    {
        $u = auth()->user();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01')->startOfDay()
            : today()->startOfMonth();
        $from = $month->copy()->startOfWeek(Carbon::MONDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $show = $request->query('show', ['activity', 'task', 'mom', 'event', 'tender']);
        $show = is_array($show) ? $show : [$show];

        $items = collect();
        if (in_array('activity', $show, true)) {
            foreach ($this->scopeActivities(Activity::query(), $u)->whereBetween('activity_at', [$from, $to->copy()->endOfDay()])->get() as $a) {
                $items->push(['date' => $a->activity_at, 'kind' => 'Activity', 'label' => $a->activity_at->format('H:i') . ' ' . $a->subject,
                    'status' => $a->status, 'url' => route('activities.edit', $a), 'cls' => $a->status === 'CANCELLED' ? 'mute' : '']);
            }
        }
        if (in_array('task', $show, true)) {
            foreach ($this->scopeTasks(Task::query(), $u)->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->get() as $t) {
                $items->push(['date' => $t->due_date, 'kind' => 'Task', 'label' => $t->title, 'status' => $t->isOverdue() ? 'Overdue' : $t->status,
                    'url' => route('tasks.edit', $t), 'cls' => $t->isOverdue() ? 'bad' : ($t->status === 'Completed' ? 'ok' : '')]);
            }
        }
        if (in_array('mom', $show, true)) {
            foreach ($this->scopeMinutes(MeetingMinute::query(), $u)->whereBetween('meeting_at', [$from, $to->copy()->endOfDay()])->get() as $m) {
                $items->push(['date' => $m->meeting_at, 'kind' => 'Meeting', 'label' => $m->title, 'status' => $m->status, 'url' => route('mom.show', $m), 'cls' => '']);
            }
        }
        if (in_array('event', $show, true)) {
            // Events module (UX-030): every role may view events, rejected ones are left out
            foreach (CsaEvent::where('status', '!=', 'REJECTED')->whereBetween('event_date', [$from, $to->copy()->endOfDay()])->get() as $e) {
                $items->push(['date' => $e->event_date, 'kind' => 'Event', 'label' => $e->code . ' ' . $e->name, 'status' => $e->status,
                    'url' => route('events.show', $e), 'cls' => $e->status === 'CLOSED' ? 'mute' : '']);
            }
        }
        if (in_array('tender', $show, true)) {
            foreach (Tender::whereBetween('closing_at', [$from, $to->copy()->endOfDay()])->get() as $t) {
                $items->push(['date' => $t->closing_at, 'kind' => 'Tender closing', 'label' => $t->code . ' ' . $t->title, 'status' => $t->status,
                    'url' => route('tenders.show', $t), 'cls' => in_array($t->status, ['AWARDED', 'LOST', 'CANCELLED', 'NO_BID', 'SUBMITTED'], true) ? 'mute' : 'warn']);
            }
        }

        return view('work.calendar', [
            'month' => $month, 'from' => $from, 'to' => $to, 'show' => $show,
            'byDay' => $items->sortBy('date')->groupBy(fn ($i) => $i['date']->toDateString()),
        ]);
    }
}
