<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'             => 'Free',
                'slug'             => 'free',
                'description'      => 'Perfect for small teams getting started.',
                'price_monthly'    => 0,
                'price_yearly'     => 0,
                'currency'         => 'USD',
                'max_members'      => 10,
                'max_channels'     => 5,
                'storage_limit'    => 5 * 1024 * 1024 * 1024, // 5GB
                'has_file_sharing' => true,
                'has_video_calls'  => false,
                'has_analytics'    => false,
                'has_custom_roles' => false,
                'is_active'        => true,
                'sort_order'       => 1,
                'features'         => [
                    'Up to 10 members',
                    'Up to 5 channels',
                    '5GB storage',
                    'File sharing',
                    'Realtime messaging',
                    'Mobile app',
                ],
            ],
            [
                'name'             => 'Pro',
                'slug'             => 'pro',
                'description'      => 'For growing teams who need more power.',
                'price_monthly'    => 9.99,
                'price_yearly'     => 99.99,
                'currency'         => 'USD',
                'max_members'      => -1, // unlimited
                'max_channels'     => -1, // unlimited
                'storage_limit'    => 50 * 1024 * 1024 * 1024, // 50GB
                'has_file_sharing' => true,
                'has_video_calls'  => true,
                'has_analytics'    => true,
                'has_custom_roles' => false,
                'is_active'        => true,
                'sort_order'       => 2,
                'features'         => [
                    'Unlimited members',
                    'Unlimited channels',
                    '50GB storage',
                    'Video calls',
                    'Advanced analytics',
                    'Priority support',
                    'Message history',
                ],
            ],
            [
                'name'             => 'Enterprise',
                'slug'             => 'enterprise',
                'description'      => 'For large organisations with custom needs.',
                'price_monthly'    => 29.99,
                'price_yearly'     => 299.99,
                'currency'         => 'USD',
                'max_members'      => -1,
                'max_channels'     => -1,
                'storage_limit'    => 500 * 1024 * 1024 * 1024, // 500GB
                'has_file_sharing' => true,
                'has_video_calls'  => true,
                'has_analytics'    => true,
                'has_custom_roles' => true,
                'is_active'        => true,
                'sort_order'       => 3,
                'features'         => [
                    'Everything in Pro',
                    '500GB storage',
                    'Custom roles & permissions',
                    'SSO / SAML',
                    'Audit logs',
                    'SLA support',
                    'Custom integrations',
                    'Dedicated manager',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Plans seeded: Free, Pro, Enterprise');
    }
}
