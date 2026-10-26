<?php

namespace App\Services\Outreach;

use App\Mail\FraudWarningMailable;
use App\Models\Campaign;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class CampaignSendService
{
    public function __construct(
        private readonly CampaignOrchestrator $orchestrator,
        private readonly EventLogger $events,
    ) {
    }

    /**
     * @return array{sent:int,blocked:int}
     */
    public function send(Campaign $campaign): array
    {
        $campaign->update(['status' => 'sending']);

        $sent = 0;
        $blocked = 0;

        $campaign->recipients()
            ->whereNotNull('queued_at')
            ->whereNull('sent_at')
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use (&$sent, &$blocked, $campaign): void {
                foreach ($recipients as $recipient) {
                    $lead = $recipient->lead;
                    if (! $lead) {
                        continue;
                    }

                    $blockReason = $this->orchestrator->blockReason($lead, $campaign);
                    if ($blockReason !== null) {
                        $recipient->update([
                            'skipped_at' => CarbonImmutable::now(),
                            'skip_reason' => $blockReason,
                        ]);
                        $this->events->log('blocked', $lead->id, $campaign->id, $blockReason);
                        $blocked++;
                        continue;
                    }

                    Mail::to($recipient->recipient_email_snapshot)
                        ->send(new FraudWarningMailable($campaign, $lead));

                    $recipient->update([
                        'sent_at' => CarbonImmutable::now(),
                    ]);

                    $lead->update([
                        'last_sent_at' => CarbonImmutable::now(),
                    ]);

                    $this->events->log('sent', $lead->id, $campaign->id, null, [
                        'email' => $recipient->recipient_email_snapshot,
                    ]);
                    $sent++;
                }
            });

        $campaign->update(['status' => 'completed']);

        return ['sent' => $sent, 'blocked' => $blocked];
    }
}
