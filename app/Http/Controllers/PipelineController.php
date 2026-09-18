<?php

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ScrollProp;

class PipelineController extends Controller
{
    /**
     * The number of contacts loaded into a column at a time.
     */
    private const int CONTACTS_PER_COLUMN = 20;

    /**
     * Show the kanban board with one column per pipeline stage.
     */
    public function index(): Response
    {
        $columns = [];

        foreach (ContactStatus::cases() as $status) {
            $columns["stage_{$status->value}"] = $this->column($status);
        }

        return Inertia::render('pipeline/index', [
            'statuses' => ContactStatus::options(),
            'counts' => fn (): array => Contact::countsByStatus(),
            ...$columns,
        ]);
    }

    /**
     * Get a stage's contacts in board order, one page at a time.
     *
     * Cursor pagination continues after the last loaded card, so moving cards
     * between columns never shifts the next page and skips a contact.
     *
     * @return ScrollProp<mixed>
     */
    private function column(ContactStatus $status): ScrollProp
    {
        return Inertia::scroll(fn () => ContactResource::collection(
            Contact::query()
                ->withStatus($status)
                ->with('company:id,name,city,state')
                ->orderBy('pipeline_position')
                ->orderBy('id')
                ->cursorPaginate(self::CONTACTS_PER_COLUMN, cursorName: $status->value)
        ))->matchOn('data.id');
    }
}
