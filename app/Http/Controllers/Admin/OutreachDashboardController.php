<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachAuditLog;
use App\Models\SendEvent;
use App\Models\SuppressionEntry;
use Illuminate\View\View;

class OutreachDashboardController extends Controller
{
    public function __invoke(): View
    {
        $leadCounts = [
            'eligible' => Lead::query()->where('status', 'eligible')->count(),
            'ineligible' => Lead::query()->where('status', 'ineligible')->count(),
            'unknown' => Lead::query()->where('status', 'unknown')->count(),
            'total' => Lead::query()->count(),
        ];

        $campaignCounts = [
            'draft' => Campaign::query()->where('status', 'draft')->count(),
            'queued' => Campaign::query()->where('status', 'queued')->count(),
            'sending' => Campaign::query()->where('status', 'sending')->count(),
            'completed' => Campaign::query()->where('status', 'completed')->count(),
            'total' => Campaign::query()->count(),
        ];

        $recentEvents = SendEvent::query()->latest('occurred_at')->limit(20)->get();
        $recentSuppressions = SuppressionEntry::query()->latest('noted_at')->limit(20)->get();
        $recentAuditLogs = OutreachAuditLog::query()->latest('occurred_at')->limit(20)->get();

        return view('admin.outreach.dashboard', [
            'leadCounts' => $leadCounts,
            'campaignCounts' => $campaignCounts,
            'recentEvents' => $recentEvents,
            'recentSuppressions' => $recentSuppressions,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}
