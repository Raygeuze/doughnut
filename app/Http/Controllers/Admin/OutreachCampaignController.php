<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Outreach\CampaignOrchestrator;
use App\Services\Outreach\CampaignSendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::query()->latest('id')->withCount('recipients')->paginate(20);

        return view('admin.outreach.campaigns', [
            'campaigns' => $campaigns,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject_template' => ['required', 'string', 'max:255'],
            'body_template' => ['required', 'string'],
            'enforce_renewal_window' => ['nullable', 'boolean'],
        ]);

        Campaign::query()->create([
            'name' => $validated['name'],
            'purpose' => 'fraud_warning',
            'enforce_renewal_window' => (bool) ($validated['enforce_renewal_window'] ?? true),
            'subject_template' => $validated['subject_template'],
            'body_template' => $validated['body_template'],
            'status' => 'draft',
        ]);

        return redirect()
            ->route('admin.outreach.campaigns.index')
            ->with('status', 'Campaign created.');
    }

    public function updateTimingPolicy(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'enforce_renewal_window' => ['nullable', 'boolean'],
        ]);

        $campaign->update([
            'enforce_renewal_window' => (bool) ($validated['enforce_renewal_window'] ?? false),
        ]);

        return redirect()
            ->route('admin.outreach.campaigns.show', $campaign)
            ->with('status', $campaign->enforce_renewal_window
                ? 'Renewal window timing is enabled for this campaign.'
                : 'Renewal window timing is disabled for this campaign.');
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load(['recipients.lead']);

        return view('admin.outreach.campaign-show', [
            'campaign' => $campaign,
        ]);
    }

    public function buildQueue(Campaign $campaign, CampaignOrchestrator $orchestrator): RedirectResponse
    {
        $result = $orchestrator->buildQueue($campaign);

        return redirect()
            ->route('admin.outreach.campaigns.show', $campaign)
            ->with('status', "Queue built: queued={$result['queued']} skipped={$result['skipped']}.");
    }

    public function send(Campaign $campaign, CampaignSendService $sender): RedirectResponse
    {
        $result = $sender->send($campaign);

        return redirect()
            ->route('admin.outreach.campaigns.show', $campaign)
            ->with('status', "Campaign sent: sent={$result['sent']} blocked={$result['blocked']}.");
    }
}
