<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array. The raw enrichment payload is deliberately left out.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'industry' => $this->industry,
            'size' => $this->size,
            'city' => $this->city,
            'state' => $this->state,
            'phone' => $this->phone,
            'enriched_at' => $this->enriched_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'contacts_count' => $this->whenCounted('contacts'),
        ];
    }
}
