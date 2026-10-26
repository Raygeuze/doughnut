<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Outreach\CampaignOrchestrator;
use Illuminate\Console\Command;

class OutreachBuildQueueCommand extends Command
{
    protected $signature = 'outreach:build-queue {campaign_id}';

    protected $description = 'Build recipient queue for a campaign with one-address-per-domain enforcement.';

    public function handle(CampaignOrchestrator $orchestrator): int
    {
        $campaign = Campaign::query()->find((int) $this->argument('campaign_id'));

        if (! $campaign) {
            $this->error('Campaign not found.');
            return self::FAILURE;
        }

        $result = $orchestrator->buildQueue($campaign);

        $this->info(sprintf('Queue built. queued=%d skipped=%d', $result['queued'], $result['skipped']));

        return self::SUCCESS;
    }
}
