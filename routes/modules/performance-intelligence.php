<?php

use App\Http\Controllers\Intelligence\AnalyticsController;
use App\Http\Controllers\Intelligence\KpiAdminController;
use App\Http\Controllers\Intelligence\KpiTeamController;
use App\Http\Controllers\Intelligence\ReportBuilderController;
use App\Http\Controllers\Intelligence\ReportController;
use App\Http\Controllers\KpiController;
use Illuminate\Support\Facades\Route;

// Performance (kpi.index / kpi.lock / kpi.adjust are in routes/web.php)
Route::post('/kpi/{kpi}/evidence', [KpiController::class, 'evidence'])->name('kpi.evidence');
Route::get('/kpi/team', [KpiTeamController::class, 'team'])->name('kpi.team');
Route::get('/kpi/review', [KpiTeamController::class, 'review'])->name('kpi.review');
Route::post('/kpi/{kpi}/review', [KpiTeamController::class, 'saveReview'])->name('kpi.review.save');
Route::get('/kpi/admin', [KpiAdminController::class, 'index'])->name('kpi.admin');
Route::post('/kpi/admin', [KpiAdminController::class, 'store'])->name('kpi.admin.store');
Route::post('/kpi/admin/copy', [KpiAdminController::class, 'copy'])->name('kpi.admin.copy');
Route::post('/kpi/admin/{kpi}', [KpiAdminController::class, 'update'])->name('kpi.admin.update');

// Intelligence
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/builder', [ReportBuilderController::class, 'index'])->name('reports.builder');
Route::post('/reports/builder', [ReportBuilderController::class, 'generate'])->name('reports.builder.generate');
Route::get('/reports/runs/{run}/download', [ReportBuilderController::class, 'download'])->name('reports.builder.download');
Route::get('/reports/pack', [ReportController::class, 'packView'])->name('reports.pack');
Route::get('/reports/pack/export', [ReportController::class, 'packExport'])->name('reports.pack.export');
Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report')->name('reports.show');
Route::get('/reports/{report}/export', [ReportController::class, 'export'])->whereNumber('report')->name('reports.export');
