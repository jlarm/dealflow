<?php

namespace App\Concerns;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ContactValidationRules
{
    /**
     * Get the validation rules used to validate contacts.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function contactRules(?int $contactId = null): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'first_name' => ['nullable', 'string', 'max:255', 'required_without_all:last_name,email'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                $contactId === null
                    ? Rule::unique(Contact::class)
                    : Rule::unique(Contact::class)->ignore($contactId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'source_list' => ['nullable', 'string', 'max:255'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get the custom validation messages for contacts.
     *
     * @return array<string, string>
     */
    protected function contactMessages(): array
    {
        return [
            'first_name.required_without_all' => 'Enter a name or an email address.',
        ];
    }

    /**
     * Normalize the email address before it is validated for uniqueness.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => Contact::normalizeEmail($this->string('email')->value())]);
        }
    }
}
