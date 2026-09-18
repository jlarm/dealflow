<?php

namespace App\Http\Requests;

use App\Concerns\ContactValidationRules;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactUpdateRequest extends FormRequest
{
    use ContactValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contact = $this->route('contact');

        return $this->contactRules($contact instanceof Contact ? $contact->id : null);
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->contactMessages();
    }
}
