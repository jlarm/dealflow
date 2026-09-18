<?php

namespace App\Http\Requests;

use App\Concerns\CampaignStepValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CampaignStepUpdateRequest extends FormRequest
{
    use CampaignStepValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->campaignStepRules();
    }
}
