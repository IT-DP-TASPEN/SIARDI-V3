<?php

namespace App\Support;

use Filament\Pages\BasePage;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

class RbacPermissionMatrix
{
    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            'super_admin',
            'cs',
            'admin_kredit',
            'abm',
            'kearsipan',
            'bm',
            'staff',
            'general_manager',
            'it',
            'it_admin',
            'hc',
            'nonaktif',
        ];
    }

    public static function buildShieldPermissionKey(string $entity, string $affix, string $subject): string
    {
        if (is_subclass_of($entity, Resource::class)) {
            return Str::snake($affix).'_'.static::normalizeResourceSubject($subject);
        }

        if (is_subclass_of($entity, BasePage::class)) {
            return 'page_'.$subject;
        }

        if (is_subclass_of($entity, Widget::class)) {
            return 'widget_'.$subject;
        }

        return Str::snake($affix).'_'.Str::snake($subject);
    }

    /**
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_values(array_unique([
            ...static::resourcePermissions(),
            ...static::pagePermissions(),
            ...static::widgetPermissions(),
        ]));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        $archiveViewer = [
            'view_archive',
            'view_any_archive',
            'view_category',
            'view_any_category',
        ];

        $archiveCreator = [
            ...$archiveViewer,
            'create_archive',
        ];

        return [
            'super_admin' => static::allPermissions(),
            'cs' => $archiveCreator,
            'admin_kredit' => $archiveCreator,
            'abm' => $archiveCreator,
            'kearsipan' => [
                ...$archiveCreator,
                'update_archive',
                'page_RekapanArsip',
                'page_DwhCoverageDashboard',
                'page_LegacyArchiveLinker',
                'page_LegacyInactiveArchives',
                'widget_ArchiveOperationsStatsWidget',
                'widget_ArchiveUploadsTrendWidget',
                'widget_RecentArchivesTableWidget',
                'widget_DwhCoverageStatsWidget',
                'widget_CoverageHotspotsTableWidget',
            ],
            'bm' => $archiveViewer,
            'staff' => [
                ...$archiveViewer,
                'widget_ArchiveOperationsStatsWidget',
                'widget_ArchiveUploadsTrendWidget',
                'widget_RecentArchivesTableWidget',
            ],
            'general_manager' => [
                ...$archiveViewer,
                'widget_ArchiveOperationsStatsWidget',
                'widget_ArchiveUploadsTrendWidget',
                'widget_RecentArchivesTableWidget',
            ],
            'it' => [
                ...$archiveViewer,
                'view_branch::office',
                'view_any_branch::office',
                'page_RekapanArsip',
                'page_DwhCoverageDashboard',
                'widget_ArchiveOperationsStatsWidget',
                'widget_ArchiveUploadsTrendWidget',
                'widget_RecentArchivesTableWidget',
                'widget_DwhCoverageStatsWidget',
                'widget_CoverageHotspotsTableWidget',
            ],
            'it_admin' => [
                ...$archiveCreator,
                'update_archive',
                'page_LegacyArchiveLinker',
                'page_LegacyInactiveArchives',
            ],
            'hc' => [
                ...$archiveCreator,
                'widget_ArchiveOperationsStatsWidget',
                'widget_ArchiveUploadsTrendWidget',
                'widget_RecentArchivesTableWidget',
            ],
            'nonaktif' => [],
        ];
    }

    /**
     * @return list<string>
     */
    public static function resourcePermissions(): array
    {
        $resourceSubjects = [
            'role' => [
                'view',
                'view_any',
                'create',
                'update',
                'delete',
                'delete_any',
            ],
            'archive' => [
                'view',
                'view_any',
                'create',
                'update',
                'restore',
                'restore_any',
                'replicate',
                'reorder',
                'delete',
                'delete_any',
                'force_delete',
                'force_delete_any',
            ],
            'branch::office' => [
                'view',
                'view_any',
                'create',
                'update',
                'restore',
                'restore_any',
                'replicate',
                'reorder',
                'delete',
                'delete_any',
                'force_delete',
                'force_delete_any',
            ],
            'category' => [
                'view',
                'view_any',
                'create',
                'update',
                'restore',
                'restore_any',
                'replicate',
                'reorder',
                'delete',
                'delete_any',
                'force_delete',
                'force_delete_any',
            ],
            'user' => [
                'view',
                'view_any',
                'create',
                'update',
                'restore',
                'restore_any',
                'replicate',
                'reorder',
                'delete',
                'delete_any',
                'force_delete',
                'force_delete_any',
            ],
        ];

        $permissions = [];

        foreach ($resourceSubjects as $subject => $prefixes) {
            foreach ($prefixes as $prefix) {
                $permissions[] = $prefix.'_'.$subject;
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public static function pagePermissions(): array
    {
        return [
            'page_RekapanArsip',
            'page_DwhCoverageDashboard',
            'page_LegacyArchiveLinker',
            'page_LegacyInactiveArchives',
        ];
    }

    /**
     * @return list<string>
     */
    public static function widgetPermissions(): array
    {
        return [
            'widget_ArchiveOperationsStatsWidget',
            'widget_ArchiveUploadsTrendWidget',
            'widget_RecentArchivesTableWidget',
            'widget_DwhCoverageStatsWidget',
            'widget_CoverageHotspotsTableWidget',
        ];
    }

    private static function normalizeResourceSubject(string $subject): string
    {
        return match (Str::snake($subject)) {
            'branch_office' => 'branch::office',
            default => Str::snake($subject),
        };
    }
}
