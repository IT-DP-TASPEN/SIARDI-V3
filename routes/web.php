<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RekapArsipController;

Route::get('/', function () {
    return redirect('/arsip_digital');
});

Route::get('/arsip_digital/archive-recap/print', [RekapArsipController::class, 'print'])->name('rekap.print');
