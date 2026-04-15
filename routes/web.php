<?php

use App\Http\Controllers\RekapArsipController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/arsip_digital');
});

Route::middleware(['auth', 'can:page_RekapanArsip'])
    ->get('/arsip_digital/archive-recap/print', [RekapArsipController::class, 'print'])
    ->name('rekap.print');
