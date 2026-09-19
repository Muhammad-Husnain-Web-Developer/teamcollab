<?php

namespace App\Http\Controllers;

use App\Events\UserPresenceUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();

        return Inertia::render('Profile/Show', [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'display_name' => $user->display_name,
                'bio'          => $user->bio,
                'avatar_url'   => $user->avatar_url,
                'timezone'     => $user->timezone,
                'status'       => $user->status,
                'status_emoji' => $user->status_emoji,
                'status_text'  => $user->status_text,
                'preferences'  => $user->preferences ?? [],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio'          => ['nullable', 'string', 'max:1000'],
            'timezone'     => ['nullable', 'string', 'timezone'],
            'preferences'  => ['nullable', 'array'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $user = auth()->user();

        // Use the central_public disk so the file is stored in storage/app/public
        // (not in a tenant-scoped directory) and served via the standard /storage symlink.
        if ($user->avatar) {
            Storage::disk('central_public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'central_public');

        $user->update(['avatar' => $path]);

        return back()->with('success', 'Avatar updated successfully.');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status'       => ['required', Rule::in(['online', 'away', 'busy', 'offline'])],
            'status_emoji' => ['nullable', 'string', 'max:10'],
            'status_text'  => ['nullable', 'string', 'max:100'],
        ]);

        $user = auth()->user();

        $user->update($validated);

        broadcast(new UserPresenceUpdated($user))->toOthers();

        return back()->with('success', 'Status updated successfully.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }
}
