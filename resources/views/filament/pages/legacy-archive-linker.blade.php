<x-filament-panels::page>
    <div
        class="siardi-page-stack"
        x-data
        x-on:modal-closed.window="if ($event.detail.id === '{{ \App\Filament\Pages\LegacyArchiveLinker::LINKING_MODAL_ID }}') $wire.handleModalClosed($event.detail.id)"
    >
        <x-filament::section
            heading="Filter Arsip Legacy"
            description="Kerjakan arsip lama yang belum memiliki referensi bisnis tanpa mengubah perilaku legacy yang sudah ada."
            icon="heroicon-o-funnel"
        >
            <x-slot name="afterHeader">
                @if (! empty($configurationGaps))
                    <x-filament::badge color="warning">{{ count($configurationGaps) }} Kategori Belum Terkonfigurasi</x-filament::badge>
                @endif
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

        <x-filament::section
            heading="Arsip Legacy Belum Tertaut"
            description="Antrian kerja untuk arsip existing yang masih belum memiliki referensi bisnis."
            icon="heroicon-o-document-magnifying-glass"
        >
            <div class="space-y-4">
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
                                <tr>
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
                                        <x-filament::button type="button" color="primary" size="sm" wire:click="selectArchive({{ $archive->id }})">
                                            Link References
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

                @if ($archives->hasPages())
                    <div>
                        <x-filament::pagination :paginator="$archives" />
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::modal
            id="{{ \App\Filament\Pages\LegacyArchiveLinker::LINKING_MODAL_ID }}"
            heading="Link Arsip"
            icon="heroicon-o-link"
            width="7xl"
            teleport="body"
            sticky-header
            close-button
            :close-by-clicking-away="false"
        >
            @if (! $selectedArchive)
                <div class="siardi-empty">
                    Pilih arsip dari antrian kiri untuk mulai melakukan linking referensi bisnis.
                </div>
            @else
                @php
                    $canValidateAgainstTarget = (bool) $selectedArchive->branchOffice?->dwhMapping?->is_active;
                @endphp
                <div class="grid items-start gap-x-6 gap-y-8 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)] xl:gap-x-8">
                    <div class="siardi-workspace-stack">
                        <div class="siardi-surface-card">
                            <div class="siardi-panel-split">
                                <div class="siardi-panel-copy">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $selectedArchive->archive_name }}</h3>
                                        <p class="break-all text-sm text-gray-500 dark:text-gray-400">
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
                                                {{ $selectedArchive->branchOffice->dwhMapping->dwh_location_code }}
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">Tanpa Mapping Cabang Aktif</x-filament::badge>
                                        @endif
                                    </div>
                                </div>

                                <div class="siardi-action-rail">
                                    <x-filament::button type="button" color="gray" size="sm" wire:click="clearSelection">
                                        Reset
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>

                        @if ($referenceFields->isEmpty())
                            <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                                Kategori ini belum memiliki konfigurasi referensi bisnis. Arsip tetap aman sebagai legacy document, tetapi belum bisa dilink ke target dan realisasi sampai konfigurasi field diaktifkan.
                            </div>
                        @else
                            @if (! $canValidateAgainstTarget)
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                                    Cabang arsip ini belum punya mapping target aktif. Lookup kandidat dan simpan referensi diblok sampai mapping cabang diaktifkan.
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

                                @if ($selectedArchive->category?->category_name === 'TABUNGAN')
                                    <label class="siardi-filter-field">
                                        <span class="siardi-filter-label">
                                            Alt Rekening Tabungan
                                            <x-filament::badge color="gray">Search Helper</x-filament::badge>
                                        </span>
                                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                                            <input type="text" wire:model.defer="candidateSearchInputs.savings_alt_account_no" class="fi-input">
                                        </x-filament::input.wrapper>
                                        <span class="siardi-filter-help">
                                            Hanya untuk pencarian kandidat tabungan. Nilai ini tidak disimpan sebagai referensi arsip.
                                        </span>
                                        @if (! empty($suggestions['savings_alt_account_no'] ?? null))
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge color="warning">Suggestion</x-filament::badge>
                                                <x-filament::badge color="gray">{{ $suggestions['savings_alt_account_no'] }}</x-filament::badge>
                                            </div>
                                        @endif
                                    </label>
                                @endif

                                @if ($selectedArchive->category?->category_name === 'KREDIT')
                                    <label class="siardi-filter-field">
                                        <span class="siardi-filter-label">
                                            Alt Rekening Kredit
                                            <x-filament::badge color="gray">Search Helper</x-filament::badge>
                                        </span>
                                        <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                                            <input type="text" wire:model.defer="candidateSearchInputs.loan_alt_account_no" class="fi-input">
                                        </x-filament::input.wrapper>
                                        <span class="siardi-filter-help">
                                            Hanya untuk pencarian kandidat kredit. Nilai ini tidak disimpan sebagai referensi arsip.
                                        </span>
                                        @if (! empty($suggestions['loan_alt_account_no'] ?? null))
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge color="warning">Suggestion</x-filament::badge>
                                                <x-filament::badge color="gray">{{ $suggestions['loan_alt_account_no'] }}</x-filament::badge>
                                            </div>
                                        @endif
                                    </label>
                                @endif
                            </div>

                            <div class="flex flex-wrap justify-between gap-3">
                                <x-filament::button
                                    type="button"
                                    color="warning"
                                    wire:click="markInactive"
                                    wire:confirm="Arsip ini akan dipindahkan ke Arsip Inactive. Lanjutkan?"
                                >
                                    Tandai Inactive
                                </x-filament::button>

                                <div class="flex flex-wrap justify-end gap-3">
                                    <x-filament::button type="button" color="gray" wire:click="lookupCandidates" wire:loading.attr="disabled" :disabled="! $canValidateAgainstTarget">
                                        Cari Kandidat
                                    </x-filament::button>
                                    <x-filament::button type="button" color="primary" wire:click="saveReferences" wire:loading.attr="disabled" :disabled="! $canValidateAgainstTarget">
                                        Simpan Referensi
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="siardi-surface-card">
                                <div class="siardi-panel-split">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Kandidat Data</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Isi satu atau lebih field referensi, lalu cari kandidat untuk mempercepat linking.
                                        </p>
                                    </div>
                                    @if ($hasLookedUpCandidates)
                                        <div class="siardi-action-rail">
                                            <x-filament::badge color="{{ empty($candidateResults) ? 'warning' : 'success' }}">
                                                {{ count($candidateResults) }} Kandidat
                                            </x-filament::badge>
                                        </div>
                                    @endif
                                </div>

                                @if (! $hasLookedUpCandidates)
                                    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada pencarian kandidat. Gunakan input referensi di atas lalu klik <strong>Cari Kandidat</strong>.
                                    </div>
                                @elseif (empty($candidateResults))
                                    <div class="mt-4 text-sm text-warning-700 dark:text-warning-300">
                                        Tidak ada kandidat data yang cocok dengan input saat ini.
                                    </div>
                                @else
                                    <div class="mt-4 space-y-3">
                                        @foreach ($candidateResults as $index => $candidate)
                                            <div class="siardi-candidate-card">
                                                <div class="siardi-panel-split">
                                                    <div class="siardi-panel-copy">
                                                        <div class="flex flex-wrap gap-2">
                                                            <x-filament::badge color="success">{{ $candidate['business_key'] }}</x-filament::badge>
                                                            <x-filament::badge color="gray">CIF {{ $candidate['cif'] ?: '-' }}</x-filament::badge>
                                                            <x-filament::badge color="primary">{{ $candidate['dwh_location_code'] ?: '-' }}</x-filament::badge>
                                                            <x-filament::badge color="gray">{{ $candidate['status'] ?: 'Tanpa status' }}</x-filament::badge>
                                                        </div>
                                                        <div class="text-sm text-gray-600 dark:text-gray-300">
                                                            Source Key: <span class="font-mono">{{ $candidate['source_key'] }}</span>
                                                        </div>
                                                        <div class="siardi-chip-list">
                                                            @foreach ($candidate['reference_values'] as $referenceType => $value)
                                                                @if (filled($value))
                                                                    @php
                                                                        $referenceLabel = match ($referenceType) {
                                                                            'savings_alt_account_no' => 'Alt Rekening Tabungan',
                                                                            'loan_alt_account_no' => 'Alt Rekening Kredit',
                                                                            default => $referenceType,
                                                                        };
                                                                    @endphp
                                                                    <span class="siardi-chip">
                                                                        {{ $referenceLabel }}: {{ $value }}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>

                                                    <div class="siardi-action-rail">
                                                        <x-filament::button type="button" color="primary" size="sm" wire:click="applyCandidate({{ $index }})">
                                                            Pakai Kandidat
                                                        </x-filament::button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="siardi-surface-card">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Preview Arsip</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Cek file arsip langsung dari popup sebelum melakukan linking.
                                </p>
                            </div>

                            {!! $archivePreviewHtml !!}
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::modal>
    </div>
</x-filament-panels::page>
