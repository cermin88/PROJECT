<?php

use App\Http\Controllers\Admin\ApprovalMatrixController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\WorkingCalendarController;
use Illuminate\Support\Facades\Route;

// Admin group (UX-069 .. UX-074, P2L) — role checks live in the controllers
Route::get('/admin/permissions', [PermissionController::class, 'index'])->name('permissions');
Route::post('/admin/permissions', [PermissionController::class, 'update'])->name('permissions.update');

Route::get('/admin/configuration', [ConfigurationController::class, 'index'])->name('configuration');
Route::post('/admin/configuration', [ConfigurationController::class, 'update'])->name('configuration.update');

Route::get('/admin/approval-matrix', [ApprovalMatrixController::class, 'index'])->name('approval-matrix');
Route::post('/admin/approval-matrix/types', [ApprovalMatrixController::class, 'storeType'])->name('approval-matrix.types.store');
Route::post('/admin/approval-matrix/types/{type}', [ApprovalMatrixController::class, 'updateType'])->name('approval-matrix.types.update');
Route::post('/admin/delegations', [ApprovalMatrixController::class, 'storeDelegation'])->name('delegations.store');
Route::post('/admin/delegations/{delegation}/revoke', [ApprovalMatrixController::class, 'revokeDelegation'])->name('delegations.revoke');

Route::get('/admin/working-calendar', [WorkingCalendarController::class, 'index'])->name('working-calendar');
Route::post('/admin/working-calendar', [WorkingCalendarController::class, 'store'])->name('working-calendar.store');
Route::post('/admin/working-calendar/weekend', [WorkingCalendarController::class, 'weekend'])->name('working-calendar.weekend');
Route::post('/admin/working-calendar/{holiday}/delete', [WorkingCalendarController::class, 'destroy'])->name('working-calendar.destroy');

Route::get('/admin/master-data', [MasterDataController::class, 'index'])->name('master-data');
Route::post('/admin/master-data', [MasterDataController::class, 'store'])->name('master-data.store');
Route::post('/admin/master-data/{value}/toggle', [MasterDataController::class, 'toggle'])->name('master-data.toggle');
Route::post('/admin/master-data/{value}/move', [MasterDataController::class, 'move'])->name('master-data.move');

Route::get('/admin/system-health', [SystemHealthController::class, 'index'])->name('system-health');

// Top bar
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
