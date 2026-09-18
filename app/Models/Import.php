<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A CSV upload and the progress of processing its rows.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $filename
 * @property string $path
 * @property string $source_list
 * @property ImportStatus $status
 * @property int $row_count
 * @property int $processed_rows
 * @property int $failed_rows
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'filename', 'path', 'source_list', 'status', 'row_count', 'error'])]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'processing',
        'row_count' => 0,
        'processed_rows' => 0,
        'failed_rows' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'row_count' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    /**
     * Mark the import as finished once every chunk has run.
     */
    public function finish(int $failedChunks = 0): void
    {
        $this->update($failedChunks === 0
            ? ['status' => ImportStatus::Completed]
            : [
                'status' => ImportStatus::Failed,
                'error' => trans_choice(':count batch of rows could not be imported.|:count batches of rows could not be imported.', $failedChunks),
            ]);
    }

    /**
     * Mark the whole import as failed, e.g. when the file cannot be read.
     */
    public function fail(string $error): void
    {
        $this->update(['status' => ImportStatus::Failed, 'error' => $error]);
    }

    /**
     * The user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportFailure, $this>
     */
    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class);
    }
}
