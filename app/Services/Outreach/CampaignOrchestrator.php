<?php

namespace App\Services\Outreach;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignOrchestrator
{
    public function __construct(
        private readonly DomainWinnerService $winnerService,
        private readonly SuppressionService $suppressionService,
        private readonly RenewalWindowService $renewalWindow,
        private readonly OutreachSettings $settings,
        private readonly EventLogger $events,
    ) {
    }

    public function buildQueue(Campaign $campaign): array
    {
        $campaign->update(['status' => 'queued']);

        $queued = 0;
        $skipped = 0;

        /** @var Collection<string, Collection<int, Lead>> $grouped */
        $grouped = Lead::query()->eligible()->get()->groupBy('email_domain');

        DB::transaction(function () use ($grouped, $campaign, &$queued, &$skipped): void {
            foreach ($grouped as $domain => $domainLeads) {
                $selection = $this->winnerService->pickWinner($domainLeads);
                $winner = $selection['winner'];
                $scores = $selection['scores'];

                if (! $winner) {
                    continue;
                }

                foreach ($domainLeads as $lead) {
                    if ((int) $lead->id !== (int) $winner->id) {
                        $this->recordSkippedRecipient(
                            $campaign,
                            $lead,
                            'domain_primary_not_selected',
                            ['scores' => $scores, 'winner' => $winner->email],
                        );
                        $skipped++;
                        continue;
                    }

                    $blockReason = $this->blockReason($lead, $campaign);
                    if ($blockReason !== null) {
                        $this->recordSkippedRecipient($campaign, $lead, $blockReason, ['scores' => $scores]);
                        $skipped++;
                        continue;
                    }

                    CampaignRecipient::query()->updateOrCreate(
                        [
                            'campaign_id' => $campaign->id,
                            'lead_id' => $lead->id,
                        ],
                        [
                            'recipient_email_snapshot' => $lead->email,
                            'email_domain' => $domain,
                            'eligibility_snapshot' => $lead->status,
                            'is_domain_winner' => true,
                            'queued_at' => CarbonImmutable::now(),
                            'skipped_at' => null,
                            'skip_reason' => null,
                        ],
                    );

                    $this->events->log('queued', $lead->id, $campaign->id, null, [
                        'scores' => $scores,
                        'winner' => $winner->email,
                    ]);
                    $queued++;
                }
            }
        });

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public function blockReason(Lead $lead, Campaign $campaign): ?string
    {
        if ($lead->status !== 'eligible') {
            return 'ineligible_at_send_time';
        }

        if ($this->suppressionService->isSuppressed($lead->email, $lead->email_domain)) {
            return 'suppressed';
        }

        $cooldownDays = $this->settings->getInt(
            'outreach_cooldown_days',
            (int) config('outreach.cooldown_days', 30),
        );

        if ($lead->last_sent_at && $lead->last_sent_at->gt(now()->subDays($cooldownDays))) {
            return 'cooldown_active';
        }

        if ($campaign->enforce_renewal_window) {
            $renewalReason = $this->renewalWindow->blockReason($lead);
            if ($renewalReason !== null) {
                return $renewalReason;
            }
        }

        $alreadyInCampaign = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('lead_id', $lead->id)
            ->whereNotNull('sent_at')
            ->exists();

        if ($alreadyInCampaign) {
            return 'duplicate_in_campaign';
        }

        return null;
    }

    private function recordSkippedRecipient(Campaign $campaign, Lead $lead, string $reason, array $metadata = []): void
    {
        CampaignRecipient::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'lead_id' => $lead->id,
            ],
            [
                'recipient_email_snapshot' => $lead->email,
                'email_domain' => $lead->email_domain,
                'eligibility_snapshot' => $lead->status,
                'is_domain_winner' => false,
                'skipped_at' => CarbonImmutable::now(),
                'skip_reason' => $reason,
            ],
        );

        $this->events->log('blocked', $lead->id, $campaign->id, $reason, $metadata);
    }
}
