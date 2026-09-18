<?php

use App\Models\CampaignStep;
use App\Models\Company;
use App\Models\Contact;

test('fills in merge fields from the contact and their company', function () {
    $step = CampaignStep::factory()->make([
        'subject' => '{{first_name}}, a question about {{ company }}',
        'body' => 'Hi {{full_name}} ({{title}}) in {{city}}, {{state}}.',
    ]);
    $contact = Contact::factory()->for(Company::factory()->state(['name' => 'Lakeside Ford', 'city' => 'Austin', 'state' => 'TX']))->create([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'title' => 'GM',
    ]);

    $email = $step->personalizeFor($contact);

    expect($email)->toBe([
        'subject' => 'Dana, a question about Lakeside Ford',
        'body' => 'Hi Dana Whitfield (GM) in Austin, TX.',
    ]);
});

test('uses the fallback when a merge field has no value', function () {
    $step = CampaignStep::factory()->make(['subject' => 'Hi {{first_name|there}}', 'body' => 'At {{company}}!']);
    $contact = Contact::factory()->create(['first_name' => null, 'company_id' => null]);

    $email = $step->personalizeFor($contact);

    expect($email)->toBe(['subject' => 'Hi there', 'body' => 'At !']);
});
