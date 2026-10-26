<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

class OutreachCreateCampaignCommand extends Command
{
    protected $signature = 'outreach:create-campaign {name} {subject} {body}';

    protected $description = 'Create a fraud-warning campaign template.';

    public function handle(): int
    {
        $campaign = Campaign::query()->create([
            'name' => (string) $this->argument('name'),
            'purpose' => 'fraud_warning',
            'subject_template' => (string) $this->argument('subject'),
            'body_template' => (string) $this->argument('body'),
            'status' => 'draft',
        ]);

        $this->info("Campaign created with id={$campaign->id}");

        return self::SUCCESS;
    }
}
