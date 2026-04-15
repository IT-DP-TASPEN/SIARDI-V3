<?php

use App\Http\Controllers\ArchivePreviewController;
use App\Http\Controllers\RekapArsipController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/arsip_digital');
});

Route::middleware(['auth'])
    ->get('/arsip_digital/archives/{archive}/preview', [ArchivePreviewController::class, 'show'])
    ->name('archive.preview');

Route::middleware(['auth', 'can:page_RekapanArsip'])
    ->get('/arsip_digital/archive-recap/print', [RekapArsipController::class, 'print'])
    ->name('rekap.print');
