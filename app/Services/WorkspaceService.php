<?php

namespace App\Services;

use App\Events\MemberJoined;
use App\Jobs\CreateTenantDefaultChannelsJob;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * Create a new tenant (workspace), assign the owner, and spin up default channels.
     */
    public function create(array $data, User $owner): Tenant
    {
        // No DB::transaction here — Tenant::create() fires TenantCreated event which
        // runs CREATE DATABASE (DDL). MySQL DDL auto-commits any open transaction,
        // causing "There is no active transaction" when Laravel tries to commit.

        // Use submitted slug if provided, otherwise generate from name
        $slug = isset($data['slug']) && $data['slug'] !== ''
            ? Str::slug($data['slug'])
            : Str::slug($data['name']);

        // Ensure slug uniqueness
        $baseSlug = $slug;
        $counter  = 1;
        while (Tenant::where('id', $slug)->orWhere('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id'           => $slug,
            'name'         => $data['name'],
            'slug'         => $slug,
            'logo'         => $data['logo'] ?? null,
            'owner_id'     => $owner->id,
            'timezone'     => $data['timezone'] ?? 'UTC',
            'company_size' => $data['company_size'] ?? null,
            'is_active'    => true,
            'settings'     => $data['settings'] ?? [],
        ]);

        // Create domain: use provided domain or auto-generate from slug
        $domain = $data['domain'] ?? ($slug . '.' . config('app.tenant_domain', 'localhost'));
        $tenant->domains()->create(['domain' => $domain]);

        // Link owner to tenant in central pivot table
        DB::connection('mysql')->table('tenant_users')->upsert([
            ['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        ], ['tenant_id', 'user_id'], ['role', 'updated_at']);

        // Queue default channels + workspace settings creation
        CreateTenantDefaultChannelsJob::dispatch($tenant->id, $owner->id)
            ->onQueue('default');

        return $tenant;
    }

    /**
     * Update workspace metadata.
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $fillable = ['name', 'logo', 'timezone', 'company_size', 'is_active', 'settings'];

        $tenant->update(array_intersect_key($data, array_flip($fillable)));

        return $tenant->fresh();
    }

    /**
     * Invite a user (by email) to the workspace with a given role.
     * Sends a WorkspaceInviteNotification. Creates the User record if they don't exist yet.
     */
    public function inviteMember(Tenant $tenant, string $email, string $role = 'member'): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Create a stub account; they'll set their password via the invite link
            $user = User::create([
                'name'      => explode('@', $email)[0],
                'email'     => $email,
                'password'  => bcrypt(Str::random(32)),
                'is_active' => false,
            ]);
        }

        // Generate a signed invite token valid for 7 days
        $token = Str::random(64);

        // Store invite metadata in tenant settings (or a dedicated invites table if available)
        $settings            = $tenant->settings ?? [];
        $settings['invites'] = $settings['invites'] ?? [];

        $settings['invites'][] = [
            'email'      => $email,
            'role'       => $role,
            'token'      => hash('sha256', $token),
            'invited_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ];

        $tenant->update(['settings' => $settings]);

        try {
            $user->notify(new WorkspaceInviteNotification($tenant, $token, $role));
        } catch (\Throwable $e) {
            Log::error("WorkspaceService::inviteMember — notification failed for {$email}: {$e->getMessage()}");
        }
    }

    /**
     * Consume an invite token and grant the given (already-authenticated)
     * user membership in the workspace. This is the ONLY path that is allowed
     * to add a member to `tenant_users` — registering or logging in on a
     * tenant subdomain must never do this on its own.
     */
    public function acceptInvite(Tenant $tenant, string $token, User $user): void
    {
        $settings = $tenant->settings ?? [];
        $invites  = $settings['invites'] ?? [];
        $hashed   = hash('sha256', $token);

        $index = null;
        foreach ($invites as $i => $invite) {
            if (hash_equals($invite['token'], $hashed)) {
                $index = $i;
                break;
            }
        }

        abort_if($index === null, 403, 'This invitation link is invalid or has already been used.');

        $invite = $invites[$index];

        abort_unless(
            strcasecmp($invite['email'], $user->email) === 0,
            403,
            'This invitation was sent to a different email address.'
        );

        abort_if(
            now()->greaterThan(\Illuminate\Support\Carbon::parse($invite['expires_at'])),
            410,
            'This invitation has expired — ask the workspace owner to send a new one.'
        );

        DB::connection('mysql')->table('tenant_users')->upsert([
            ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $invite['role'], 'created_at' => now(), 'updated_at' => now()],
        ], ['tenant_id', 'user_id'], ['role', 'updated_at']);

        if (! $user->is_active) {
            $user->update(['is_active' => true]);
        }

        // Consume the invite so the token can't be replayed.
        unset($invites[$index]);
        $settings['invites'] = array_values($invites);
        $tenant->update(['settings' => $settings]);
    }

    /**
     * Remove a member from the workspace (central user record stays intact).
     * Also detaches them from all tenant channels.
     */
    public function removeMember(Tenant $tenant, User $user): void
    {
        // Remove from central pivot table
        DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->delete();

        tenancy()->initialize($tenant);

        try {
            // Remove from all channels inside the tenant DB
            \App\Models\Tenant\ChannelMember::where('user_id', $user->id)->delete();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Transfer workspace ownership to another user.
     */
    public function transferOwnership(Tenant $tenant, User $newOwner): void
    {
        if ($newOwner->id === $tenant->owner_id) {
            return;
        }

        $tenant->update(['owner_id' => $newOwner->id]);

        tenancy()->initialize($tenant);

        try {
            // Elevate the new owner to channel admin in every channel
            \App\Models\Tenant\ChannelMember::where('user_id', $newOwner->id)
                ->update(['role' => 'admin']);
        } finally {
            tenancy()->end();
        }

        Log::info("WorkspaceService: Ownership of tenant {$tenant->id} transferred to user {$newOwner->id}.");
    }
}
