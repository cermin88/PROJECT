<?php

use App\Http\Controllers\Events\AttendanceController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\PostMortemController;
use Illuminate\Support\Facades\Route;

// Events module (UX-030 / UX-032 / UX-033)
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::post('/events', [EventController::class, 'store'])->name('events.store');

Route::get('/events/attendance', [AttendanceController::class, 'index'])->name('events.attendance');
Route::post('/events/invitations/{invitation}', [AttendanceController::class, 'status'])->name('events.invitations.status');
Route::get('/events/post-mortem', [PostMortemController::class, 'index'])->name('events.postmortem');

Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event')->name('events.show');
Route::post('/events/{event}', [EventController::class, 'update'])->whereNumber('event')->name('events.update');
Route::post('/events/{event}/actual', [EventController::class, 'actual'])->name('events.actual');
Route::post('/events/{event}/submit', [EventController::class, 'submit'])->name('events.submit');
Route::post('/events/{event}/decide', [EventController::class, 'decide'])->name('events.decide');
Route::post('/events/{event}/advance', [EventController::class, 'advance'])->name('events.advance');
Route::post('/events/{event}/opportunities', [EventController::class, 'linkOpportunity'])->name('events.opportunities.link');
Route::post('/events/{event}/opportunities/{opportunity}/unlink', [EventController::class, 'unlinkOpportunity'])->name('events.opportunities.unlink');
Route::post('/events/{event}/invitations', [AttendanceController::class, 'invite'])->name('events.invite');
Route::post('/events/{event}/post-mortem', [PostMortemController::class, 'save'])->name('events.postmortem.save');
