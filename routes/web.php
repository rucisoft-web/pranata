<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\RiskController;
use App\Http\Middleware\ResolveEntity;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/entity', [EntityController::class, 'switch'])->name('entity.switch');
    Route::middleware(ResolveEntity::class)->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('risks', RiskController::class)->except('destroy')->where(['risk' => '[0-9]+']);
        Route::post('/risks/{risk}/{operation}', [RiskController::class, 'transition'])
            ->where(['risk' => '[0-9]+', 'operation' => 'submit|approve|return'])->name('risks.transition');
    });
});
