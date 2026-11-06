<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Competitor;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public function audit(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, ['HOD', 'Management', 'Superadmin'], true), 403);
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action')->all();
        $entities = AuditLog::whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type')->all();
        $users = User::orderBy('name')->pluck('name', 'id');

        $q = AuditLog::with('user');
        if (($v = (int) $request->query('user_id')) && $users->has($v)) {
            $q->where('user_id', $v);
        }
        if (in_array($v = $request->query('action'), $actions, true)) {
            $q->where('action', $v);
        }
        if (in_array($v = $request->query('entity_type'), $entities, true)) {
            $q->where('entity_type', $v);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('action', 'like', "%{$s}%")->orWhere('entity_id', $s)->orWhere('ip', 'like', "%{$s}%")->orWhere('detail', 'like', "%{$s}%"));
        }
        if ($d = $request->date('from')) {
            $q->whereDate('created_at', '>=', $d);
        }
        if ($d = $request->date('to')) {
            $q->whereDate('created_at', '<=', $d);
        }
        return view('governance.audit', [
            'logs' => $q->latest('id')->paginate(config('csa.per_page'))->withQueryString(),
            'actions' => $actions, 'entities' => $entities, 'users' => $users->all(),
        ]);
    }

    public function users(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, ['HOD', 'Management', 'Superadmin'], true), 403);
        $roles = User::distinct()->orderBy('role')->pluck('role')->all();
        $q = User::query();
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('job_position', 'like', "%{$s}%"));
        }
        if (in_array($v = $request->query('role'), $roles, true)) {
            $q->where('role', $v);
        }
        if (in_array($v = $request->query('active'), ['1', '0'], true)) {
            $q->where('active', $v === '1');
        }
        return view('governance.users', ['users' => $q->orderBy('code')->paginate(config('csa.per_page'))->withQueryString(), 'roles' => $roles]);
    }

    public function ecosystem()
    {
        // One search box narrows all three lists; each list pages on its own query key
        $s = request()->query('q');
        $like = fn ($cols) => fn ($q) => $q->where(function ($w) use ($cols, $s) {
            foreach ($cols as $c) {
                $w->orWhere($c, 'like', "%{$s}%");
            }
        });
        $class = in_array($c = request()->query('classification'), Partner::CLASSIFICATIONS, true) ? $c : null;
        $category = in_array($c = request()->query('category'), Product::CATEGORIES, true) ? $c : null;
        return view('governance.ecosystem', [
            'partners' => Partner::withCount('products')->when($s, $like(['name', 'classification']))
                ->when($class, fn ($q) => $q->where('classification', 'like', "%{$class}%"))
                ->orderBy('name')->paginate(config('csa.per_page'), ['*'], 'partners_page')->withQueryString(),
            'products' => Product::with('partner')->when($s, $like(['name', 'category']))
                ->when($category, fn ($q) => $q->where('category', $category))
                ->orderBy('name')->paginate(config('csa.per_page'), ['*'], 'products_page')->withQueryString(),
            'competitors' => Competitor::when($s, $like(['name']))->orderBy('name')->paginate(config('csa.per_page'), ['*'], 'competitors_page')->withQueryString(),
            'classifications' => Partner::CLASSIFICATIONS,
            'categories' => Product::CATEGORIES,
        ]);
    }
}
