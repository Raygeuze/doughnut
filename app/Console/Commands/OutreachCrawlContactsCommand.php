<?php

namespace App\Console\Commands;

use App\Services\Outreach\Registry\PublicContactCrawlerService;
use Illuminate\Console\Command;

class OutreachCrawlContactsCommand extends Command
{
    protected $signature = 'outreach:crawl-contacts
        {--limit=50 : Number of resolved domains to crawl}';

    protected $description = 'Crawl public contact pages and ingest discovered emails into leads.';

    public function handle(PublicContactCrawlerService $crawler): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $crawler->crawl($limit);

        $this->info(sprintf(
            'Crawl complete. domains=%d pages=%d emails=%d leads_touched=%d',
            $result['domains'],
            $result['pages'],
            $result['emails'],
            $result['leads'],
        ));

        return self::SUCCESS;
    }
}
