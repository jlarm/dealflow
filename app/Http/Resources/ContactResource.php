<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'email_status' => $this->email_status?->value,
            'phone' => $this->phone,
            'title' => $this->title,
            'seniority' => $this->seniority,
            'departments' => $this->departments,
            'linkedin_url' => $this->linkedin_url,
            'source_list' => $this->source_list,
            'status' => $this->status->value,
            'score' => $this->score,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'city' => $this->company->city,
                'state' => $this->company->state,
            ]),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }

    /**
     * Get the contact's full name, falling back to the email address.
     */
    private function displayName(): string
    {
        $name = trim("{$this->first_name} {$this->last_name}");

        return $name !== '' ? $name : (string) $this->email;
    }
}
