<?php

use App\Http\Controllers\Work\ActivityController;
use App\Http\Controllers\Work\CalendarController;
use App\Http\Controllers\Work\MomController;
use App\Http\Controllers\Work\MyWorkController;
use App\Http\Controllers\Work\TaskController;
use Illuminate\Support\Facades\Route;

// Work module (UX-022 .. UX-027)
Route::get('/mywork', [MyWorkController::class, 'index'])->name('mywork');

Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
Route::post('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
Route::post('/activities/{activity}/status', [ActivityController::class, 'status'])->name('activities.status');

Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
Route::post('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
Route::post('/tasks/{task}/advance', [TaskController::class, 'advance'])->name('tasks.advance');
Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');

Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

Route::get('/mom', [MomController::class, 'index'])->name('mom.index');
Route::get('/mom/create', [MomController::class, 'create'])->name('mom.create');
Route::post('/mom', [MomController::class, 'store'])->name('mom.store');
Route::get('/mom/{mom}', [MomController::class, 'show'])->name('mom.show');
Route::get('/mom/{mom}/edit', [MomController::class, 'edit'])->name('mom.edit');
Route::post('/mom/{mom}', [MomController::class, 'update'])->name('mom.update');
Route::post('/mom/{mom}/submit', [MomController::class, 'submit'])->name('mom.submit');
Route::post('/mom/{mom}/decide', [MomController::class, 'decide'])->name('mom.decide');
Route::post('/mom/{mom}/actions', [MomController::class, 'addAction'])->name('mom.actions.store');
Route::post('/mom/{mom}/actions/{item}/task', [MomController::class, 'spawn'])->name('mom.actions.task');
