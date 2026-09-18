<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignStep;
use App\Models\CampaignEnrollment;
use App\Services\OutreachSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:send-due')]
#[Description('Queue the campaign emails that are due, within the sending window and daily limit')]
class SendDueCampaignSteps extends Command
{
    public function handle(OutreachSchedule $schedule): int
    {
        if (! $schedule->isOpen()) {
            $this->components->info('Outside the sending window, nothing queued.');

            return self::SUCCESS;
        }

        $remaining = $schedule->remainingToday();

        if ($remaining === 0) {
            $this->components->info('The daily sending limit has been reached.');

            return self::SUCCESS;
        }

        $enrollments = CampaignEnrollment::query()
            ->dueForSend()
            ->orderBy('next_send_at')
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        $enrollments->each(fn (CampaignEnrollment $enrollment) => SendCampaignStep::dispatch($enrollment));

        $this->components->info("Queued {$enrollments->count()} campaign emails.");

        return self::SUCCESS;
    }
}
