<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthController extends AdminController
{
    public function index()
    {
        $this->gate(self::VIEWERS);
        $checks = [];

        // Database connection
        try {
            $t = microtime(true);
            DB::select('select 1');
            $checks['Database'] = ['ok', DB::connection()->getDriverName() . ' · ' . round((microtime(true) - $t) * 1000, 1) . ' ms'];
        } catch (Throwable $e) {
            $checks['Database'] = ['bad', Str::limit($e->getMessage(), 120)];
        }

        // Queue + failed jobs (database driver tables)
        $driver = config('queue.default');
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
            $checks['Queue'] = [$failed ? 'warn' : 'ok', "driver {$driver} · " . ($pending ?? 'n/a') . ' pending · ' . ($failed ?? 'n/a') . ' failed'];
        } catch (Throwable $e) {
            $checks['Queue'] = ['bad', Str::limit($e->getMessage(), 120)];
        }

        // Cache round-trip
        try {
            $k = 'health:' . Str::random(8);
            Cache::put($k, 'ok', 10);
            $hit = Cache::pull($k) === 'ok';
            $checks['Cache'] = [$hit ? 'ok' : 'bad', 'store ' . config('cache.default') . ($hit ? ' · write/read OK' : ' · read-back failed')];
        } catch (Throwable $e) {
            $checks['Cache'] = ['bad', Str::limit($e->getMessage(), 120)];
        }

        // Storage writable
        $file = storage_path('app/health-' . Str::random(6) . '.tmp');
        $ok = @file_put_contents($file, 'ok') !== false;
        if ($ok) {
            @unlink($file);
        }
        $free = @disk_free_space(storage_path());
        $checks['Storage'] = [$ok ? 'ok' : 'bad', ($ok ? 'storage/app writable' : 'storage/app NOT writable') . ($free ? ' · ' . round($free / 1073741824, 1) . ' GB free' : '')];

        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->latest('failed_at')->limit(5)->get() : collect();
        $migrations = Schema::hasTable('migrations') ? DB::table('migrations')->count() : 0;

        return view('admin.system-health', [
            'checks' => $checks,
            'failedJobs' => $failedJobs,
            'info' => [
                'Application' => config('app.name'),
                'Environment' => app()->environment(),
                'Debug mode' => config('app.debug') ? 'ON' : 'off',
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Migrations run' => $migrations,
                'Session driver' => config('session.driver'),
                'Server time' => now()->format('d M Y H:i:s T'),
            ],
        ]);
    }
}
