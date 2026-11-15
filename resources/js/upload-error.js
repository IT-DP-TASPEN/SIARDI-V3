// Replace FilePond's generic "Error during upload" for failed temporary uploads.
// Uses only documented hooks: FilePond DOM events (`FilePond:init`, `FilePond:processfile`)
// and Filament's JS notification API. Livewire's `$wire.upload()` error callback carries no
// error details, so the cause (413, 5xx, network) cannot be classified client-side.
const UPLOAD_FAILED_TITLE = 'Upload file gagal';
const UPLOAD_FAILED_BODY = 'Server tidak berhasil menerima atau menyimpan file. Silakan coba kembali. Jika masalah berlanjut, hubungi IT.';

document.addEventListener('FilePond:init', (event) => {
    event.detail?.pond?.setOptions({
        labelFileProcessingError: UPLOAD_FAILED_TITLE,
        labelTapToRetry: 'klik untuk coba lagi',
    });
});

document.addEventListener('FilePond:processfile', (event) => {
    if (!event.detail?.error || !window.FilamentNotification) {
        return;
    }

    new window.FilamentNotification()
        .title(UPLOAD_FAILED_TITLE)
        .body(UPLOAD_FAILED_BODY)
        .danger()
        .send();
});
