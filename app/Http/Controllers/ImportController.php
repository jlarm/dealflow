<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStoreRequest;
use App\Http\Resources\ImportFailureResource;
use App\Http\Resources\ImportResource;
use App\Jobs\ProcessImport;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * Show every CSV upload, newest first.
     */
    public function index(): Response
    {
        $imports = Import::query()
            ->with('user:id,name')
            ->latest()
            ->latest('id')
            ->paginate(20);

        return Inertia::render('imports/index', [
            'imports' => ImportResource::collection($imports),
        ]);
    }

    /**
     * Show the upload form.
     */
    public function create(): Response
    {
        return Inertia::render('imports/create', [
            'expectedColumns' => [
                'Email', 'First name', 'Last name', 'Name', 'Title', 'Phone', 'Email Status',
                'Company', 'Website', 'City', 'State', 'Industry', 'Employees', 'LinkedIn',
                'Seniority', 'Departments', 'Lists', 'Source Type', 'Source URL', 'Research Date', 'Notes',
            ],
        ]);
    }

    /**
     * Store the uploaded file and queue it for processing.
     */
    public function store(ImportStoreRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        $import = Import::create([
            'user_id' => $request->user()?->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $file->store('imports', 'local'),
            'source_list' => $request->validated('source_list'),
        ]);

        ProcessImport::dispatch($import);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import started.')]);

        return to_route('imports.show', $import);
    }

    /**
     * Show an import's progress and the rows that failed.
     */
    public function show(Import $import): Response
    {
        $failures = $import->failures()
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate(25);

        return Inertia::render('imports/show', [
            'import' => new ImportResource($import->load('user:id,name')),
            'failures' => ImportFailureResource::collection($failures),
        ]);
    }
}
