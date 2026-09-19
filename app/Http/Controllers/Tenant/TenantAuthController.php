<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuthController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register', [
            'isTenant'        => true,
            'workspaceName'   => tenant('name'),
        ]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        // Registering here creates a central account, nothing more —
        // workspace membership is granted only by accepting an invite
        // (WorkspaceService::acceptInvite). Auto-joining on register let
        // anyone who could resolve this subdomain become a full member.
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        Auth::login($user);

        // Not a member yet — EnsureTenantMiddleware will bounce them with a
        // clear "you don't have access" message until an invite is accepted.
        return redirect()->route('dashboard');
    }

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'isTenant'      => true,
            'workspaceName' => tenant('name'),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Membership is granted only via an accepted invite
        // (WorkspaceService::acceptInvite) — logging in here does not, by
        // itself, add the user to this workspace. EnsureTenantMiddleware
        // enforces that on the next request.
        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
