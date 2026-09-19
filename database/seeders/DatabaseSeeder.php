<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Refusing to seed demo accounts (admin@teamcollab.app / password) into production.');
            return;
        }

        $this->call(PlanSeeder::class);

        // Create demo owner user
        $owner = User::updateOrCreate(
            ['email' => 'admin@teamcollab.app'],
            [
                'name'              => 'Admin User',
                'email'             => 'admin@teamcollab.app',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'status'            => 'online',
                'is_active'         => true,
            ]
        );

        // Create demo team member
        User::updateOrCreate(
            ['email' => 'john@teamcollab.app'],
            [
                'name'              => 'John Doe',
                'email'             => 'john@teamcollab.app',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'status'            => 'online',
                'is_active'         => true,
            ]
        );

        $member = User::where('email', 'john@teamcollab.app')->first();

        // Create demo tenant
        $tenant = Tenant::updateOrCreate(
            ['id' => 'acme'],
            [
                'id'           => 'acme',
                'name'         => 'Acme Corporation',
                'slug'         => 'acme',
                'owner_id'     => $owner->id,
                'timezone'     => 'UTC',
                'company_size' => '11-50',
                'is_active'    => true,
            ]
        );

        $tenant->domains()->updateOrCreate(
            ['domain' => 'acme.localhost'],
            ['domain' => 'acme.localhost']
        );

        // Link demo users to tenant
        \DB::table('tenant_users')->upsert([
            ['tenant_id' => 'acme', 'user_id' => $owner->id, 'role' => 'owner',  'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 'acme', 'user_id' => $member->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ], ['tenant_id', 'user_id'], ['role', 'updated_at']);

        // Assign subscription
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            Subscription::updateOrCreate(
                ['tenant_id' => 'acme'],
                [
                    'tenant_id'     => 'acme',
                    'plan_id'       => $freePlan->id,
                    'status'        => 'active',
                    'billing_cycle' => 'monthly',
                    'amount'        => 0,
                    'starts_at'     => now(),
                ]
            );
        }

        $this->command->info('');
        $this->command->info('✅ Demo data seeded!');
        $this->command->info('');
        $this->command->info('  Admin: admin@teamcollab.app / password');
        $this->command->info('  Member: john@teamcollab.app / password');
        $this->command->info('  Tenant: acme (acme.localhost)');
        $this->command->info('');
        $this->command->warn('  Run: php artisan tenants:migrate');
        $this->command->warn('  to create the tenant database & default channels');
    }
}
