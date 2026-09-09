<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\SiteController;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [SiteController::class, 'index']);

    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site}', [SiteController::class, 'show']);
    Route::delete('/sites/{site}', [SiteController::class, 'destroy']);

    Route::post('/sites/{site}/audits', [AuditController::class, 'store']);
    Route::get('/audits/{audit}', [AuditController::class, 'show']);

    Route::post('/sites/{site}/content', [ContentController::class, 'store']);
    Route::get('/content/{contentPiece}', [ContentController::class, 'show']);
});
