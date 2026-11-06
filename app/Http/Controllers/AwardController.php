<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class AwardController extends Controller
{
    public const STATUSES = ['PENDING_EVIDENCE', 'QUALIFIED'];

    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $visibleAwards = Award::visibleToProjectOwner(auth()->user());
        $q = (clone $visibleAwards)->with(['customer', 'project']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        if (in_array($v = $request->query('status'), self::STATUSES, true)) {
            $q->where('status', $v);
        }
        if ($v = $request->query('from')) $q->whereDate('award_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('award_date', '<=', $v);

        return view('awards.index', [
            'awards' => $q->orderByDesc('award_date')->paginate(config('csa.per_page'))->withQueryString(),
            'qualifiedTotal' => (float) (clone $visibleAwards)->qualified()->sum('value'),
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Award $award)
    {
        abort_unless(Award::visibleToProjectOwner(auth()->user())->whereKey($award->id)->exists(), 404);
        $award->load(['customer', 'evidence', 'project.milestones', 'invoices']);
        return view('awards.show', compact('award'));
    }

    public function addEvidence(Request $request, Award $award, WorkflowService $wf)
    {
        abort_unless(Award::visibleToProjectOwner(auth()->user())->whereKey($award->id)->exists(), 404);
        $d = $request->validate(['type' => 'required|in:PO,LOA,OFFICIAL_AWARD', 'reference' => 'required|string|max:120']);
        [$ok, $msg] = $wf->addEvidence($award, $d['type'], $d['reference']);
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function requestQualification(Award $award, WorkflowService $wf)
    {
        abort_unless(Award::visibleToProjectOwner(auth()->user())->whereKey($award->id)->exists(), 404);
        [$ok, $msg] = $wf->requestAwardQualification($award, auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }
}
