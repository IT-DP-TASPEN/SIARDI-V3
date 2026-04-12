<x-filament-widgets::widget>
    <x-filament::section
        heading="Arsip Terbaru"
        description="Daftar arsip terbaru sesuai scope akses user."
        icon="heroicon-o-clock"
    >
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
                            <th>Upload</th>
                            <th>Status Linking</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $archive = $row['archive'];
                                $status = $row['linkage_status'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $archive->archive_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $archive->archive_code ?: 'Tanpa kode' }}</div>
                                </td>
                                <td>
                                    <x-filament::badge color="gray">{{ $archive->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                                </td>
                                <td>
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $archive->branchOffice?->branch_name ?? 'Tanpa cabang' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $archive->branchOffice?->branch_code ?? '--' }}</div>
                                </td>
                                <td>{{ $archive->created_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td>
                                    <x-filament::badge color="{{ $status['color'] }}">{{ $status['label'] }}</x-filament::badge>
                                </td>
                                <td class="text-right">
                                    <x-filament::button color="gray" size="sm" tag="a" :href="$row['view_url']">
                                        Lihat
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
