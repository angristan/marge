<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GetDashboardStats;
use App\Actions\Admin\UpdateSettings;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show dashboard.
     */
    public function __invoke(): Response
    {
        $stats = GetDashboardStats::run();
        $settings = UpdateSettings::getAll();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'siteName' => $settings['site_name'],
            'siteUrl' => $settings['site_url'],
        ]);
    }
}
