<?php

use App\Http\Controllers\Webhooks\MailgunEventController;
use App\Http\Controllers\Webhooks\MailgunInboundController;
use App\Http\Middleware\VerifyMailgunSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/mailgun')
    ->middleware([VerifyMailgunSignature::class, 'throttle:600,1'])
    ->group(function () {
        Route::post('events', MailgunEventController::class)->name('webhooks.mailgun.events');
        Route::post('inbound', MailgunInboundController::class)->name('webhooks.mailgun.inbound');
    });
