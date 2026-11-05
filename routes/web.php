<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\RiskController;
use App\Http\Middleware\ResolveEntity;
use App\Http\Middleware\RequireMfa;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\ObligationController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditFindingController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ExecutiveSummaryController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::prefix('demo')->name('demo.')->middleware(\App\Http\Middleware\DemoAccess::class)->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/ringkasan', ExecutiveSummaryController::class)->name('summary');
    Route::get('/risks', [RiskController::class, 'index'])->name('risks.index');
    Route::get('/risks/{risk}', [RiskController::class, 'show'])->whereNumber('risk')->name('risks.show');
    Route::get('/obligations', [ObligationController::class, 'index'])->name('obligations.index');
    Route::get('/obligations/{obligation}', [ObligationController::class, 'show'])->whereNumber('obligation')->name('obligations.show');
    Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance.index');
    Route::get('/compliance/{occurrence}', [ComplianceController::class, 'show'])->whereNumber('occurrence')->name('compliance.show');
    Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');
    Route::get('/audits/{audit}', [AuditController::class, 'show'])->whereNumber('audit')->name('audits.show');
    Route::get('/audit-actions', [AuditFindingController::class, 'index'])->name('findings.index');
    Route::get('/audit-findings/{finding}', [AuditFindingController::class, 'show'])->whereNumber('finding')->name('findings.show');
    Route::get('/evidence/{evidence}', [EvidenceController::class, 'download'])->whereNumber('evidence')->name('evidence.download');
    Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
    Route::get('/governance/{assessment}', [GovernanceController::class, 'show'])->whereNumber('assessment')->name('governance.show');
    Route::get('/governance/{assessment}/disclosure', [GovernanceController::class, 'disclosure'])->whereNumber('assessment')->name('governance.disclosure');
    Route::get('/governance/{assessment}/export', [GovernanceController::class, 'export'])->whereNumber('assessment')->name('governance.export');
    Route::get('/governance/items/{item}', [GovernanceController::class, 'item'])->whereNumber('item')->name('governance.items.show');
});
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/setup', [MfaController::class, 'enroll'])->middleware('throttle:10,1')->name('mfa.enroll');
    Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaController::class, 'verify'])->middleware('throttle:10,1')->name('mfa.verify');
    Route::middleware(RequireMfa::class)->group(function () {
        Route::get('/security', [MfaController::class, 'security'])->name('mfa.security');
        Route::post('/security/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1')->name('password.change');
        Route::get('/mfa/recovery', [MfaController::class, 'recovery'])->name('mfa.recovery');
        Route::post('/mfa/recovery', [MfaController::class, 'rotate'])->middleware('throttle:10,1')->name('mfa.rotate');
        Route::post('/entity', [EntityController::class, 'switch'])->name('entity.switch');
    });
    Route::middleware([RequireMfa::class, ResolveEntity::class])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/ringkasan', ExecutiveSummaryController::class)->name('summary');
        Route::resource('risks', RiskController::class)->except('destroy')->where(['risk' => '[0-9]+']);
        Route::post('/risks/{risk}/{operation}', [RiskController::class, 'transition'])
            ->where(['risk' => '[0-9]+', 'operation' => 'submit|approve|return'])->name('risks.transition');
        Route::resource('obligations', ObligationController::class)->except('destroy')->where(['obligation' => '[0-9]+']);
        Route::post('/regulatory-sources', [ObligationController::class, 'source'])->name('sources.store');
        Route::post('/obligations/{obligation}/periods', [ObligationController::class, 'period'])->whereNumber('obligation')->name('obligations.period');
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance.index');
        Route::get('/compliance/{occurrence}', [ComplianceController::class, 'show'])->whereNumber('occurrence')->name('compliance.show');
        Route::post('/compliance/{occurrence}/{operation}', [ComplianceController::class, 'transition'])->where(['occurrence' => '[0-9]+', 'operation' => 'submit|verify|return'])->name('compliance.transition');
        Route::resource('audits', AuditController::class)->only(['index', 'create', 'store', 'show'])->where(['audit' => '[0-9]+']);
        Route::post('/audits/{audit}/procedures', [AuditController::class, 'procedure'])->whereNumber('audit')->name('audits.procedure');
        Route::post('/audits/{audit}/findings', [AuditController::class, 'finding'])->whereNumber('audit')->name('audits.finding');
        Route::post('/audits/{audit}/{operation}', [AuditController::class, 'transition'])->where(['audit' => '[0-9]+', 'operation' => 'start|submit|return|finalize|close'])->name('audits.transition');
        Route::get('/audit-actions', [AuditFindingController::class, 'index'])->name('findings.index');
        Route::get('/audit-findings/{finding}', [AuditFindingController::class, 'show'])->whereNumber('finding')->name('findings.show');
        Route::post('/audit-findings/{finding}/{operation}', [AuditFindingController::class, 'transition'])->where(['finding' => '[0-9]+', 'operation' => 'submit|close|return'])->name('findings.transition');
        Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
        Route::post('/governance', [GovernanceController::class, 'store'])->name('governance.store');
        Route::get('/governance/{assessment}', [GovernanceController::class, 'show'])->whereNumber('assessment')->name('governance.show');
        Route::post('/governance/{assessment}/assign', [GovernanceController::class, 'assign'])->whereNumber('assessment')->name('governance.assign');
        Route::post('/governance/{assessment}/approve', [GovernanceController::class, 'approve'])->whereNumber('assessment')->name('governance.approve');
        Route::get('/governance/{assessment}/disclosure', [GovernanceController::class, 'disclosure'])->whereNumber('assessment')->name('governance.disclosure');
        Route::get('/governance/{assessment}/export', [GovernanceController::class, 'export'])->whereNumber('assessment')->name('governance.export');
        Route::get('/governance/items/{item}', [GovernanceController::class, 'item'])->whereNumber('item')->name('governance.items.show');
        Route::put('/governance/items/{item}', [GovernanceController::class, 'update'])->whereNumber('item')->name('governance.items.update');
        Route::post('/governance/items/{item}/actions', [GovernanceController::class, 'action'])->whereNumber('item')->name('governance.items.action');
        Route::post('/governance/items/{item}/{operation}', [GovernanceController::class, 'transition'])->where(['item' => '[0-9]+', 'operation' => 'submit|verify|return'])->name('governance.items.transition');
        Route::post('/governance/actions/{action}/done', [GovernanceController::class, 'complete'])->whereNumber('action')->name('governance.actions.done');
        Route::post('/evidence/{type}/{subject}', [EvidenceController::class, 'store'])->where(['type' => 'compliance|finding|procedure|governance', 'subject' => '[0-9]+'])->name('evidence.store');
        Route::get('/evidence/{evidence}', [EvidenceController::class, 'download'])->whereNumber('evidence')->name('evidence.download');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
        Route::post('/users/{user}/password', [UserController::class, 'password'])->whereNumber('user')->middleware('throttle:10,1')->name('users.password');
        Route::post('/users/{user}/mfa-reset', [UserController::class, 'mfa'])->whereNumber('user')->middleware('throttle:10,1')->name('users.mfa');
        Route::post('/entities', [UserController::class, 'entity'])->name('entities.store');
    });
});
