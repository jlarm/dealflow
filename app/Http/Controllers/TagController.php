<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagStoreRequest;
use App\Http\Requests\TagUpdateRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Show the tags with how many contacts use each.
     */
    public function index(): Response
    {
        return Inertia::render('tags/index', [
            'tags' => TagResource::collection(Tag::query()->withCount('contacts')->orderBy('name')->get()),
        ]);
    }

    /**
     * Store a newly created tag.
     */
    public function store(TagStoreRequest $request): RedirectResponse
    {
        Tag::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return back();
    }

    /**
     * Rename a tag.
     */
    public function update(TagUpdateRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag renamed.')]);

        return back();
    }

    /**
     * Delete a tag and remove it from every contact.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }
}
