<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\UnsubscribeContact;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public unsubscribe page linked from every campaign email.
 *
 * Opening the link only shows a confirmation, because mail scanners follow
 * links automatically. Unsubscribing takes a POST, either from the page's
 * button or from a mail client's one-click unsubscribe (RFC 8058).
 */
class UnsubscribeController extends Controller
{
    public function show(Contact $contact): Response
    {
        return Inertia::render('public/unsubscribe', [
            'email' => $this->maskEmail((string) $contact->email),
            'unsubscribed' => $contact->unsubscribed_at !== null,
            'action' => URL::signedRoute('unsubscribe.store', ['contact' => $contact]),
        ]);
    }

    public function store(Request $request, Contact $contact, UnsubscribeContact $unsubscribeContact): RedirectResponse|HttpResponse
    {
        $unsubscribeContact->handle($contact, [
            'via' => $request->header('X-Inertia') ? 'unsubscribe_page' : 'one_click',
        ]);

        return $request->header('X-Inertia')
            ? back()
            : response()->noContent();
    }

    /**
     * Show enough of the address to recognise it without exposing it, e.g. "ja***@acme.com".
     */
    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '';
        }

        return Str::mask(Str::before($email, '@'), '*', 2).'@'.Str::after($email, '@');
    }
}
