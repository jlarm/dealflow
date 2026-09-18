<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignEnrollmentController;
use App\Http\Controllers\CampaignStatusController;
use App\Http\Controllers\CampaignStepController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactActivityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactStatusController;
use App\Http\Controllers\ContactTagController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PipelineContactController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::patch('pipeline/contacts/{contact}', [PipelineContactController::class, 'update'])->name('pipeline.contacts.update');

    Route::resource('contacts', ContactController::class);
    Route::patch('contacts/{contact}/status', [ContactStatusController::class, 'update'])->name('contacts.status.update');
    Route::post('contacts/{contact}/activities', [ContactActivityController::class, 'store'])->name('contacts.activities.store');
    Route::put('contacts/{contact}/tags', [ContactTagController::class, 'update'])->name('contacts.tags.update');

    Route::resource('companies', CompanyController::class);

    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('imports', ImportController::class)->only(['index', 'create', 'store', 'show']);

    Route::resource('campaigns', CampaignController::class);
    Route::patch('campaigns/{campaign}/status', [CampaignStatusController::class, 'update'])->name('campaigns.status.update');
    Route::resource('campaigns.steps', CampaignStepController::class)->only(['store', 'update', 'destroy'])->scoped(['step' => 'id']);
    Route::resource('campaigns.enrollments', CampaignEnrollmentController::class)->only(['store', 'destroy'])->scoped(['enrollment' => 'id']);
});

Route::get('unsubscribe/{contact}', [UnsubscribeController::class, 'show'])
    ->middleware('signed')
    ->name('unsubscribe.show');
Route::post('unsubscribe/{contact}', [UnsubscribeController::class, 'store'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('unsubscribe.store');
