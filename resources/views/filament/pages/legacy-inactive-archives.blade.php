<x-filament-panels::page>
    <div
        class="siardi-page-stack"
        x-data
        x-on:modal-closed.window="if ($event.detail.id === '{{ \App\Filament\Pages\LegacyInactiveArchives::DETAIL_MODAL_ID }}') $wire.handleModalClosed($event.detail.id)"
    >
        <x-filament::section
            heading="Filter Arsip Inactive"
            description="Lihat arsip legacy yang sudah dipisahkan dari antrian linking utama."
            icon="heroicon-o-funnel"
        >
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
            heading="Daftar Arsip Inactive"
            description="Arsip di sini tidak muncul di Legacy Linking sampai dikembalikan."
            icon="heroicon-o-archive-box"
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
                                <th>Ditandai Oleh</th>
                                <th>Waktu Inactive</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($archives as $archive)
                                @php
                                    $marker = $archive->legacyInactiveMarker;
                                @endphp
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
                                    <td>{{ $marker?->markedBy?->name ?? '-' }}</td>
                                    <td>{{ $marker?->marked_inactive_at?->format('d M Y H:i') ?? '-' }}</td>
                                    <td class="text-right">
                                        <x-filament::button type="button" color="gray" size="sm" wire:click="selectArchive({{ $archive->id }})">
                                            Detail
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="siardi-empty">Belum ada arsip inactive.</div>
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
            id="{{ \App\Filament\Pages\LegacyInactiveArchives::DETAIL_MODAL_ID }}"
            heading="Detail Arsip Inactive"
            icon="heroicon-o-archive-box"
            width="7xl"
            teleport="body"
            sticky-header
            close-button
            :close-by-clicking-away="false"
        >
            @if (! $selectedArchive)
                <div class="siardi-empty">
                    Pilih arsip dari daftar untuk melihat detail inactive.
                </div>
            @else
                @php
                    $marker = $selectedArchive->legacyInactiveMarker;
                @endphp
                <div class="grid items-start gap-x-6 gap-y-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,1fr)] xl:gap-x-8">
                    <div class="siardi-workspace-stack">
                        <div class="siardi-surface-card">
                            <div class="space-y-4">
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
                                    <x-filament::badge color="gray">Inactive</x-filament::badge>
                                </div>

                                <dl class="grid gap-3 text-sm text-gray-600 dark:text-gray-300">
                                    <div>
                                        <dt class="font-medium text-gray-950 dark:text-white">Ditandai Oleh</dt>
                                        <dd>{{ $marker?->markedBy?->name ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-950 dark:text-white">Waktu Inactive</dt>
                                        <dd>{{ $marker?->marked_inactive_at?->format('d M Y H:i') ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-950 dark:text-white">Tanggal Arsip</dt>
                                        <dd>{{ \App\Filament\Resources\ArchiveResource::formatDateValue($selectedArchive->archive_date) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-950 dark:text-white">Deskripsi</dt>
                                        <dd>{{ $selectedArchive->archive_description ?: '-' }}</dd>
                                    </div>
                                </dl>

                                <div class="flex flex-wrap justify-between gap-3">
                                    <x-filament::button type="button" color="gray" wire:click="clearSelection">
                                        Tutup
                                    </x-filament::button>
                                    <x-filament::button
                                        type="button"
                                        color="primary"
                                        wire:click="restoreArchive"
                                        wire:confirm="Arsip ini akan dikembalikan ke Legacy Linking. Lanjutkan?"
                                    >
                                        Kembalikan ke Legacy Linking
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="siardi-surface-card">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Preview Arsip</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Lihat file arsip sebelum memutuskan restore ke queue utama.
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
