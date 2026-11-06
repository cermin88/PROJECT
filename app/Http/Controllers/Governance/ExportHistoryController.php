<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

// UX-066 Export History: every EXPORT audit row (who / what / filters / rows / output)
class ExportHistoryController extends Controller
{
    public function index(Request $request)
    {
        $priv = in_array(auth()->user()->role, ['HOD', 'AHOD', 'Management', 'Superadmin'], true);
        $q = AuditLog::with('user')->where('action', 'EXPORT');
        if (! $priv) {
            $q->where('user_id', auth()->id()); // non-privileged roles see only their own exports
        } elseif ($u = $request->integer('user')) {
            $q->where('user_id', $u);
        }
        if ($m = $request->query('entity')) {
            $q->where('entity_type', $m);
        }
        if ($f = $request->query('from')) {
            $q->where('created_at', '>=', $f);
        }

        return view('governance.exports', [
            'logs' => $q->latest('id')->paginate(config('csa.per_page'))->withQueryString(),
            'priv' => $priv,
            'users' => $priv ? User::orderBy('name')->get() : collect(),
            'entities' => AuditLog::where('action', 'EXPORT')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
        ]);
    }
}
