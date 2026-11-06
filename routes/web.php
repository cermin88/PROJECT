<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CommercialController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenderController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('/pipeline', [OpportunityController::class, 'pipeline'])->name('pipeline');
    Route::post('/opportunities/{opportunity}/stage', [OpportunityController::class, 'stage'])->name('opportunities.stage');

    Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
    Route::get('/tenders/{tender}', [TenderController::class, 'show'])->name('tenders.show');
    Route::post('/tenders/{tender}/checklist/{item}', [TenderController::class, 'toggleChecklist'])->name('tenders.checklist');
    Route::post('/tenders/{tender}/pricing', [TenderController::class, 'savePricing'])->name('tenders.pricing');
    Route::post('/tenders/{tender}/request-approval', [TenderController::class, 'requestApproval'])->name('tenders.approval');
    Route::post('/tenders/{tender}/submit', [TenderController::class, 'submit'])->name('tenders.submit');

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');

    Route::get('/awards', [AwardController::class, 'index'])->name('awards.index');
    Route::get('/awards/{award}', [AwardController::class, 'show'])->name('awards.show');
    Route::post('/awards/{award}/evidence', [AwardController::class, 'addEvidence'])->name('awards.evidence');
    Route::post('/awards/{award}/qualify', [AwardController::class, 'requestQualification'])->name('awards.qualify');

    Route::get('/projects', [CommercialController::class, 'projects'])->name('projects.index');
    Route::post('/milestones/{milestone}', [CommercialController::class, 'milestone'])->name('milestones.update');
    Route::get('/invoices', [CommercialController::class, 'invoices'])->name('invoices.index');
    Route::get('/renewals', [CommercialController::class, 'renewals'])->name('renewals.index');
    Route::post('/renewals/{renewal}/opportunity', [CommercialController::class, 'renewalOpportunity'])->name('renewals.opportunity');

    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/status', [CaseController::class, 'status'])->name('cases.status');
    Route::post('/cases/{case}/assign', [CaseController::class, 'assign'])->name('cases.assign');
    Route::post('/cases/{case}/confirm', [CaseController::class, 'confirm'])->name('cases.confirm');

    Route::get('/kpi', [KpiController::class, 'index'])->name('kpi.index');
    Route::post('/kpi/{kpi}/lock', [KpiController::class, 'lock'])->name('kpi.lock');
    Route::post('/kpi/{kpi}/adjust', [KpiController::class, 'adjust'])->name('kpi.adjust');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::get('/ecosystem', [GovernanceController::class, 'ecosystem'])->name('ecosystem');
    Route::get('/audit', [GovernanceController::class, 'audit'])->name('audit');
    Route::get('/users', [GovernanceController::class, 'users'])->name('users');

    // Module route files added per prototype group (routes/modules/*.php)
    foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
        require $moduleRoutes;
    }

});
