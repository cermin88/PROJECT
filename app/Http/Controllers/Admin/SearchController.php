<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Global search (top bar): name / title or Business ID across the core records
class SearchController extends Controller
{
    // type => [table, id column, label column, status column, show route]
    private const SOURCES = [
        'Customer' => ['customers', 'code', 'name', 'relationship', 'customers.show'],
        'Contact' => ['contacts', 'email', 'name', 'buying_role', 'contacts.show'],
        'Opportunity' => ['opportunities', 'code', 'name', 'stage', 'opportunities.show'],
        'Tender' => ['tenders', 'code', 'title', 'status', 'tenders.show'],
        'Award' => ['awards', 'code', 'title', 'status', 'awards.show'],
        'Case' => ['cases', 'code', 'title', 'status', 'cases.show'],
        'Partner' => ['partners', 'reg_no', 'name', 'classification', 'partners.show'],
        'Document' => ['documents', 'code', 'title', 'status', 'documents.show'],
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        // Optional type filter: one type is fully paginated; all types show the top 20 each with a "see all" link
        $only = array_key_exists($t = (string) $request->query('type'), self::SOURCES) ? $t : null;
        $groups = [];
        $counts = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            foreach (self::SOURCES as $type => [$table, $idCol, $label, $status, $route]) {
                if (($only && $only !== $type) || ! Schema::hasTable($table)) {
                    continue;
                }
                $b = DB::table($table)->select('id', "$idCol as bid", "$label as label", "$status as status")
                    ->where(fn ($w) => $w->where($label, 'like', $like)->orWhere($idCol, 'like', $like))
                    ->tap(fn ($b) => $this->scope($b, $type, $request->user()))
                    ->orderBy($label);
                $link = function ($r) use ($route, $table) {
                    $r->url = Route::has($route) ? route($route, $r->id)
                        : ($table === 'contacts' && Route::has('contacts.index') ? route('contacts.index', ['q' => $r->label]) : null);
                    return $r;
                };
                if ($only) {
                    $rows = $b->paginate(config('csa.per_page'))->withQueryString()->through($link);
                    $counts[$type] = $rows->total();
                } else {
                    $counts[$type] = (clone $b)->count();
                    $rows = $b->limit(20)->get()->map($link);
                }
                if ($rows->isNotEmpty()) {
                    $groups[$type] = $rows;
                }
            }
        }
        return view('admin.search', ['q' => $q, 'groups' => $groups, 'counts' => $counts, 'only' => $only,
            'types' => array_keys(self::SOURCES), 'total' => array_sum($counts)]);
    }

    // Same visibility rules as the list screens: archived rows hidden, CC Executive sees own cases, restricted documents only for privileged roles
    private function scope($b, string $type, $user): void
    {
        $table = self::SOURCES[$type][0];
        if (Schema::hasColumn($table, 'archived_at')) {
            $b->whereNull("$table.archived_at");
        }
        if (in_array($type, ['Customer', 'Partner'], true)) {
            $b->where('archived', false);
        }
        if ($type === 'Case' && $user->role === 'CC Executive') {
            $b->where('assignee_id', $user->id);
        }
        if ($type === 'Document' && ! $user->canSeeRestricted()) {
            $b->where('restricted', false);
        }
        if ($type === 'Award') {
            $b->where(function ($query) use ($user) {
                $query->whereNotExists(function ($project) {
                    $project->selectRaw('1')->from('projects')->whereColumn('projects.award_id', 'awards.id');
                })->orWhereIn('awards.id', Project::visibleTo($user)->select('award_id'));
            });
        }
    }
}
