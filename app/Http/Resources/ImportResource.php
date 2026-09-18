<?php

namespace App\Http\Resources;

use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Import
 */
class ImportResource extends JsonResource
{
    /**
     * Transform the resource into an array. The stored file path is deliberately left out.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'source_list' => $this->source_list,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'row_count' => $this->row_count,
            'processed_rows' => $this->processed_rows,
            'failed_rows' => $this->failed_rows,
            'error' => $this->error,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
