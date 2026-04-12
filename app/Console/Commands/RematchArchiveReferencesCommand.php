<?php

namespace App\Console\Commands;

use App\Jobs\RematchArchiveReferencesJob;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Console\Command;

class RematchArchiveReferencesCommand extends Command
{
    protected $signature = 'siardi:rematch-references
        {--force : Rematch all supported archives with business references}
        {--chunk=200 : Number of archive ids processed per query chunk}';

    protected $description = 'Dispatch rematch jobs for archive business references.';

    public function handle(ArchiveBusinessReferenceService $service): int
    {
        $force = (bool) $this->option('force');
        $chunkSize = max((int) $this->option('chunk'), 1);
        $queuedJobs = 0;

        $service->rematchableArchivesQuery($force)
            ->orderBy('archives.id')
            ->chunkById($chunkSize, function ($archives) use (&$queuedJobs): void {
                foreach ($archives as $archive) {
                    RematchArchiveReferencesJob::dispatch((int) $archive->id);
                    $queuedJobs++;
                }
            }, 'archives.id', 'id');

        $this->info("Queued {$queuedJobs} rematch job(s).");

        return self::SUCCESS;
    }
}
