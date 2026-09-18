<?php

namespace App\Http\Controllers;

use App\Actions\Campaigns\EnrollContacts;
use App\Enums\CampaignStatus;
use App\Http\Requests\ContactFilterRequest;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignEnrollmentController extends Controller
{
    /**
     * Enroll every verified contact matching the contact list filters.
     */
    public function store(ContactFilterRequest $request, Campaign $campaign, EnrollContacts $enrollContacts): RedirectResponse
    {
        if ($campaign->status === CampaignStatus::Completed) {
            throw ValidationException::withMessages([
                'campaign' => __('Contacts cannot be added to a completed campaign.'),
            ]);
        }

        $enrolled = $enrollContacts->handle($campaign, Contact::query()->filter($request->filters()));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            ':count verified contact added to :campaign.|:count verified contacts added to :campaign.',
            $enrolled,
            ['campaign' => $campaign->name],
        )]);

        return back();
    }

    /**
     * Remove a contact from a campaign. Emails already sent stay on their timeline.
     */
    public function destroy(Campaign $campaign, CampaignEnrollment $enrollment): RedirectResponse
    {
        $enrollment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed from the campaign.')]);

        return back();
    }
}
