<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base class for tests that need to run inside a tenant.
 *
 * Each test gets a throwaway tenant with its own database, migrated and
 * initialized, then dropped in tearDown. RefreshDatabase only covers the
 * central connection, so tenant databases are cleaned up explicitly.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $id = 'test' . Str::lower(Str::random(8));

        $this->tenant = Tenant::create([
            'id'        => $id,
            'name'      => 'Test Workspace',
            'slug'      => $id,
            // EnsureTenantMiddleware rejects inactive workspaces with a 403.
            'is_active' => true,
        ]);

        $this->tenant->domains()->create(['domain' => $this->tenant->id . '.localhost']);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        // End tenancy before RefreshDatabase rolls the central transaction back,
        // otherwise the tenant connection is left pointing at a dropped schema.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        try {
            DB::purge('tenant');
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        } catch (\Throwable) {
            // A failed test may never have created the database; nothing to drop.
        }

        parent::tearDown();
    }

    /**
     * Create a central user and attach them to the current tenant.
     */
    protected function tenantUser(string $role = 'member', array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name'              => 'Test User',
            'email'             => Str::lower(Str::random(10)) . '@example.test',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ], $attributes));

        DB::connection('mysql')->table('tenant_users')->insert([
            'tenant_id'  => $this->tenant->id,
            'user_id'    => $user->id,
            'role'       => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
