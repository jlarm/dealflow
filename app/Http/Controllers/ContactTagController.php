<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactTagController extends Controller
{
    /**
     * Replace the tags on a contact.
     */
    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists(Tag::class, 'id')],
        ]);

        $contact->tags()->sync($validated['tag_ids']);

        return back();
    }
}
