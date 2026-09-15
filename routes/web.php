<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SocialPostController;
use App\Http\Controllers\TeamController;

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
    Route::post('/sites/{site}/audit-frequency', [SiteController::class, 'updateFrequency']);
    Route::post('/sites/{site}/host', [SiteController::class, 'updateHost']);

    Route::post('/sites/{site}/audits', [AuditController::class, 'store']);
    Route::get('/audits/{audit}', [AuditController::class, 'show']);
    Route::get('/audits/{audit}/pdf', [AuditController::class, 'pdf']);
    Route::post('/audits/{audit}/recommendations', [AuditController::class, 'recommend']);

    Route::post('/sites/{site}/content', [ContentController::class, 'store']);
    Route::get('/content/{contentPiece}', [ContentController::class, 'show']);

    Route::get('/sites/{site}/competitors/lookup', [CompetitorController::class, 'lookup']);
    Route::post('/sites/{site}/competitors/search', [CompetitorController::class, 'search']);
    Route::post('/sites/{site}/competitors', [CompetitorController::class, 'store']);
    Route::get('/competitors/{comparison}', [CompetitorController::class, 'show']);

    Route::post('/sites/{site}/social', [SocialPostController::class, 'store']);
    Route::get('/social/{post}', [SocialPostController::class, 'show']);

    Route::post('/sites/{site}/newsletters', [NewsletterController::class, 'store']);
    Route::get('/newsletters/{newsletter}', [NewsletterController::class, 'show']);
    Route::post('/newsletters/{newsletter}/send', [NewsletterController::class, 'send']);
    Route::post('/sites/{site}/subscribers', [NewsletterController::class, 'addSubscriber']);
    Route::delete('/subscribers/{subscriber}', [NewsletterController::class, 'destroySubscriber']);

    Route::post('/sites/{site}/keywords', [KeywordController::class, 'store']);
    Route::post('/keywords/{tracked}/check', [KeywordController::class, 'checkNow']);
    Route::delete('/keywords/{tracked}', [KeywordController::class, 'destroy']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::post('/team/{teamMember}/access', [TeamController::class, 'updateAccess']);
    Route::delete('/team/{teamMember}', [TeamController::class, 'destroy']);

    Route::get('/platform', [PlatformController::class, 'index']);
    Route::post('/platform/users/{user}', [PlatformController::class, 'updateUser']);
    Route::delete('/platform/users/{user}', [PlatformController::class, 'destroyUser']);
    Route::delete('/platform/accounts/{account}', [PlatformController::class, 'destroyAccount']);
});
