<x-filament-panels::page>
    @php
        $totalBranches = $rekap->count();
        $totalKredit = $rekap->sum('kredit');
        $totalDeposito = $rekap->sum('deposito');
        $totalTabungan = $rekap->sum('tabungan');
        $overallTotal = $rekap->sum('total');
    @endphp

    <div class="siardi-page-stack" id="rekap-arsip">
        <x-filament::section
            heading="Archive Recap"
            description="Ringkasan arsip legacy per cabang untuk kategori kredit, deposito, dan tabungan."
            icon="heroicon-o-presentation-chart-line"
        >
            <div class="siardi-stat-grid">
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Cabang Termonitor</p>
                    <p class="siardi-stat-value">{{ number_format($totalBranches) }}</p>
                    <p class="siardi-stat-meta">Cabang operasional aktif di recap legacy.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Total Kredit</p>
                    <p class="siardi-stat-value">{{ number_format($totalKredit) }}</p>
                    <p class="siardi-stat-meta">Arsip kategori kredit.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Total Deposito</p>
                    <p class="siardi-stat-value">{{ number_format($totalDeposito) }}</p>
                    <p class="siardi-stat-meta">Arsip kategori bilyet deposito.</p>
                </div>
                <div class="siardi-stat-card">
                    <p class="siardi-stat-label">Total Arsip</p>
                    <p class="siardi-stat-value">{{ number_format($overallTotal) }}</p>
                    <p class="siardi-stat-meta">Gabungan tabungan, deposito, dan kredit.</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Laporan Kinerja Aplikasi SIARDI"
            description="Format laporan dipertahankan agar tetap familiar untuk review operasional dan kebutuhan print."
            icon="heroicon-o-table-cells"
        >
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge color="gray">{{ number_format($totalBranches) }} Cabang</x-filament::badge>
                    <x-filament::badge color="primary">{{ number_format($totalTabungan) }} Tabungan</x-filament::badge>
                    <x-filament::badge color="warning">{{ number_format($totalDeposito) }} Deposito</x-filament::badge>
                    <x-filament::badge color="success">{{ number_format($overallTotal) }} Total Arsip</x-filament::badge>
                </div>
            </x-slot>

            <div class="siardi-report-table-shell">
                <table class="siardi-report-table">
                    <thead>
                        <tr>
                            <th class="siardi-report-title"></th>
                            <th class="siardi-report-title" colspan="{{ count($rekap->keys()) * 3 + 1 }}">
                                LAPORAN KINERJA APLIKASI SIARDI
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td rowspan="3" class="bg-white align-middle dark:bg-gray-900">
                                <img src="{{ asset('logo.png') }}" alt="Bank DP Taspen" class="mx-auto w-28">
                            </td>
                            @foreach ($rekap->keys() as $key)
                                <td class="siardi-report-head" colspan="3">{{ $key }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($rekap->keys() as $branchName)
                                @foreach (['Kredit', 'Deposito', 'Tabungan'] as $kategori)
                                    <td class="siardi-report-subhead">{{ $kategori }}</td>
                                @endforeach
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($rekap as $branch => $data)
                                <td class="siardi-report-value">{{ number_format($data['kredit']) }}</td>
                                <td class="siardi-report-value">{{ number_format($data['deposito']) }}</td>
                                <td class="siardi-report-value">{{ number_format($data['tabungan']) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="siardi-report-total-label">Total Keseluruhan</td>
                            @foreach ($rekap as $branch => $data)
                                <td class="siardi-report-total" colspan="3">{{ number_format($data['total']) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="siardi-signature">
                    <p>{{ auth()->user()?->branchOffice?->branch_name ?? 'SIARDI' }}, {{ \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y') }}</p>
                    <p class="text-right">Mengetahui,</p>
                    <p class="siardi-signature-name">{{ auth()->user()?->name ?? '-' }}</p>
                    <p class="siardi-signature-role">{{ \Illuminate\Support\Str::of(auth()->user()?->primaryRoleName() ?? 'user')->replace('_', ' ')->squish()->upper() }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
