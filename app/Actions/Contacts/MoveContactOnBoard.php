<?php

namespace App\Actions\Contacts;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Places a contact on the pipeline board exactly where it was dropped: in a stage,
 * between the card above and the card below. Changing stage is logged on the timeline.
 */
class MoveContactOnBoard
{
    /**
     * The smallest gap allowed between neighbouring cards before the stage is renumbered.
     */
    private const float MIN_GAP = 0.001;

    public function __construct(private ChangeContactStatus $changeContactStatus) {}

    /**
     * @param  Contact|null  $above  The card that should end up directly above.
     * @param  Contact|null  $below  The card that should end up directly below.
     */
    public function handle(Contact $contact, ContactStatus $status, ?Contact $above, ?Contact $below, ?User $user = null): void
    {
        DB::transaction(function () use ($contact, $status, $above, $below, $user): void {
            $this->changeContactStatus->handle($contact, $status, $user);

            $contact->update(['pipeline_position' => $this->position($contact, $status, $above, $below)]);
        });
    }

    private function position(Contact $contact, ContactStatus $status, ?Contact $above, ?Contact $below): float
    {
        $above = $above?->status === $status && ! $above->is($contact) ? $above : null;
        $below = $below?->status === $status && ! $below->is($contact) ? $below : null;

        if ($above === null && $below === null) {
            return Contact::topOfStage($status);
        }

        $upper = $above->pipeline_position ?? $this->neighbour($contact, $status, $below, above: true);
        $lower = $below->pipeline_position ?? $this->neighbour($contact, $status, $above, above: false);

        if ($upper === null) {
            return $lower - Contact::PIPELINE_GAP;
        }

        if ($lower === null) {
            return $upper + Contact::PIPELINE_GAP;
        }

        if ($lower - $upper < self::MIN_GAP) {
            $this->renumber($status, $contact);

            return $this->position($contact, $status, $above?->refresh(), $below?->refresh());
        }

        return ($upper + $lower) / 2;
    }

    /**
     * The position of the next card past the given one, which may not be loaded on the board yet.
     */
    private function neighbour(Contact $contact, ContactStatus $status, ?Contact $from, bool $above): ?float
    {
        if ($from === null) {
            return null;
        }

        $query = Contact::query()
            ->withStatus($status)
            ->whereKeyNot($contact->id)
            ->where('pipeline_position', $above ? '<' : '>', $from->pipeline_position);

        $position = $above ? $query->max('pipeline_position') : $query->min('pipeline_position');

        return $position === null ? null : (float) $position;
    }

    /**
     * Spread the stage's cards evenly again once repeated drops leave too small a gap.
     */
    private function renumber(ContactStatus $status, Contact $moving): void
    {
        $position = 0;

        Contact::query()
            ->withStatus($status)
            ->whereKeyNot($moving->id)
            ->orderBy('pipeline_position')
            ->orderBy('id')
            ->select(['id', 'pipeline_position'])
            ->lazy(500)
            ->each(function (Contact $contact) use (&$position): void {
                $position += Contact::PIPELINE_GAP;
                Contact::query()->whereKey($contact->id)->update(['pipeline_position' => $position]);
            });
    }
}
