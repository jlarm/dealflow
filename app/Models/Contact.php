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
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property EmailStatus|null $email_status
 * @property string|null $phone
 * @property string|null $title
 * @property string|null $seniority
 * @property string|null $departments
 * @property string|null $linkedin_url
 * @property string|null $source_list
 * @property string|null $apollo_contact_id
 * @property ContactStatus $status
 * @property float $pipeline_position
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
    'seniority',
    'departments',
    'linkedin_url',
    'source_list',
    'apollo_contact_id',
    'status',
    'pipeline_position',
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
            'pipeline_position' => 'float',
            'email_status' => EmailStatus::class,
            'score' => 'integer',
            'last_contacted_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * The gap left between neighbouring cards on the pipeline board.
     */
    public const int PIPELINE_GAP = 1024;

    /**
     * New contacts join the bottom of their stage on the pipeline board.
     */
    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            if (! $contact->isDirty('pipeline_position')) {
                $contact->pipeline_position = self::bottomOfStage($contact->status);
            }
        });
    }

    /**
     * A board position above every card in the stage.
     */
    public static function topOfStage(ContactStatus $status): float
    {
        return (float) (self::query()->withStatus($status)->min('pipeline_position') ?? self::PIPELINE_GAP) - self::PIPELINE_GAP;
    }

    /**
     * A board position below every card in the stage.
     */
    public static function bottomOfStage(ContactStatus $status): float
    {
        return (float) (self::query()->withStatus($status)->max('pipeline_position') ?? 0) + self::PIPELINE_GAP;
    }

    /**
     * Normalize an email address so it can be used as the de-duplication key.
     */
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Count the contacts in every pipeline stage with a single grouped query.
     *
     * @return array<string, int>
     */
    public static function countsByStatus(): array
    {
        $counts = self::query()
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];

        foreach (ContactStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
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
     * Scope the query to contacts that campaigns may email: a verified address
     * (valid status) that has not unsubscribed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function contactable(Builder $query): void
    {
        $query->whereNotNull('email')
            ->whereNull('unsubscribed_at')
            ->where('email_status', EmailStatus::Valid);
    }

    /**
     * Determine whether campaigns may email this contact.
     */
    public function isContactable(): bool
    {
        return $this->email !== null
            && $this->unsubscribed_at === null
            && $this->email_status === EmailStatus::Valid;
    }

    /**
     * Scope the query to the contact list filters from ContactFilterRequest.
     *
     * @param  Builder<self>  $query
     * @param  array{search: string, status: string|null, tag: int|null, company: int|null, source_list: string, state: string, seniority: string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'], fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tag'], fn (Builder $query, int $tag) => $query->whereRelation('tags', 'tags.id', $tag))
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company))
            ->when($filters['source_list'], fn (Builder $query, string $sourceList) => $query->where('source_list', $sourceList))
            ->when($filters['state'], fn (Builder $query, string $state) => $query->whereRelation('company', 'state', $state))
            ->when($filters['seniority'], fn (Builder $query, string $seniority) => $query->where('seniority', $seniority));
    }

    /**
     * Scope the query to contacts whose name, email, title, or company name contains the term.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $pattern = "%{$term}%";

        $query->where(fn (Builder $query) => $query
            ->whereAny(['first_name', 'last_name', 'email', 'title'], 'like', $pattern)
            ->orWhereRelation('company', 'name', 'like', $pattern));
    }
}
