<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A contact's progress through a campaign's email sequence.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $contact_id
 * @property int $sequence_step The position of the last step sent, or 0 before the first send.
 * @property Carbon $enrolled_at
 * @property Carbon|null $next_send_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $stopped_at
 * @property string|null $stop_reason
 */
#[Table(name: 'campaign_contact', incrementing: true, timestamps: false)]
class CampaignEnrollment extends Pivot
{
    /**
     * The pivot columns loaded through the campaign and contact relationships.
     *
     * @var list<string>
     */
    public const array PIVOT_COLUMNS = [
        'id',
        'sequence_step',
        'enrolled_at',
        'next_send_at',
        'completed_at',
        'stopped_at',
        'stop_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_step' => 'integer',
            'enrolled_at' => 'datetime',
            'next_send_at' => 'datetime',
            'completed_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Determine whether the contact is still working through the sequence.
     */
    public function isActive(): bool
    {
        return $this->completed_at === null && $this->stopped_at === null;
    }

    /**
     * Stop the sequence early, e.g. because the contact unsubscribed or replied.
     */
    public function stop(string $reason): void
    {
        $this->update([
            'stopped_at' => now(),
            'stop_reason' => $reason,
            'next_send_at' => null,
        ]);
    }

    /**
     * Record that a step was sent and schedule the next one, or finish the sequence.
     */
    public function advanceTo(int $position, ?CampaignStep $nextStep): void
    {
        $this->update([
            'sequence_step' => $position,
            'next_send_at' => $nextStep === null ? null : now()->addDays($nextStep->delay_days),
            'completed_at' => $nextStep === null ? now() : null,
        ]);
    }

    /**
     * Scope the query to enrollments that are still working through the sequence.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('completed_at')->whereNull('stopped_at');
    }

    /**
     * Scope the query to enrollments whose next step should be sent now.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueForSend(Builder $query): void
    {
        $query->active()
            ->where('next_send_at', '<=', now())
            ->whereRelation('campaign', 'status', CampaignStatus::Active);
    }
}
