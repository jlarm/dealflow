<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property EmailStatus|null $email_status
 * @property string|null $phone
 * @property string|null $title
 * @property string|null $linkedin_url
 * @property string|null $source_list
 * @property ContactStatus $status
 * @property int $score
 * @property Carbon|null $last_contacted_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'first_name',
    'last_name',
    'email',
    'email_status',
    'phone',
    'title',
    'linkedin_url',
    'source_list',
    'status',
    'score',
    'last_contacted_at',
    'unsubscribed_at',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'score' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'email_status' => EmailStatus::class,
            'score' => 'integer',
            'last_contacted_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<EmailEvent, $this>
     */
    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }

    /**
     * @return BelongsToMany<Campaign, $this, CampaignEnrollment, 'enrollment'>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)
            ->using(CampaignEnrollment::class)
            ->as('enrollment')
            ->withPivot(CampaignEnrollment::PIVOT_COLUMNS);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Scope the query to contacts in the given pipeline stage.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, ContactStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope the query to contacts that may receive outreach email.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function contactable(Builder $query): void
    {
        $query->whereNotNull('email')
            ->whereNull('unsubscribed_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('email_status')
                ->orWhereNotIn('email_status', EmailStatus::unsendable()));
    }
}
