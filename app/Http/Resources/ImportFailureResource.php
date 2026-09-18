<?php

namespace App\Http\Resources;

use App\Models\ImportFailure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportFailure
 */
class ImportFailureResource extends JsonResource
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
            'row_number' => $this->row_number,
            'errors' => array_merge(...array_values($this->errors)),
            'raw_row' => $this->raw_row,
        ];
    }
}
