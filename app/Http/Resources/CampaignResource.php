<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
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
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'steps_count' => $this->whenCounted('steps'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'active_enrollments_count' => $this->whenCounted('activeEnrollments'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
