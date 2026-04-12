<x-filament-panels::page>
    @php
        $summaryCollection = collect($summaryRows);
        $targetTotal = $summaryCollection->sum('target_count');
        $realizedTotal = $summaryCollection->sum('realized_count');
        $missingTotal = $summaryCollection->sum('missing_count');
        $coverageTotal = $targetTotal > 0 ? round(($realizedTotal / $targetTotal) * 100, 2) : 0;
    @endphp

    <div class="siardi-page-stack">
        <x-filament::section
            heading="Filter Target & Realisasi"
            description="Gunakan filter kategori dan cabang untuk memeriksa target dan realisasi arsip terhadap data target harian."
            icon="heroicon-o-funnel"
        >
            <x-slot name="afterHeader">
                <x-filament::button color="gray" outlined wire:click="clearDrilldown">
                    Clear Drilldown
                </x-filament::button>
            </x-slot>

            <div class="siardi-filter-grid">
                <label class="siardi-filter-field">
                    <span class="siardi-filter-label">Kategori</span>
                    <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('select')?.focus()">
                        <select wire:model.live="selectedCategoryId" class="fi-select-input">
                            <option value="">Semua kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </label>

                <label class="siardi-filter-field">
                    <span class="siardi-filter-label">Cabang</span>
                    <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('select')?.focus()">
                        <select wire:model.live="selectedBranchOfficeId" class="fi-select-input">
                            <option value="">Semua cabang</option>
                            @foreach ($branchMappings as $branchMapping)
                                <option value="{{ $branchMapping->branch_office_id }}">
                                    {{ $branchMapping->siardi_branch_code }} - {{ $branchMapping->branchOffice?->branch_name }}
                                </option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Ringkasan Target & Realisasi"
            description="Target dihitung dari seluruh distinct business record per cabang dan kategori, realisasi dihitung dari distinct business record yang sudah tertaut di SIARDI."
            icon="heroicon-o-chart-bar-square"
        >
            <div class="siardi-stat-grid">
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Target</p>
                    <p class="siardi-stat-value">{{ number_format($targetTotal) }}</p>
                    <p class="siardi-stat-meta">Total record target.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Realisasi</p>
                    <p class="siardi-stat-value">{{ number_format($realizedTotal) }}</p>
                    <p class="siardi-stat-meta">Distinct record yang sudah tertaut di SIARDI.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Missing</p>
                    <p class="siardi-stat-value">{{ number_format($missingTotal) }}</p>
                    <p class="siardi-stat-meta">Target yang belum memiliki arsip terhubung.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Coverage</p>
                    <p class="siardi-stat-value">{{ number_format($coverageTotal, 2) }}%</p>
                    <p class="siardi-stat-meta">Persentase realized terhadap target.</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Rekap Per Cabang"
            description="Rincian target, realisasi, dan missing untuk tiap kombinasi kategori dan cabang."
            icon="heroicon-o-table-cells"
        >
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
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summaryRows as $row)
                            @php
                                $coverageColor = match (true) {
                                    $row['coverage_percentage'] >= 95 => 'success',
                                    $row['coverage_percentage'] >= 75 => 'warning',
                                    default => 'danger',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <x-filament::badge color="gray">{{ $row['category_name'] }}</x-filament::badge>
                                </td>
                                <td>
                                    <div class="space-y-1">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $row['branch_name'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['dwh_location_code'] }}</div>
                                    </div>
                                </td>
                                <td class="text-right">{{ number_format($row['target_count']) }}</td>
                                <td class="text-right">{{ number_format($row['realized_count']) }}</td>
                                <td class="text-right">
                                    <x-filament::badge color="{{ $row['missing_count'] === 0 ? 'success' : 'warning' }}">
                                        {{ number_format($row['missing_count']) }}
                                    </x-filament::badge>
                                </td>
                                <td class="text-right">
                                    <x-filament::badge color="{{ $coverageColor }}">
                                        {{ number_format($row['coverage_percentage'], 2) }}%
                                    </x-filament::badge>
                                </td>
                                <td class="text-right">
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        outlined
                                        size="sm"
                                        wire:click="openDrilldown({{ $row['category_id'] }}, {{ $row['branch_office_id'] }})"
                                    >
                                        Lihat Detail
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="siardi-empty">Belum ada data target dan realisasi.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @if ($drilldownCategoryId && $drilldownBranchOfficeId)
            <x-filament::section
                heading="Data Belum Tertaut"
                description="Daftar record target yang masuk hitungan tetapi belum memiliki referensi arsip terhubung di SIARDI."
                icon="heroicon-o-exclamation-triangle"
            >
                @if ($missingRows->isNotEmpty())
                    <div class="siardi-table-shell">
                        <table class="siardi-table">
                            <thead>
                                <tr>
                                    <th>Source Key</th>
                                    <th>Branch</th>
                                    <th>CIF</th>
                                    <th>Business Key</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($missingRows as $row)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $row['source_key'] }}</td>
                                        <td>{{ $row['branch_code'] }}</td>
                                        <td class="font-mono text-xs">{{ $row['cif'] }}</td>
                                        <td class="font-mono text-xs">{{ $row['business_key'] }}</td>
                                        <td>
                                            <x-filament::badge color="gray">{{ $row['status'] ?: 'Unknown' }}</x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="siardi-empty">Tidak ada data yang belum tertaut untuk filter yang dipilih.</div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
