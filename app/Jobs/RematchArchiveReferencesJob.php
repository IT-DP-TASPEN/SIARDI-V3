<?php

namespace App\Jobs;

use App\Models\Archive;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RematchArchiveReferencesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $archiveId,
    ) {}

    public function handle(ArchiveBusinessReferenceService $service): void
    {
        $archive = Archive::query()
            ->with([
                'branchOffice.dwhMapping',
                'businessReferences.categoryReferenceField.category',
            ])
            ->find($this->archiveId);

        if (! $archive) {
            return;
        }

        $service->rematchArchive($archive);
    }
}
