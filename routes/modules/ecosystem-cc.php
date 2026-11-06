<?php

use App\Http\Controllers\CaseController;
use App\Http\Controllers\Ecosystem\CompetitorController;
use App\Http\Controllers\Ecosystem\ContactCentreController;
use App\Http\Controllers\Ecosystem\PartnerController;
use App\Http\Controllers\Ecosystem\ProductController;
use Illuminate\Support\Facades\Route;

// Ecosystem: Partners (UX-040), Products & Solutions (UX-042), Competitors (UX-043)
Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
Route::get('/partners/create', [PartnerController::class, 'create'])->name('partners.create');
Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
Route::get('/partners/{partner}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
Route::post('/partners/{partner}/archive', [PartnerController::class, 'archive'])->name('partners.archive');
Route::post('/partners/{partner}/contacts', [PartnerController::class, 'addContact'])->name('partners.contacts.store');
Route::delete('/partners/{partner}/contacts/{contact}', [PartnerController::class, 'removeContact'])->name('partners.contacts.destroy');
Route::post('/partners/{partner}/agreements', [PartnerController::class, 'addAgreement'])->name('partners.agreements.store');
Route::put('/partners/{partner}/agreements/{agreement}', [PartnerController::class, 'updateAgreement'])->name('partners.agreements.update');
Route::post('/partners/{partner}/products', [PartnerController::class, 'linkProduct'])->name('partners.products.link');
Route::post('/partners/{partner}/links', [PartnerController::class, 'link'])->name('partners.links.store');
Route::delete('/partners/{partner}/links/{type}/{id}', [PartnerController::class, 'unlink'])->whereNumber('id')->name('partners.links.destroy');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');

Route::get('/competitors', [CompetitorController::class, 'index'])->name('competitors.index');
Route::post('/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
Route::get('/competitors/{competitor}/edit', [CompetitorController::class, 'edit'])->name('competitors.edit');
Route::put('/competitors/{competitor}', [CompetitorController::class, 'update'])->name('competitors.update');

// Contact Centre: dashboard + SLA/CSAT (UX-047), assignment console (UX-046)
Route::get('/cc', [ContactCentreController::class, 'dashboard'])->name('cc.dashboard');
Route::get('/cc/assignment', [ContactCentreController::class, 'assignment'])->name('cc.assignment');
Route::post('/cc/assignment/{case}', [ContactCentreController::class, 'assign'])->name('cc.assign');
Route::get('/cc/sla', [ContactCentreController::class, 'sla'])->name('cc.sla');
Route::post('/cc/sla/{case}/csat', [ContactCentreController::class, 'csat'])->name('cc.csat');

// Case Queue additions (UX-044/045): create, timeline note, reopen with reason
Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
Route::post('/cases/{case}/note', [CaseController::class, 'note'])->name('cases.note');
Route::post('/cases/{case}/reopen', [CaseController::class, 'reopen'])->name('cases.reopen');
