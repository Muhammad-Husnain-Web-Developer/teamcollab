<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): Response
    {
        $stats = $this->analyticsService->getDashboardStats();

        $dailyMessages = $this->analyticsService->getDailyMessages(30);

        $channelActivity = $this->analyticsService->getChannelActivity();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_members'      => $stats['total_members'],
                'active_users'       => $stats['active_users'],
                'total_messages'     => $stats['total_messages'],
                'messages_today'     => $stats['messages_today'],
                'total_channels'     => $stats['total_channels'],
                'storage_used_human' => $stats['storage_used_human'],
            ],
            'daily_messages'   => $dailyMessages,
            'channel_activity' => $channelActivity,
        ]);
    }
}
