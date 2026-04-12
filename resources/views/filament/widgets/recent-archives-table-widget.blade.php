<x-filament-widgets::widget>
    <x-filament::section
        heading="Arsip Terbaru"
        description="Daftar arsip terbaru yang terlihat oleh user ini."
        icon="heroicon-o-clock"
    >
        <x-slot name="afterHeader">
            <x-filament::button color="gray" size="sm" tag="a" :href="$archiveIndexUrl">
                Semua Arsip
            </x-filament::button>
        </x-slot>

        @if ($rows->isEmpty())
            <div class="siardi-empty">Belum ada arsip dalam scope user ini.</div>
        @else
            <div class="siardi-table-shell">
                <table class="siardi-table">
                    <thead>
                        <tr>
                            <th>Nama Arsip</th>
                            <th>Kategori</th>
                            <th>Cabang</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ $row['view_url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row['archive']->archive_name }}
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['archive']->archive_code ?: 'Tanpa kode arsip' }}
                                    </div>
                                </td>
                                <td>
                                    <x-filament::badge color="gray">{{ $row['archive']->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                                </td>
                                <td>{{ $row['archive']->branchOffice?->branch_name ?? '-' }}</td>
                                <td>
                                    <div>{{ \App\Filament\Resources\ArchiveResource::formatDateValue($row['archive']->archive_date) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['archive']->created_at?->format('d M Y H:i') }}</div>
                                </td>
                                <td>
                                    <x-filament::badge color="{{ $row['linkage_status']['color'] }}">
                                        {{ $row['linkage_status']['label'] }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
