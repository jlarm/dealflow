<?php

namespace App\Http\Resources;

use App\Models\CampaignEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CampaignEnrollment
 */
class CampaignEnrollmentResource extends JsonResource
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
            'contact' => $this->whenLoaded('contact', fn (): array => [
                'id' => $this->contact->id,
                'name' => trim("{$this->contact->first_name} {$this->contact->last_name}") ?: (string) $this->contact->email,
                'email' => $this->contact->email,
            ]),
            'sequence_step' => $this->sequence_step,
            'state' => match (true) {
                $this->stopped_at !== null => 'stopped',
                $this->completed_at !== null => 'completed',
                default => 'active',
            },
            'stop_reason' => $this->stop_reason,
            'enrolled_at' => $this->enrolled_at->toIso8601String(),
            'next_send_at' => $this->next_send_at?->toIso8601String(),
        ];
    }
}
