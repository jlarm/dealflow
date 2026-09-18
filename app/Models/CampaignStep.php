<?php

namespace App\Models;

use Database\Factories\CampaignStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $position
 * @property string $subject
 * @property string $body
 * @property int $delay_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['position', 'subject', 'body', 'delay_days'])]
class CampaignStep extends Model
{
    /** @use HasFactory<CampaignStepFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_days' => 'integer',
        ];
    }

    /**
     * The merge fields that can be used in a subject or body, e.g. {{first_name}} or {{first_name|there}}.
     *
     * @var list<string>
     */
    public const array MERGE_FIELDS = ['first_name', 'last_name', 'full_name', 'title', 'company', 'city', 'state'];

    /**
     * Fill in the merge fields for a contact. A field with no value uses the text
     * after "|" as a fallback, or is left empty.
     *
     * @return array{subject: string, body: string}
     */
    public function personalizeFor(Contact $contact): array
    {
        $values = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => trim("{$contact->first_name} {$contact->last_name}"),
            'title' => $contact->title,
            'company' => $contact->company?->name,
            'city' => $contact->company?->city,
            'state' => $contact->company?->state,
        ];

        $fill = fn (string $text): string => (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*(?:\|([^}]*))?\}\}/i',
            function (array $match) use ($values): string {
                $value = $values[strtolower($match[1])] ?? null;

                return filled($value) ? (string) $value : trim($match[2] ?? '');
            },
            $text,
        );

        return ['subject' => $fill($this->subject), 'body' => $fill($this->body)];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
