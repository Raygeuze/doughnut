<?php

namespace App\Console\Commands;

use App\Models\RegistryIngestJob;
use App\Services\Outreach\Registry\RegistryMaterializationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OutreachMaterializeSeedsCommand extends Command
{
    protected $signature = 'outreach:materialize-seeds
        {--job-id=* : Specific ingest job ids to process}
        {--limit=0 : Max rows to read per job (0 means unlimited)}
        {--dry-run : Parse without persisting canonical seed businesses}';

    protected $description = 'Materialize discovered dataset rows into canonical registry seed businesses.';

    public function handle(RegistryMaterializationService $materializer): int
    {
        $jobIds = $this->option('job-id');
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $query = RegistryIngestJob::query()
            ->with('parserProfile')
            ->where('status', 'pending')
            ->orderBy('id');

        if (is_array($jobIds) && count($jobIds) > 0) {
            $query->whereIn('id', array_map('intval', $jobIds));
        }

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->warn('No pending ingest jobs matched your selection.');
            return self::SUCCESS;
        }

        $processed = 0;
        $totalRead = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalRejected = 0;

        foreach ($jobs as $job) {
            try {
                $format = strtoupper((string) ($job->format ?? ''));
                if ($format !== 'CSV') {
                    $job->update([
                        'status' => 'skipped',
                        'error_message' => "Unsupported format for current materializer: {$format}",
                        'finished_at' => CarbonImmutable::now(),
                    ]);
                    $this->warn("Job {$job->id} skipped: unsupported format '{$format}'.");
                    continue;
                }

                $result = $materializer->materializeCsvJob($job, $limit, $dryRun);
                $processed++;
                $totalRead += $result['rows_read'];
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $totalRejected += $result['rejected'];

                $this->line(sprintf(
                    'Job %d complete: read=%d created=%d updated=%d rejected=%d',
                    $job->id,
                    $result['rows_read'],
                    $result['created'],
                    $result['updated'],
                    $result['rejected'],
                ));
            } catch (\Throwable $e) {
                $job->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    'finished_at' => CarbonImmutable::now(),
                ]);

                $this->error("Job {$job->id} failed: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            'Materialization done. jobs_processed=%d rows_read=%d created=%d updated=%d rejected=%d %s',
            $processed,
            $totalRead,
            $totalCreated,
            $totalUpdated,
            $totalRejected,
            $dryRun ? '(dry run only)' : '',
        ));

        return self::SUCCESS;
    }
}
