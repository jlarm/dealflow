<?php

namespace App\Http\Controllers;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContactActivityController extends Controller
{
    /**
     * Log a note or call on a contact's timeline.
     */
    public function store(Request $request, Contact $contact, LogActivity $logActivity): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(ActivityType::class)->only(ActivityType::loggable())],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $type = ActivityType::from($validated['type']);

        $logActivity->handle($contact, $type, ['body' => $validated['body']], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type logged.', ['type' => $type->label()])]);

        return back();
    }
}
