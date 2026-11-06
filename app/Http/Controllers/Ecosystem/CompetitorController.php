<?php

namespace App\Http\Controllers\Ecosystem;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Competitor;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function index(Request $request)
    {
        $q = Competitor::withCount([
            'opportunities',
            'opportunities as lost_opps_count' => fn ($o) => $o->where('stage', 'Lost'),
        ]);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%"));
        }
        // Head-to-head outcome: competitors we have beaten more often, or lost to more often
        match ($request->query('record')) {
            'winning' => $q->whereColumn('won_against', '>', 'lost_against'),
            'losing' => $q->whereColumn('lost_against', '>', 'won_against'),
            'unmet' => $q->where('encounters', 0),
            default => null,
        };
        return view('ecosystem.competitors.index', [
            'competitors' => $q->orderByDesc('encounters')->paginate(config('csa.per_page'))->withQueryString(),
            'canEdit' => Access::editsEcosystem(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $c = Competitor::create($this->validated($request));
        AuditLog::record('COMPETITOR_CREATED', 'competitor', $c->id, ['name' => $c->name]);
        return redirect()->route('competitors.index')->with('ok', "Competitor {$c->ref()} created.");
    }

    public function edit(Competitor $competitor)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $competitor->load(['opportunities' => fn ($q) => $q->with('customer')->latest('id')]);
        return view('ecosystem.competitors.edit', ['competitor' => $competitor]);
    }

    public function update(Request $request, Competitor $competitor)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $competitor->update($this->validated($request));
        AuditLog::record('COMPETITOR_UPDATED', 'competitor', $competitor->id, ['changed' => array_keys($competitor->getChanges())]);
        return redirect()->route('competitors.index')->with('ok', 'Competitor saved.');
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:255',
            'encounters' => 'required|integer|min:0',
            'won_against' => 'required|integer|min:0',
            'lost_against' => 'required|integer|min:0',
            'known_winning_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
        // Head-to-head results can never exceed recorded encounters
        if ($d['won_against'] + $d['lost_against'] > $d['encounters']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['encounters' => 'Won + Lost cannot exceed Encounters.']);
        }
        return $d;
    }
}
