<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property CampaignStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'status'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
        ];
    }

    /**
     * The emails in the sequence, in sending order.
     *
     * @return HasMany<CampaignStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Contact, $this, CampaignEnrollment, 'enrollment'>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->using(CampaignEnrollment::class)
            ->as('enrollment')
            ->withPivot(CampaignEnrollment::PIVOT_COLUMNS);
    }

    /**
     * @return HasMany<CampaignEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CampaignEnrollment::class);
    }
}
