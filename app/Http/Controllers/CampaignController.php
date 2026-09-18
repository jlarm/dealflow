<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\EmailEventType;
use App\Http\Requests\CampaignStoreRequest;
use App\Http\Requests\CampaignUpdateRequest;
use App\Http\Resources\CampaignEnrollmentResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CampaignStepResource;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\EmailEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Show every campaign with its size, newest first.
     */
    public function index(): Response
    {
        $campaigns = Campaign::query()
            ->withCount(['steps', 'enrollments', 'activeEnrollments'])
            ->latest()
            ->latest('id')
            ->paginate(20);

        return Inertia::render('campaigns/index', [
            'campaigns' => CampaignResource::collection($campaigns),
        ]);
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(): Response
    {
        return Inertia::render('campaigns/create');
    }

    /**
     * Store a new draft campaign.
     */
    public function store(CampaignStoreRequest $request): RedirectResponse
    {
        $campaign = Campaign::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created. Add its emails next.')]);

        return to_route('campaigns.show', $campaign);
    }

    /**
     * Show a campaign's emails, enrollments, and sending stats.
     */
    public function show(Campaign $campaign): Response
    {
        $campaign->loadCount([
            'enrollments',
            'activeEnrollments',
            'enrollments as completed_enrollments_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
            'enrollments as stopped_enrollments_count' => fn (Builder $query) => $query->whereNotNull('stopped_at'),
        ]);

        $enrollments = $campaign->enrollments()
            ->with('contact:id,first_name,last_name,email')
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->paginate(25);

        return Inertia::render('campaigns/show', [
            'campaign' => new CampaignResource($campaign),
            'steps' => CampaignStepResource::collection($campaign->steps()->get()),
            'enrollments' => CampaignEnrollmentResource::collection($enrollments),
            'stats' => [
                'enrolled' => $campaign->enrollments_count,
                'active' => $campaign->active_enrollments_count,
                'completed' => $campaign->completed_enrollments_count,
                'stopped' => $campaign->stopped_enrollments_count,
                'sent' => EmailEvent::query()
                    ->where('event_type', EmailEventType::Sent)
                    ->where('payload->campaign_id', $campaign->id)
                    ->count(),
            ],
            'statuses' => CampaignStatus::options(),
            'mergeFields' => CampaignStep::MERGE_FIELDS,
        ]);
    }

    /**
     * Show the form for renaming a campaign.
     */
    public function edit(Campaign $campaign): Response
    {
        return Inertia::render('campaigns/edit', [
            'campaign' => new CampaignResource($campaign),
        ]);
    }

    /**
     * Rename a campaign.
     */
    public function update(CampaignUpdateRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('campaigns.show', $campaign);
    }

    /**
     * Delete a campaign with its emails and enrollments. Sent emails stay on contact timelines.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('campaigns.index');
    }
}
