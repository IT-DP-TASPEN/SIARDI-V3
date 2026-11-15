<?php

namespace App\Console\Commands;

use App\Models\Archive;
use App\Services\ArchiveFileService;
use Illuminate\Console\Command;

class MigrateLegacyArchiveFilesCommand extends Command
{
    protected $signature = 'siardi:migrate-legacy-archive-files
        {--dry-run : Report what would be migrated without touching files or the database}
        {--chunk=200 : Number of archives processed per query chunk}';

    protected $description = 'Move legacy archive files from the public disk to the private archive disk (idempotent).';

    public function handle(ArchiveFileService $files): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['pending' => 0, 'migrated' => 0, 'missing' => 0, 'failed' => 0, 'skipped' => 0];

        Archive::query()
            ->where('archive_disk', ArchiveFileService::LEGACY_DISK)
            ->chunkById(max((int) $this->option('chunk'), 1), function ($archives) use ($files, $dryRun, &$counts): void {
                foreach ($archives as $archive) {
                    $status = $files->migrateLegacyFile($archive, $dryRun);
                    $counts[$status]++;

                    if (in_array($status, ['missing', 'failed'], true)) {
                        $this->warn("Archive #{$archive->id}: {$status} ({$archive->archive_path})");
                    }
                }
            });

        $this->table(['Status', 'Archives'], collect($counts)->map(fn (int $count, string $status): array => [$status, $count])->values()->all());

        if ($dryRun) {
            $this->info('Dry run: no files or database rows were changed.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
