<?php

namespace App\Concerns;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CompanyValidationRules
{
    /**
     * Get the validation rules used to validate companies.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function companyRules(?int $companyId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:'.Company::DOMAIN_PATTERN,
                $companyId === null
                    ? Rule::unique(Company::class)
                    : Rule::unique(Company::class)->ignore($companyId),
            ],
            'industry' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get the custom validation messages for companies.
     *
     * @return array<string, string>
     */
    protected function companyMessages(): array
    {
        return [
            'domain.regex' => 'Enter a domain like acme.com.',
        ];
    }

    /**
     * Normalize a website URL to a bare domain before it is validated for uniqueness.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('domain')) {
            $this->merge(['domain' => Company::normalizeDomain($this->string('domain')->value())]);
        }
    }
}
