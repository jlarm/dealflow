<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Enums\EmailEventType;
use App\Http\Resources\ImportResource;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\Import;
use App\Services\OutreachSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many days the daily sends chart covers.
     */
    private const int CHART_DAYS = 14;

    /**
     * The period the reply and bounce rates are measured over.
     */
    private const int RATE_DAYS = 30;

    /**
     * Show headline numbers, today's sending, the pipeline, and recent activity.
     */
    public function __invoke(OutreachSchedule $schedule): Response
    {
        $countsByStatus = Contact::countsByStatus();

        return Inertia::render('dashboard', [
            'stats' => $this->stats(),
            'sending' => [
                'sent_today' => EmailEvent::sentToday(),
                'daily_limit' => $schedule->dailyLimit(),
                'window_open' => $schedule->isOpen(),
                'window' => sprintf(
                    'Weekdays %s–%s %s',
                    CarbonImmutable::createFromTime((int) config('outreach.send_from_hour'))->format('ga'),
                    CarbonImmutable::createFromTime((int) config('outreach.send_until_hour'))->format('ga'),
                    now(config('outreach.timezone'))->format('T'),
                ),
            ],
            'sendsByDay' => $this->sendsByDay(),
            'pipeline' => array_map(fn (array $status): array => [
                ...$status,
                'count' => $countsByStatus[$status['value']],
            ], ContactStatus::options()),
            'campaigns' => Inertia::defer(fn (): array => $this->campaigns(), 'activity'),
            'recentReplies' => Inertia::defer(fn (): array => $this->recentReplies(), 'activity'),
            'recentImports' => Inertia::defer(fn () => ImportResource::collection(
                Import::query()->latest()->latest('id')->limit(5)->get(),
            ), 'activity'),
        ]);
    }

    /**
     * @return array{contacts: int, contactable: int, sent_7_days: int, sent_30_days: int, replies_30_days: int, bounces_30_days: int, contacted_30_days: int}
     */
    private function stats(): array
    {
        $since = now()->subDays(self::RATE_DAYS);
        $sentSince = fn (int $days): int => EmailEvent::query()
            ->where('event_type', EmailEventType::Sent)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->count();

        return [
            'contacts' => Contact::count(),
            'contactable' => Contact::contactable()->count(),
            'sent_7_days' => $sentSince(7),
            'sent_30_days' => $sentSince(self::RATE_DAYS),
            'contacted_30_days' => EmailEvent::query()
                ->where('event_type', EmailEventType::Sent)
                ->where('occurred_at', '>=', $since)
                ->distinct()
                ->count('contact_id'),
            'replies_30_days' => $this->realReplies()
                ->where('created_at', '>=', $since)
                ->distinct()
                ->count('contact_id'),
            'bounces_30_days' => EmailEvent::query()
                ->where('event_type', EmailEventType::Bounced)
                ->where('occurred_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * Campaign emails sent on each of the last days, in the outreach timezone.
     *
     * @return list<array{date: string, count: int}>
     */
    private function sendsByDay(): array
    {
        $timezone = config('outreach.timezone');
        $firstDay = now($timezone)->startOfDay()->subDays(self::CHART_DAYS - 1);

        $counts = EmailEvent::query()
            ->where('event_type', EmailEventType::Sent)
            ->where('occurred_at', '>=', $firstDay->utc())
            ->pluck('occurred_at')
            ->countBy(fn ($occurredAt): string => $occurredAt->setTimezone($timezone)->toDateString());

        $days = [];

        for ($offset = 0; $offset < self::CHART_DAYS; $offset++) {
            $date = $firstDay->addDays($offset)->toDateString();
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }

    /**
     * Running and paused campaigns with their reach and results.
     *
     * @return list<array{id: int, name: string, status: 'active'|'completed'|'draft'|'paused', status_label: string, enrolled: int, contacted: int, sent: int, replied: int, bounced: int}>
     */
    private function campaigns(): array
    {
        return array_values(Campaign::query()
            ->whereIn('status', [CampaignStatus::Active, CampaignStatus::Paused])
            ->withCount([
                'enrollments',
                'enrollments as contacted_count' => fn (Builder $query) => $query->where('sequence_step', '>', 0),
                'enrollments as replied_count' => fn (Builder $query) => $query->where('stop_reason', 'replied'),
                'enrollments as bounced_count' => fn (Builder $query) => $query->where('stop_reason', 'bounced'),
            ])
            ->withSum('enrollments', 'sequence_step')
            ->orderBy('name')
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'enrolled' => $campaign->enrollments_count,
                'contacted' => (int) $campaign->getAttribute('contacted_count'),
                'sent' => (int) $campaign->getAttribute('enrollments_sum_sequence_step'),
                'replied' => (int) $campaign->getAttribute('replied_count'),
                'bounced' => (int) $campaign->getAttribute('bounced_count'),
            ])
            ->all());
    }

    /**
     * The latest real replies, newest first.
     *
     * @return list<array{id: int, contact: array{id: int, name: string}, subject: string|null, body: string|null, created_at: string|null}>
     */
    private function recentReplies(): array
    {
        return array_values($this->realReplies()
            ->with('contact:id,first_name,last_name,email')
            ->latest()
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Activity $reply): array => [
                'id' => $reply->id,
                'contact' => [
                    'id' => $reply->contact->id,
                    'name' => trim("{$reply->contact->first_name} {$reply->contact->last_name}") ?: (string) $reply->contact->email,
                ],
                'subject' => is_string($reply->payload['subject'] ?? null) ? $reply->payload['subject'] : null,
                'body' => is_string($reply->payload['body'] ?? null) ? $reply->payload['body'] : null,
                'created_at' => $reply->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Replies from people, not out-of-office auto-replies.
     *
     * @return Builder<Activity>
     */
    private function realReplies(): Builder
    {
        return Activity::query()
            ->where('type', ActivityType::EmailReplied)
            ->whereNull('payload->auto_reply');
    }
}
