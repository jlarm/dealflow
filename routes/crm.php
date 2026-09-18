<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactActivityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactStatusController;
use App\Http\Controllers\ContactTagController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contacts', ContactController::class);
    Route::patch('contacts/{contact}/status', [ContactStatusController::class, 'update'])->name('contacts.status.update');
    Route::post('contacts/{contact}/activities', [ContactActivityController::class, 'store'])->name('contacts.activities.store');
    Route::put('contacts/{contact}/tags', [ContactTagController::class, 'update'])->name('contacts.tags.update');

    Route::resource('companies', CompanyController::class);

    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
});
