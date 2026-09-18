<?php

namespace App\Http\Resources;

use App\Models\CampaignStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CampaignStep
 */
class CampaignStepResource extends JsonResource
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
            'position' => $this->position,
            'subject' => $this->subject,
            'body' => $this->body,
            'delay_days' => $this->delay_days,
        ];
    }
}
