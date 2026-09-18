<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait CampaignStepValidationRules
{
    /**
     * Get the validation rules used to validate campaign emails.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function campaignStepRules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'delay_days' => ['required', 'integer', 'min:0', 'max:90'],
        ];
    }
}
