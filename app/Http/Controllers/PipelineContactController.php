<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\MoveContactOnBoard;
use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineContactController extends Controller
{
    /**
     * Place a contact where it was dropped on the board: in a stage, between two cards.
     */
    public function update(Request $request, Contact $contact, MoveContactOnBoard $moveContactOnBoard): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'above_id' => ['nullable', 'integer', Rule::exists(Contact::class, 'id')],
            'below_id' => ['nullable', 'integer', Rule::exists(Contact::class, 'id')],
        ]);

        $aboveId = $request->integer('above_id');
        $belowId = $request->integer('below_id');

        $moveContactOnBoard->handle(
            $contact,
            ContactStatus::from($validated['status']),
            $aboveId > 0 ? Contact::find($aboveId) : null,
            $belowId > 0 ? Contact::find($belowId) : null,
            $request->user(),
        );

        return back();
    }
}
