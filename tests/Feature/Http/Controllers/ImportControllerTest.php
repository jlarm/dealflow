<?php

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('imports.index'));

    $response->assertRedirect(route('login'));
});

describe('index', function () {
    test('lists imports newest first', function () {
        $older = Import::factory()->completed()->create(['created_at' => now()->subDay()]);
        $newer = Import::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('imports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('imports/index')
            ->where('imports.data.0.id', $newer->id)
            ->where('imports.data.1.id', $older->id)
            ->missing('imports.data.0.path'));
    });
});

describe('store', function () {
    test('stores the file privately and queues it for processing', function () {
        Storage::fake('local');
        Queue::fake([ProcessImport::class]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('q4-dealers.csv', "Email\njane@acme.com\n"),
            'source_list' => 'Q4 dealers',
        ]);

        $import = Import::sole();
        $response->assertRedirect(route('imports.show', $import))
            ->assertInertiaFlash('toast.message', 'Import started.');
        expect($import)
            ->filename->toBe('q4-dealers.csv')
            ->source_list->toBe('Q4 dealers')
            ->user_id->toBe($user->id)
            ->status->toBe(ImportStatus::Processing);
        Storage::disk('local')->assertExists($import->path);
        Queue::assertPushed(ProcessImport::class, fn (ProcessImport $job): bool => $job->import->is($import));
    });

    test('rejects files that are not CSV', function () {
        Storage::fake('local');
        Queue::fake([ProcessImport::class]);

        $response = $this->actingAs(User::factory()->create())->post(route('imports.store'), [
            'file' => UploadedFile::fake()->image('photo.png'),
            'source_list' => 'Photos',
        ]);

        $response->assertSessionHasErrors(['file' => 'Upload a CSV file.']);
        $this->assertDatabaseEmpty('imports');
        Queue::assertNothingPushed();
    });

    test('requires a source list', function () {
        Storage::fake('local');
        Queue::fake([ProcessImport::class]);

        $response = $this->actingAs(User::factory()->create())->post(route('imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('leads.csv', "Email\njane@acme.com\n"),
        ]);

        $response->assertSessionHasErrors('source_list');
        $this->assertDatabaseEmpty('imports');
        Queue::assertNothingPushed();
    });
});

describe('show', function () {
    test('shows the import with its failed rows in file order', function () {
        $import = Import::factory()->completed()->create();
        $later = ImportFailure::factory()->for($import)->create(['row_number' => 9]);
        $earlier = ImportFailure::factory()->for($import)->create([
            'row_number' => 3,
            'errors' => ['email' => ['The email field must be a valid email address.']],
        ]);
        ImportFailure::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('imports.show', $import));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('imports/show')
            ->where('import.id', $import->id)
            ->has('failures.data', 2)
            ->where('failures.data.0.id', $earlier->id)
            ->where('failures.data.0.errors', ['The email field must be a valid email address.'])
            ->where('failures.data.1.id', $later->id));
    });
});
