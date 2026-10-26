<?php

namespace App\Console\Commands;

use App\Services\Outreach\Registry\DomainDiscoveryService;
use Illuminate\Console\Command;

class OutreachDiscoverDomainsCommand extends Command
{
    protected $signature = 'outreach:discover-domains
        {--limit=200 : Number of pending registry seed businesses to process}';

    protected $description = 'Generate and evaluate candidate domains for registry seed businesses.';

    public function handle(DomainDiscoveryService $discovery): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $discovery->discover($limit);

        $this->info(sprintf(
            'Domain discovery complete. processed=%d resolved=%d candidates=%d',
            $result['processed'],
            $result['resolved'],
            $result['candidates'],
        ));

        return self::SUCCESS;
    }
}
