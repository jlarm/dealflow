# CRM Core Data Model

Laravel + Inertia + React CRM for contact management, lead tracking, and cold outreach.

## Tables

### `contacts`
```
id
company_id (nullable, FK)
first_name
last_name
email
phone
title
linkedin_url
source_list       // which original CSV it came from
status             // enum: new, contacted, replied, qualified, meeting_booked, won, lost
score              // int, for prioritization
last_contacted_at
created_at / updated_at
```

### `companies`
```
id
name
domain
industry
size               // employee count bucket
enrichment_data    // json — raw Apollo/Clearbit payload, avoid over-normalizing this
created_at / updated_at
```

### `campaigns`
```
id
name
status             // draft, active, paused, completed
created_at / updated_at
```

### `campaign_contact` (pivot)
```
id
campaign_id
contact_id
sequence_step      // which email in the sequence they're on
enrolled_at
```

### `activities`
The timeline/log — makes the app feel like a real CRM.
```
id
contact_id
type               // email_sent, email_opened, email_replied, note, status_change, call
payload            // json — subject/body snippet, old/new status, etc.
created_at
```

### `email_events`
From ESP webhooks (Postmark/SendGrid).
```
id
activity_id (FK, nullable)
contact_id
message_id         // ESP's ID, for matching webhook events
event_type         // sent, delivered, opened, clicked, bounced, complained
occurred_at
```

### `imports`
Tracks each CSV upload as its own record.
```
id
filename
row_count
status             // processing, completed, failed
error_log          // json, per-row failures
created_at
```

### `tags` + `contact_tag` (pivot)
For segmentation beyond `source_list` (industry vertical, dealership size bucket, etc.)

## Key Relationships
- `Company hasMany Contacts`
- `Contact hasMany Activities`
- `Contact belongsToMany Campaigns` through `campaign_contact`
- `Contact hasMany EmailEvents`
- `Contact belongsToMany Tags`

## Design Notes

- **`status` on contacts is the pipeline stage** — this is what the Inertia+React kanban board renders off of. Keep it a simple enum/string column, not a separate `pipeline_stages` table, unless custom/configurable stages per campaign are needed later.

- **`activities` is append-only** — never update/delete rows, just insert. This becomes the contact detail timeline for free.

- **`email_events` is separate from `activities`** because ESP webhooks fire independently and you'll want to backfill/reprocess without touching the main activity log. Create a matching `activity` row when an important event lands (reply, bounce).

- **Import as a queued job**: `imports` table gets a row immediately, then a queued job (`laravel-excel` chunked reader) processes rows, creates/updates `contacts` + `companies`, and updates `imports.status` when done. Broadcast progress over a websocket/Echo channel for a live progress bar in React.

- **Enrichment** (Apollo/Clearbit) should also be queued, triggered after import — don't call it synchronously per-row during CSV parsing.
