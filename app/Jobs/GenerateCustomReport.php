<?php

namespace App\Jobs;

use App\Models\CustomReportRun;
use App\Models\User;
use App\Services\Intelligence\CustomReportQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Builds a custom report CSV outside the request: Queued -> Processing -> Completed | Failed
class GenerateCustomReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId) {}

    public function handle(CustomReportQuery $query): void
    {
        $run = CustomReportRun::find($this->runId);
        if (! $run || $run->status !== 'Queued') {
            return;
        }
        $run->update(['status' => 'Processing', 'started_at' => now()]);
        try {
            $res = $query->run($run->definition, User::findOrFail($run->user_id));
            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, $res['headers']);
            foreach ($res['rows'] as $row) {
                fputcsv($fh, $row);
            }
            rewind($fh);
            $path = "reports/{$run->code}.csv";
            Storage::disk('local')->put($path, stream_get_contents($fh));
            fclose($fh);
            $run->update(['status' => 'Completed', 'row_count' => $res['total'], 'file_path' => $path, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $run->update(['status' => 'Failed', 'error' => mb_substr($e->getMessage(), 0, 250), 'completed_at' => now()]);
        }
    }
}
