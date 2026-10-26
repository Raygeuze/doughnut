<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Outreach\CampaignSendService;
use Illuminate\Console\Command;

class OutreachSendCampaignCommand extends Command
{
    protected $signature = 'outreach:send-campaign {campaign_id}';

    protected $description = 'Send queued recipients for a campaign with final compliance gate checks.';

    public function handle(CampaignSendService $sender): int
    {
        $campaign = Campaign::query()->find((int) $this->argument('campaign_id'));

        if (! $campaign) {
            $this->error('Campaign not found.');
            return self::FAILURE;
        }

        $result = $sender->send($campaign);

        $this->info(sprintf('Campaign complete. sent=%d blocked=%d', $result['sent'], $result['blocked']));

        return self::SUCCESS;
    }
}
