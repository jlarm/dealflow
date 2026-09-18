<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Http\Requests\ContactStoreRequest;
use App\Http\Requests\ContactUpdateRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\TagResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * The columns the contacts list may be sorted by, keyed by the sort name the frontend sends.
     *
     * @var array<string, list<string>>
     */
    private const array SORTABLE_COLUMNS = [
        'name' => ['last_name', 'first_name'],
        'score' => ['score'],
        'last_contacted_at' => ['last_contacted_at'],
        'created_at' => ['created_at'],
    ];

    /**
     * Show the filterable list of contacts.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => ContactStatus::tryFrom($request->string('status')->value())?->value,
            'tag' => $request->integer('tag') ?: null,
            'company' => $request->integer('company') ?: null,
            'source_list' => $request->string('source_list')->trim()->value(),
            'state' => $request->string('state')->trim()->value(),
            'seniority' => $request->string('seniority')->trim()->value(),
            'sort' => array_key_exists($request->string('sort')->value(), self::SORTABLE_COLUMNS)
                ? $request->string('sort')->value()
                : 'created_at',
            'direction' => $request->string('direction')->value() === 'asc' ? 'asc' : 'desc',
        ];

        $contacts = Contact::query()
            ->with(['company:id,name,city,state', 'tags:id,name'])
            ->when($filters['search'], fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tag'], fn (Builder $query, int $tag) => $query->whereRelation('tags', 'tags.id', $tag))
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company))
            ->when($filters['source_list'], fn (Builder $query, string $sourceList) => $query->where('source_list', $sourceList))
            ->when($filters['state'], fn (Builder $query, string $state) => $query->whereRelation('company', 'state', $state))
            ->when($filters['seniority'], fn (Builder $query, string $seniority) => $query->where('seniority', $seniority))
            ->tap(function (Builder $query) use ($filters): void {
                foreach (self::SORTABLE_COLUMNS[$filters['sort']] as $column) {
                    $query->orderBy($column, $filters['direction']);
                }
            })
            ->orderBy('id', $filters['direction'])
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('contacts/index', [
            'contacts' => ContactResource::collection($contacts),
            'filters' => $filters,
            'statuses' => ContactStatus::options(),
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get(['id', 'name'])),
            'sourceLists' => Contact::query()
                ->whereNotNull('source_list')
                ->distinct()
                ->orderBy('source_list')
                ->pluck('source_list'),
            'states' => Company::query()
                ->whereNotNull('state')
                ->distinct()
                ->orderBy('state')
                ->pluck('state'),
            'seniorities' => Contact::query()
                ->whereNotNull('seniority')
                ->distinct()
                ->orderBy('seniority')
                ->pluck('seniority'),
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('contacts/create', [
            'companies' => $this->companyOptions(),
            'companyId' => $request->integer('company') ?: null,
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(ContactStoreRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Show a contact with its activity timeline.
     */
    public function show(Contact $contact): Response
    {
        $contact->load(['company:id,name,city,state', 'tags:id,name']);

        return Inertia::render('contacts/show', [
            'contact' => new ContactResource($contact),
            'activities' => Inertia::scroll(fn () => ActivityResource::collection(
                $contact->activities()
                    ->with('user:id,name')
                    ->latest()
                    ->latest('id')
                    ->paginate(20, pageName: 'activities')
            ))->defer(),
            'statuses' => ContactStatus::options(),
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get(['id', 'name'])),
            'loggableActivityTypes' => array_map(fn (ActivityType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], ActivityType::loggable()),
        ]);
    }

    /**
     * Show the form for editing a contact.
     */
    public function edit(Contact $contact): Response
    {
        return Inertia::render('contacts/edit', [
            'contact' => new ContactResource($contact),
            'companies' => $this->companyOptions(),
        ]);
    }

    /**
     * Update a contact's details. Pipeline status is changed separately so the change is logged.
     */
    public function update(ContactUpdateRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Delete a contact along with its timeline and enrollments.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('contacts.index');
    }

    /**
     * Get the companies a contact can be assigned to.
     *
     * @return Collection<int, Company>
     */
    private function companyOptions(): Collection
    {
        return Company::query()->orderBy('name')->get(['id', 'name']);
    }
}
