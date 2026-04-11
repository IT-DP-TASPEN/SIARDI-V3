<x-filament-panels::page>
    <div class="siardi-page-stack">
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
                </div>
            </x-slot>

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
        </x-filament::section>

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
        </x-filament::section>

        @if ($selectedArchive)
            <x-filament::section
                heading="Link Referensi Bisnis"
                description="Tambahkan referensi bisnis untuk arsip legacy tanpa memodifikasi metadata legacy yang sudah ada."
                icon="heroicon-o-link"
            >
                <x-slot name="afterHeader">
                    <div class="flex flex-wrap gap-2">
                        <x-filament::badge color="gray">{{ $selectedArchive->category?->category_name ?? 'Tanpa kategori' }}</x-filament::badge>
                        <x-filament::badge color="primary">{{ $selectedArchive->branchOffice?->branch_name ?? 'Tanpa cabang' }}</x-filament::badge>
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $selectedArchive->archive_name }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($selectedArchive->archive_code)
                                <x-filament::badge color="gray">{{ $selectedArchive->archive_code }}</x-filament::badge>
                            @endif
                            <x-filament::badge color="warning">{{ $selectedArchive->archive_path }}</x-filament::badge>
                        </div>
                    </div>

                    @if ($referenceFields->isEmpty())
                        <div class="siardi-empty">Kategori ini belum memiliki konfigurasi referensi bisnis.</div>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($referenceFields as $field)
                                <label class="siardi-filter-field">
                                    <span class="siardi-filter-label">{{ $field->label }}</span>
                                    <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('input')?.focus()">
                                        <input type="text" wire:model.defer="referenceInputs.{{ $field->id }}" class="fi-input">
                                    </x-filament::input.wrapper>
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

                        <div class="flex justify-end">
                            <x-filament::button type="button" color="primary" wire:click="saveReferences">
                                Simpan Referensi
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
