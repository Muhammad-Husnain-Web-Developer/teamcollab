<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): Response
    {
        $data = $this->analyticsService->getFullAnalytics();

        return Inertia::render('Analytics/Index', [
            'stats'              => $data['stats'],
            'daily_messages'     => $data['daily_messages'],
            'weekly_messages'    => $data['weekly_messages'],
            'channel_activity'   => $data['channel_activity'],
            'member_activity'    => $data['member_activity'],
            'file_uploads'       => $data['file_uploads'],
            'peak_hours'         => $data['peak_hours'],
            'top_channels'       => $data['top_channels'],
            'top_members'        => $data['top_members'],
            'growth'             => $data['growth'],
        ]);
    }
}
