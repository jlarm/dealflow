<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignStatusController extends Controller
{
    /**
     * Activate, pause, or complete a campaign. Only active campaigns send email.
     */
    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);

        $status = CampaignStatus::from($validated['status']);

        if ($status === CampaignStatus::Active && ! $campaign->steps()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('Add at least one email before activating the campaign.'),
            ]);
        }

        $campaign->update(['status' => $status]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign :status.', ['status' => strtolower($status->label())])]);

        return back();
    }
}
