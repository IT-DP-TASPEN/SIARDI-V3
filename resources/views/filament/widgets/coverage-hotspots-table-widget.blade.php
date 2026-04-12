<x-filament-widgets::widget>
    <x-filament::section
        heading="Realisasi Terendah"
        description="Cabang dan kategori dengan backlog missing paling besar."
        icon="heroicon-o-fire"
    >
        <x-slot name="afterHeader">
            <x-filament::button color="gray" size="sm" tag="a" :href="$coverageUrl">
                Buka Detail
            </x-filament::button>
        </x-slot>

        @if (! $overview['available'])
            <div class="rounded-2xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
                {{ $overview['message'] }}
            </div>
        @elseif ($rows->isEmpty())
            <div class="siardi-empty">Belum ada hotspot realisasi dalam scope user ini.</div>
        @else
            <div class="siardi-table-shell">
                <table class="siardi-table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Cabang</th>
                            <th class="text-right">Target</th>
                            <th class="text-right">Realisasi</th>
                            <th class="text-right">Missing</th>
                            <th class="text-right">Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $coverageColor = match (true) {
                                    $row['coverage_percentage'] >= 95 => 'success',
                                    $row['coverage_percentage'] >= 75 => 'warning',
                                    default => 'danger',
                                };
                            @endphp
                            <tr>
                                <td><x-filament::badge color="gray">{{ $row['category_name'] }}</x-filament::badge></td>
                                <td>
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['branch_name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['dwh_location_code'] }}</div>
                                </td>
                                <td class="text-right">{{ number_format($row['target_count']) }}</td>
                                <td class="text-right">{{ number_format($row['realized_count']) }}</td>
                                <td class="text-right">
                                    <x-filament::badge color="{{ $row['missing_count'] > 0 ? 'warning' : 'success' }}">
                                        {{ number_format($row['missing_count']) }}
                                    </x-filament::badge>
                                </td>
                                <td class="text-right">
                                    <x-filament::badge color="{{ $coverageColor }}">
                                        {{ number_format($row['coverage_percentage'], 2) }}%
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
