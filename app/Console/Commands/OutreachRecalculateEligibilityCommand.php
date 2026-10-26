<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Outreach\EligibilityService;
use Illuminate\Console\Command;

class OutreachRecalculateEligibilityCommand extends Command
{
    protected $signature = 'outreach:recalculate-eligibility {--lead_id=}';

    protected $description = 'Recalculate lead eligibility based on current policy and evidence.';

    public function handle(EligibilityService $eligibility): int
    {
        $query = Lead::query();

        if ($this->option('lead_id')) {
            $query->whereKey((int) $this->option('lead_id'));
        }

        $count = 0;

        $query->chunkById(200, function ($leads) use (&$count, $eligibility): void {
            foreach ($leads as $lead) {
                $result = $eligibility->evaluateLead($lead);
                $count++;
                $this->line(sprintf(
                    'Lead %d %s => %s [%s]',
                    $lead->id,
                    $lead->email,
                    $result['status'],
                    implode(',', $result['reasons']),
                ));
            }
        });

        $this->info("Recalculated {$count} lead(s).");

        return self::SUCCESS;
    }
}
