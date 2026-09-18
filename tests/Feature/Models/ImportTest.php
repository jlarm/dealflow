<?php

use App\Enums\ImportStatus;
use App\Models\Import;

test('finishing without failed chunks completes the import', function () {
    $import = Import::factory()->create();

    $import->finish();

    expect($import->refresh())
        ->status->toBe(ImportStatus::Completed)
        ->error->toBeNull();
});

test('finishing with failed chunks fails the import and says how many', function () {
    $import = Import::factory()->create();

    $import->finish(failedChunks: 2);

    expect($import->refresh())
        ->status->toBe(ImportStatus::Failed)
        ->error->toBe('2 batches of rows could not be imported.');
});
