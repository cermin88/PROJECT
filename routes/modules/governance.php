<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Governance\ArchiveController;
use App\Http\Controllers\Governance\DataQualityController;
use App\Http\Controllers\Governance\DuplicateController;
use App\Http\Controllers\Governance\ExportHistoryController;
use App\Http\Controllers\Governance\ImportController;
use App\Http\Controllers\Governance\ReassignmentController;
use Illuminate\Support\Facades\Route;

// Governance group (UX-059, UX-061 .. UX-066)
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::post('/documents/{document}/version', [DocumentController::class, 'version'])->name('documents.version');
Route::post('/documents/{document}/status', [DocumentController::class, 'status'])->name('documents.status');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

Route::get('/data-quality', [DataQualityController::class, 'index'])->name('dataquality');
Route::get('/data-quality/export', [DataQualityController::class, 'export'])->name('dataquality.export');

Route::get('/duplicates', [DuplicateController::class, 'index'])->name('duplicates.index');
Route::post('/duplicates/merge', [DuplicateController::class, 'merge'])->name('duplicates.merge');
Route::post('/duplicates/separate', [DuplicateController::class, 'separate'])->name('duplicates.separate');

Route::get('/archive', [ArchiveController::class, 'index'])->name('archive');
Route::post('/archive', [ArchiveController::class, 'store'])->name('archive.store');
Route::post('/archive/restore', [ArchiveController::class, 'restore'])->name('archive.restore');

Route::get('/reassignment', [ReassignmentController::class, 'index'])->name('reassignment');
Route::post('/reassignment', [ReassignmentController::class, 'execute'])->name('reassignment.execute');

Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::post('/import', [ImportController::class, 'upload'])->name('import.upload');
Route::get('/import/template/{entity}', [ImportController::class, 'template'])->name('import.template');
Route::get('/import/{batch}', [ImportController::class, 'show'])->whereNumber('batch')->name('import.show');
Route::post('/import/{batch}/map', [ImportController::class, 'map'])->whereNumber('batch')->name('import.map');
Route::post('/import/{batch}/commit', [ImportController::class, 'commit'])->whereNumber('batch')->name('import.commit');

Route::get('/exports', [ExportHistoryController::class, 'index'])->name('exports.index');
