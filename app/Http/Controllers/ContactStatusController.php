<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\ChangeContactStatus;
use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactStatusController extends Controller
{
    /**
     * Move a contact to a different pipeline stage.
     */
    public function update(Request $request, Contact $contact, ChangeContactStatus $changeContactStatus): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContactStatus::class)],
        ]);

        $changeContactStatus->handle($contact, ContactStatus::from($validated['status']), $request->user());

        return back();
    }
}
