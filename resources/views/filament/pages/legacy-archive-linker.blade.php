<x-filament-panels::page>
    <div
        class="siardi-page-stack"
        x-data
        x-on:legacy-linking-archive-selected.window="
            if (window.matchMedia('(max-width: 1279px)').matches) {
                $nextTick(() => document.getElementById('legacy-linking-workspace')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
            }
        "
    >
        <x-filament::section
            heading="Filter Arsip Legacy"
            description="Kerjakan arsip lama yang belum memiliki referensi bisnis tanpa mengubah perilaku legacy yang sudah ada."
            icon="heroicon-o-funnel"
        >
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge color="gray">{{ $archives->count() }} Arsip Tampil</x-filament::badge>
                    @if ($selectedArchive)
                        <x-filament::badge color="primary">1 Arsip Dipilih</x-filament::badge>
                    @endif
                    @if (! empty($configurationGaps))
                        <x-filament::badge color="warning">{{ count($configurationGaps) }} Kategori Belum Terkonfigurasi</x-filament::badge>
                    @endif
                </div>
            </x-slot>

            <div class="space-y-4">
                @if (! empty($configurationGaps))
                    <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                        Konfigurasi referensi bisnis belum aktif untuk: <strong>{{ implode(', ', $configurationGaps) }}</strong>.
                        Jalankan <code>php artisan db:seed --class=BusinessReferenceConfigurationSeeder --force</code> di environment target agar field linking untuk kategori ini muncul.
                    </div>
                @endif

                <div class="siardi-filter-grid-5">
                    <label class="siardi-filter-field">
                        <span class="siardi-filter-label">Cari</span>
                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                            <input type="text" wire:model.live.debounce.400ms="search" class="fi-input" placeholder="Nama, kode, filename">
                        </x-filament::input.wrapper>
                    </label>

                    <label class="siardi-filter-field">
                        <span class="siardi-filter-label">Kategori</span>
                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('select')?.focus()">
                            <select wire:model.live="categoryId" class="fi-select-input">
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
                            <select wire:model.live="branchOfficeId" class="fi-select-input">
                                <option value="">Semua cabang</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->branch_code }} - {{ $branch->branch_name }}</option>
                                @endforeach
                            </select>
                        </x-filament::input.wrapper>
                    </label>

                    <label class="siardi-filter-field">
                        <span class="siardi-filter-label">Upload Dari</span>
                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                            <input type="date" wire:model.live="uploadedFrom" class="fi-input">
                        </x-filament::input.wrapper>
                    </label>

                    <label class="siardi-filter-field">
                        <span class="siardi-filter-label">Upload Sampai</span>
                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                            <input type="date" wire:model.live="uploadedTo" class="fi-input">
                        </x-filament::input.wrapper>
                    </label>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(23rem,1fr)]">
            <x-filament::section
                heading="Arsip Legacy Belum Tertaut"
                description="Antrian kerja untuk arsip existing yang masih belum memiliki business reference."
                icon="heroicon-o-document-magnifying-glass"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge color="warning">Maksimal 50 arsip per hasil filter</x-filament::badge>
                </x-slot>

                <div class="siardi-table-shell">
                    <table class="siardi-table">
                        <thead>
                            <tr>
                                <th>Waktu Upload</th>
                                <th>Kategori</th>
                                <th>Cabang</th>
                                <th>Kode Arsip</th>
                                <th>Nama Arsip</th>
                                <th>File</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($archives as $archive)
                                @php
                                    $isSelected = $selectedArchiveId === $archive->id;
                                @endphp
                                <tr @class([
                                    'bg-primary-50/80 dark:bg-primary-500/10' => $isSelected,
                                ])>
                                    <td>{{ $archive->created_at?->format('d M Y H:i') }}</td>
                                    <td>
                                        <x-filament::badge color="gray">{{ $archive->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                                    </td>
                                    <td>
                                        <x-filament::badge color="primary">
                                            {{ $archive->branchOffice?->branch_code ?? '--' }} - {{ $archive->branchOffice?->branch_name ?? 'Tanpa cabang' }}
                                        </x-filament::badge>
                                    </td>
                                    <td>{{ $archive->archive_code ?: '-' }}</td>
                                    <td class="font-medium text-gray-950 dark:text-white">{{ $archive->archive_name }}</td>
                                    <td class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $archive->archive_path }}</td>
                                    <td class="text-right">
                                        <x-filament::button type="button" color="{{ $isSelected ? 'gray' : 'primary' }}" size="sm" wire:click="selectArchive({{ $archive->id }})">
                                            {{ $isSelected ? 'Dipilih' : 'Link References' }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="siardi-empty">Tidak ada arsip legacy yang cocok.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <div id="legacy-linking-workspace" class="space-y-6 xl:sticky xl:top-24">
                <x-filament::section
                    heading="Workspace Linking"
                    description="Pilih satu arsip dari antrian kiri, lalu isi atau cari referensi bisnis yang paling tepat."
                    icon="heroicon-o-link"
                >
                    <x-slot name="afterHeader">
                        @if ($selectedArchive)
                            <div class="flex flex-wrap gap-2">
                                <x-filament::badge color="primary">{{ $selectedArchive->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                                <x-filament::badge color="gray">{{ $selectedArchive->branchOffice?->branch_name ?? 'Tanpa cabang' }}</x-filament::badge>
                                <x-filament::badge color="gray">{{ $selectedArchive->created_at?->format('d M Y H:i') ?? '-' }}</x-filament::badge>
                            </div>
                        @endif
                    </x-slot>

                    @if (! $selectedArchive)
                        <div class="siardi-empty">
                            Pilih arsip dari antrian kiri untuk mulai melakukan linking referensi bisnis.
                        </div>
                    @else
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $selectedArchive->archive_name }}</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $selectedArchive->archive_code ?: 'Tanpa kode arsip' }} | {{ $selectedArchive->archive_path }}
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <x-filament::badge color="gray">{{ $selectedArchive->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                                            <x-filament::badge color="primary">
                                                {{ $selectedArchive->branchOffice?->branch_code ?? '--' }} - {{ $selectedArchive->branchOffice?->branch_name ?? 'Tanpa cabang' }}
                                            </x-filament::badge>
                                            @if ($selectedArchive->branchOffice?->dwhMapping?->is_active)
                                                <x-filament::badge color="success">
                                                    DWH {{ $selectedArchive->branchOffice->dwhMapping->dwh_location_code }}
                                                </x-filament::badge>
                                            @else
                                                <x-filament::badge color="warning">Tanpa Mapping Cabang Aktif</x-filament::badge>
                                            @endif
                                        </div>
                                    </div>

                                    <x-filament::button type="button" color="gray" wire:click="clearSelection">
                                        Reset
                                    </x-filament::button>
                                </div>
                            </div>

                            @if ($referenceFields->isEmpty())
                                <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                                    Kategori ini belum memiliki konfigurasi referensi bisnis. Arsip tetap aman sebagai legacy document, tetapi belum bisa dilink ke target rekonsiliasi sampai konfigurasi field diaktifkan.
                                </div>
                            @else
                                @if (! $selectedArchive->branchOffice?->dwhMapping?->is_active)
                                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                                        Cabang arsip ini belum punya mapping DWH aktif. Lookup kandidat tetap bisa dijalankan, tetapi tidak akan dibatasi ke satu cabang DWH tertentu.
                                    </div>
                                @endif

                                <div class="grid gap-4">
                                    @foreach ($referenceFields as $field)
                                        <label class="siardi-filter-field">
                                            <span class="siardi-filter-label">
                                                {{ $field->label }}
                                                @if ($field->is_primary_match_key)
                                                    <x-filament::badge color="primary">Primary Match</x-filament::badge>
                                                @endif
                                            </span>
                                            <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                                                <input type="text" wire:model.defer="referenceInputs.{{ $field->id }}" class="fi-input">
                                            </x-filament::input.wrapper>
                                            @error("referenceInputs.$field->id")
                                                <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                                            @enderror
                                            @if ($field->help_text)
                                                <span class="siardi-filter-help">{{ $field->help_text }}</span>
                                            @endif
                                            @if (! empty($suggestions[$field->reference_type] ?? null))
                                                <div class="flex flex-wrap gap-2">
                                                    <x-filament::badge color="warning">Suggestion</x-filament::badge>
                                                    <x-filament::badge color="gray">{{ $suggestions[$field->reference_type] }}</x-filament::badge>
                                                </div>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>

                                <div class="flex flex-wrap justify-end gap-3">
                                    <x-filament::button type="button" color="gray" wire:click="lookupCandidates" wire:loading.attr="disabled">
                                        Cari Kandidat DWH
                                    </x-filament::button>
                                    <x-filament::button type="button" color="primary" wire:click="saveReferences" wire:loading.attr="disabled">
                                        Simpan Referensi
                                    </x-filament::button>
                                </div>

                                <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Kandidat DWH</h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Isi satu atau lebih field referensi, lalu cari kandidat untuk mempercepat linking.
                                            </p>
                                        </div>
                                        @if ($hasLookedUpCandidates)
                                            <x-filament::badge color="{{ empty($candidateResults) ? 'warning' : 'success' }}">
                                                {{ count($candidateResults) }} Kandidat
                                            </x-filament::badge>
                                        @endif
                                    </div>

                                    @if (! $hasLookedUpCandidates)
                                        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                            Belum ada pencarian kandidat. Gunakan input referensi di atas lalu klik <strong>Cari Kandidat DWH</strong>.
                                        </div>
                                    @elseif (empty($candidateResults))
                                        <div class="mt-4 text-sm text-warning-700 dark:text-warning-300">
                                            Tidak ada kandidat DWH yang cocok dengan input saat ini.
                                        </div>
                                    @else
                                        <div class="mt-4 space-y-3">
                                            @foreach ($candidateResults as $index => $candidate)
                                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/10">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div class="space-y-2">
                                                            <div class="flex flex-wrap gap-2">
                                                                <x-filament::badge color="success">{{ $candidate['business_key'] }}</x-filament::badge>
                                                                <x-filament::badge color="gray">CIF {{ $candidate['cif'] ?: '-' }}</x-filament::badge>
                                                                <x-filament::badge color="primary">DWH {{ $candidate['dwh_location_code'] ?: '-' }}</x-filament::badge>
                                                                <x-filament::badge color="gray">{{ $candidate['status'] ?: 'Tanpa status' }}</x-filament::badge>
                                                            </div>
                                                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                                                Source Key: <span class="font-mono">{{ $candidate['source_key'] }}</span> |
                                                                Snapshot: {{ $candidate['as_of_date'] ?: '-' }}
                                                            </div>
                                                            <div class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                                @foreach ($candidate['reference_values'] as $referenceType => $value)
                                                                    @if (filled($value))
                                                                        <span class="rounded-full bg-white px-3 py-1 dark:bg-white/10">
                                                                            {{ $referenceType }}: {{ $value }}
                                                                        </span>
                                                                    @endif
                                                                @endforeach
                                                            </div>
                                                        </div>

                                                        <x-filament::button type="button" color="primary" size="sm" wire:click="applyCandidate({{ $index }})">
                                                            Pakai Kandidat
                                                        </x-filament::button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
