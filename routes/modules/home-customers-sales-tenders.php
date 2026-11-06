<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\Sales\AttentionController;
use App\Http\Controllers\Sales\ContactController;
use App\Http\Controllers\Sales\InsightController;
use App\Http\Controllers\Sales\TenderBoardController;
use App\Http\Controllers\TenderController;
use Illuminate\Support\Facades\Route;

// Paths avoid /customers/{customer} and /tenders/{tender}, which web.php registers first.

// Home
Route::get('/attention', [AttentionController::class, 'index'])->name('attention');
Route::post('/attention/dispose', [AttentionController::class, 'dispose'])->name('attention.dispose');

// Customers
Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
Route::post('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
Route::get('/customer-attention', [InsightController::class, 'customerAttention'])->name('customers.attention');
Route::post('/customers/{customer}/engaged', [InsightController::class, 'markEngaged'])->name('customers.engaged');
Route::post('/customers/{customer}/update', [CustomerController::class, 'update'])->name('customers.update');
Route::post('/customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');

// Sales
Route::get('/forecast', [InsightController::class, 'forecast'])->name('forecast');
Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');
Route::post('/opportunities/{opportunity}/next-action', [OpportunityController::class, 'nextAction'])->name('opportunities.next');
Route::post('/opportunities/{opportunity}/value', [OpportunityController::class, 'value'])->name('opportunities.value');
Route::post('/opportunities/{opportunity}/value/{approval}/apply', [OpportunityController::class, 'applyValue'])->name('opportunities.value.apply');

// Tenders
Route::get('/tender-calendar', [TenderBoardController::class, 'calendar'])->name('tenders.calendar');
Route::get('/tender-warroom', [TenderBoardController::class, 'warroom'])->name('tenders.warroom');
Route::post('/tenders', [TenderController::class, 'store'])->name('tenders.store');
Route::post('/tenders/{tender}/bid', [TenderController::class, 'bid'])->name('tenders.bid');
Route::post('/tenders/{tender}/outcome', [TenderController::class, 'outcome'])->name('tenders.outcome');
Route::post('/tenders/{tender}/retender', [TenderController::class, 'retender'])->name('tenders.retender');
