<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A delivery event reported by the email provider, stored raw so it can be reprocessed.
 *
 * @property int $id
 * @property int|null $activity_id
 * @property int $contact_id
 * @property string $message_id
 * @property string|null $provider_event_id
 * @property EmailEventType $event_type
 * @property array<string, mixed>|null $payload
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['activity_id', 'message_id', 'provider_event_id', 'event_type', 'payload', 'occurred_at'])]
class EmailEvent extends Model
{
    /** @use HasFactory<EmailEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EmailEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Count the campaign emails sent since midnight in the outreach timezone.
     */
    public static function sentToday(): int
    {
        $timezone = config('outreach.timezone');

        return self::query()
            ->where('event_type', EmailEventType::Sent)
            ->where('occurred_at', '>=', now($timezone)->startOfDay()->utc())
            ->count();
    }

    /**
     * Find the campaign email a provider event or reply refers to, by DealFlow's
     * Message-ID or the id the mail provider reported when it accepted the email.
     *
     * @param  list<string>  $messageIds
     */
    public static function findSent(array $messageIds): ?self
    {
        $messageIds = array_values(array_filter(array_map(fn (string $id): string => trim($id, " <>\t\n\r"), $messageIds)));

        if ($messageIds === []) {
            return null;
        }

        return self::query()
            ->where('event_type', EmailEventType::Sent)
            ->where(fn ($query) => $query
                ->whereIn('message_id', $messageIds)
                ->orWhereIn('payload->provider_message_id', $messageIds))
            ->latest('id')
            ->first();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The timeline entry created for this event, if it was significant enough to log.
     *
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
