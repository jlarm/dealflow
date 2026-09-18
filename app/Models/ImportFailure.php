<?php

namespace App\Models;

use Database\Factories\ImportFailureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CSV row that could not be imported, with its validation errors.
 *
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property array<string, list<string>> $errors
 * @property array<string, string|null> $raw_row
 * @property Carbon|null $created_at
 */
#[Fillable(['row_number', 'errors', 'raw_row'])]
class ImportFailure extends Model
{
    /** @use HasFactory<ImportFailureFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'errors' => 'array',
            'raw_row' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
