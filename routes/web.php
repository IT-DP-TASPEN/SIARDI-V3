<?php

use App\Http\Controllers\ArchiveFileController;
use App\Http\Controllers\RekapArsipController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/arsip_digital');
});

Route::middleware(['auth', 'can:page_RekapanArsip'])
    ->get('/arsip_digital/archive-recap/print', [RekapArsipController::class, 'print'])
    ->name('rekap.print');

Route::middleware('auth')->group(function (): void {
    Route::get('/arsip_digital/archive-files/{archive}', [ArchiveFileController::class, 'show'])
        ->name('archives.file.show');
    Route::get('/arsip_digital/archive-files/{archive}/download', [ArchiveFileController::class, 'download'])
        ->name('archives.file.download');
});
