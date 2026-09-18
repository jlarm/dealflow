<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyStoreRequest;
use App\Http\Requests\CompanyUpdateRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ContactResource;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Show the searchable list of companies.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $companies = Company::query()
            ->withCount('contacts')
            ->when($search, fn (Builder $query, string $search) => $query->search($search))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('companies/index', [
            'companies' => CompanyResource::collection($companies),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        return Inertia::render('companies/create');
    }

    /**
     * Store a newly created company.
     */
    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $company = Company::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', $company);
    }

    /**
     * Show a company with its contacts.
     */
    public function show(Company $company): Response
    {
        $contacts = $company->contacts()
            ->with('tags:id,name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25);

        return Inertia::render('companies/show', [
            'company' => new CompanyResource($company->loadCount('contacts')),
            'contacts' => ContactResource::collection($contacts),
        ]);
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company): Response
    {
        return Inertia::render('companies/edit', [
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Update a company's details.
     */
    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.show', $company);
    }

    /**
     * Delete a company. Its contacts are kept without a company.
     */
    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index');
    }
}
