<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCustomReport;
use App\Models\AuditLog;
use App\Models\CustomReportRun;
use App\Models\User;
use App\Services\Intelligence\CustomReportQuery;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Custom Report Builder (UX-057). Preview is open to all roles; generating a file is an export.
class ReportBuilderController extends Controller
{
    public function index(Request $request, CustomReportQuery $query)
    {
        $modules = CustomReportQuery::modules();
        $def = null;
        $result = null;
        if ($request->filled('module') && $request->has('fields')) {
            $def = CustomReportQuery::normalise($request->validate(CustomReportQuery::rules()));
            $result = $def['fields'] ? $query->run($def, $request->user(), 50) : null;
        }
        $u = auth()->user();
        return view('intelligence.builder', [
            'modules' => $modules,
            'module' => $request->query('module', 'opportunity'),
            'def' => $def,
            'result' => $result,
            'users' => User::orderBy('name')->get(),
            'runs' => CustomReportRun::with('user')->when(! in_array($u->role, ['Superadmin', 'HOD'], true), fn ($q) => $q->where('user_id', $u->id))->latest('id')->limit(20)->get(),
            'canExport' => ReportController::canExport(),
        ]);
    }

    public function generate(Request $request)
    {
        abort_unless(ReportController::canExport(), 403);
        $def = CustomReportQuery::normalise($request->validate(CustomReportQuery::rules()));
        if (! $def['fields']) {
            return back()->with('err', 'Choose at least one field for this module.');
        }
        $run = CustomReportRun::create([
            'code' => WorkflowService::nextCode('RPJ', 'custom_report_runs'),
            'user_id' => auth()->id(), 'module' => $def['module'], 'definition' => $def, 'status' => 'Queued',
        ]);
        AuditLog::record('ASYNC_REPORT_QUEUED', 'custom_report_run', $run->id, ['code' => $run->code, 'module' => $def['module']]);
        // Runs after the response is sent, so no queue worker is needed for the demo
        GenerateCustomReport::dispatchAfterResponse($run->id);
        return redirect()->route('reports.builder', $request->except('_token'))->with('ok', "Report {$run->code} queued. Refresh to see its status.");
    }

    public function download(CustomReportRun $run)
    {
        $u = auth()->user();
        abort_unless(ReportController::canExport() && ($run->user_id === $u->id || in_array($u->role, ['Superadmin', 'HOD'], true)), 403);
        if ($run->status !== 'Completed' || ! $run->file_path || ! Storage::disk('local')->exists($run->file_path)) {
            return back()->with('err', 'This report is not ready to download.');
        }
        AuditLog::record('EXPORT', 'custom_report_run', $run->id, ['code' => $run->code, 'module' => $run->module, 'rows' => $run->row_count]);
        return Storage::disk('local')->download($run->file_path, "{$run->code}.csv", ['Content-Type' => 'text/csv']);
    }
}
