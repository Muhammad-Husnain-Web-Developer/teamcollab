<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Central Domain)
|--------------------------------------------------------------------------
*/

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('workspaces.index');
    }
    return redirect()->route('login');
});

// Guest-only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    // Throttled: unauthenticated POSTs are the credential-stuffing surface.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');

    Route::get('/forgot-password', ForgotPasswordController::class)->name('password.request');
    Route::post('/forgot-password', ForgotPasswordController::class);

    Route::get('/reset-password/{token}', ResetPasswordController::class)->name('password.reset');
    Route::post('/reset-password', ResetPasswordController::class)->name('password.update');
});

// Logout (requires auth)
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Email verification routes (requires auth)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Invite acceptance (requires login, but not email verification — the
// invite email itself is the proof of ownership for that address).
Route::middleware(['auth'])->group(function () {
    Route::get('/workspaces/{tenant}/invites/accept', [WorkspaceController::class, 'acceptInvite'])
        ->name('workspaces.invites.accept');
});

// Workspace management routes (requires auth + verified email)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{tenant}/settings', [WorkspaceController::class, 'edit'])->name('workspaces.edit');
    Route::put('/workspaces/{tenant}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{tenant}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/{tenant}/invite', [WorkspaceController::class, 'inviteMember'])->name('workspaces.invite');
    Route::delete('/workspaces/{tenant}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.remove');
    Route::get('/workspaces/{tenant}', [WorkspaceController::class, 'show'])->name('workspaces.show');
});
