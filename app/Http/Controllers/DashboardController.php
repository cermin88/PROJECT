<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\CsaEvent;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Partner;
use App\Models\Renewal;
use App\Models\SupportCase;
use App\Models\Task;
use App\Models\Tender;
use App\Services\WorkflowService;

class DashboardController extends Controller
{
    public function index(WorkflowService $wf)
    {
        $since = now()->subDays(30);
        $recent = collect();
        $addRecent = function ($records, string $type, callable $url) use (&$recent): void {
            foreach ($records as $record) {
                $recent->push([
                    'type' => $type,
                    'title' => $record->name ?? $record->title ?? $record->subject,
                    'created_at' => $record->created_at,
                    'url' => $url($record),
                ]);
            }
        };
        $addRecent(Customer::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Customer',
            fn ($record) => route('customers.show', $record));
        $addRecent(Contact::whereHas('customer', fn ($query) => $query->where('owner_id', auth()->id()))
            ->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Contact', fn () => route('contacts.index'));
        $addRecent(Opportunity::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Opportunity',
            fn ($record) => route('opportunities.show', $record));
        $addRecent(Activity::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Activity',
            fn () => route('activities.index'));
        $addRecent(Task::where('created_by', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Task',
            fn () => route('tasks.index'));
        $addRecent(Tender::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Tender',
            fn ($record) => route('tenders.show', $record));
        $addRecent(CsaEvent::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Event',
            fn ($record) => route('events.show', $record));
        $addRecent(Partner::where('owner_id', auth()->id())->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Partner',
            fn ($record) => route('partners.show', $record));
        $addRecent(SupportCase::query()->when(auth()->user()->role === 'CC Executive', fn ($query) => $query->where('assignee_id', auth()->id()))
            ->where('created_at', '>=', $since)->latest()->take(5)->get(), 'Case',
            fn ($record) => route('cases.show', $record));

        return view('dashboard', [
            'm' => $wf->metrics(auth()->user()),
            'closingTenders' => Tender::whereIn('status', Tender::SUBMITTABLE)->orderBy('closing_at')->take(5)->get(),
            'myApprovals' => Approval::where('status', 'Pending')->where('approver_id', auth()->id())->with('requester')->take(5)->get(),
            'staleOpps' => Opportunity::active()->where('updated_at', '<', now()->subDays(30))->take(5)->get(),
            'renewalsDue' => Renewal::visibleToProjectOwner(auth()->user())->where('status', 'DUE_SOON')
                ->with('customer')->orderBy('expiry_date')->take(5)->get(),
            'stages' => Opportunity::active()->selectRaw('stage, count(*) as n, sum(value) as total')->groupBy('stage')->get()->keyBy('stage'),
            'recentRecords' => $recent->sortByDesc('created_at')->take(8)->values(),
        ]);
    }
}
