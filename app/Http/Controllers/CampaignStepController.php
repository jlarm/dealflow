<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\CampaignStepStoreRequest;
use App\Http\Requests\CampaignStepUpdateRequest;
use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignStepController extends Controller
{
    /**
     * Add an email to the end of the sequence.
     */
    public function store(CampaignStepStoreRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->steps()->create([
            ...$request->validated(),
            'position' => (int) $campaign->steps()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email added.')]);

        return back();
    }

    /**
     * Edit an email. Changes apply to every email not yet sent.
     */
    public function update(CampaignStepUpdateRequest $request, Campaign $campaign, CampaignStep $step): RedirectResponse
    {
        $step->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email saved.')]);

        return back();
    }

    /**
     * Remove an email while the campaign is a draft, renumbering the ones after it.
     * Once a campaign has sent, removing an email would shift contacts' place in the sequence.
     */
    public function destroy(Campaign $campaign, CampaignStep $step): RedirectResponse
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'step' => __('Emails can only be removed while the campaign is a draft.'),
            ]);
        }

        DB::transaction(function () use ($campaign, $step): void {
            $step->delete();

            $campaign->steps()
                ->where('position', '>', $step->position)
                ->get()
                ->each(fn (CampaignStep $later) => $later->update(['position' => $later->position - 1]));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email removed.')]);

        return back();
    }
}
