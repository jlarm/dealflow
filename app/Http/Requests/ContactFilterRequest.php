<?php

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reads the contact list filters. Values that are not valid filters are ignored
 * rather than rejected, so a stale or hand-edited URL still shows the list.
 */
class ContactFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the filters to apply with the Contact::filter() scope.
     *
     * @return array{search: string, status: string|null, tag: int|null, company: int|null, source_list: string, state: string, seniority: string}
     */
    public function filters(): array
    {
        return [
            'search' => $this->string('search')->trim()->value(),
            'status' => ContactStatus::tryFrom($this->string('status')->value())?->value,
            'tag' => $this->integer('tag') ?: null,
            'company' => $this->integer('company') ?: null,
            'source_list' => $this->string('source_list')->trim()->value(),
            'state' => $this->string('state')->trim()->value(),
            'seniority' => $this->string('seniority')->trim()->value(),
        ];
    }
}
