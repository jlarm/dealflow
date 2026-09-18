# Dealflow Build Plan

Implementation plan for `docs/crm-data-model.md` and `docs/dealflow-overview.md`. It follows the `laravel-best-practices` skill and the conventions the starter kit already uses: `#[Fillable]`/`#[Hidden]` model attributes, a `casts()` method, form requests in `app/Http/Requests/<Area>`, actions in `app/Actions`, `Inertia::render` with lowercase page paths, Wayfinder for routes, and Pest feature tests.

## Resolved Decisions

| # | Decision | Choice |
|---|---|---|
| 1 | Who owns the data | One shared workspace: every verified user sees everything. `user_id` only records who did something (imports, activities). |
| 2 | CSV parsing | No `laravel-excel`. The file is streamed with the built-in `LazyCollection` and `fgetcsv`, then processed in chunks with `Bus::batch`. |
| 3 | Live import progress | Inertia v3 polling (`usePoll`). Add Reverb later only if polling isn't good enough. |
| 4 | Search | Indexed database filters first. Scout + Meilisearch is optional, in Phase 8. |
| 5 | Email provider | **Mailgun.** It's already in use, and Postmark doesn't allow cold or unsolicited outreach. |
| 6 | Enrichment | **No third-party enrichment API.** Contact lists are Apollo exports and researched dealer lists, so the data comes in through the CSV instead. Phase 5 makes the importer understand that format. An email verification service (ZeroBounce, NeverBounce) can be added later if unverified addresses turn out to matter. |

**New dependencies:** Phase 6 needs `symfony/mailgun-mailer` and `symfony/http-client` for Laravel's `mailgun` transport. Nothing else is added unless Phase 8 is approved.

## Changes to the Data Model

- **`campaign_steps`** (new table): `campaign_id`, `position`, `subject`, `body`, `delay_days`, plus a unique index on (`campaign_id`, `position`). The original model had nowhere to store the emails in a sequence.
- **`campaign_contact`**:
  - Add `next_send_at`, `completed_at`, `stopped_at`, `stop_reason` and timestamps.
  - Add a unique index on (`campaign_id`, `contact_id`) and an index on `next_send_at`.
- **`contacts`**:
  - `email` is a nullable unique column, stored lowercase. It's the key for de-duplication.
  - Add `unsubscribed_at` (the do-not-contact / CAN-SPAM list) and `email_status` (from the list's Email Status column, and later from bounces).
  - Index (`status`, `score`).
- **`companies`**: `domain` is a nullable unique column (the key for matching). Add `enriched_at`. Phase 5 adds `city` and `state`, because dealer lists identify a dealership by name and location, not by website.
- **`email_events`**:
  - Add a unique `provider_event_id` (Mailgun `event-data.id`) so resent webhooks aren't stored twice.
  - Add `payload` (JSON) so events can be reprocessed.
  - Index `message_id`.
  - Linking an event to its send: `message_id` is also stored in the "email sent" activity's payload.
- **`activities`**:
  - Add a nullable `user_id` for who did it.
  - Index (`contact_id`, `created_at`).
  - The model sets `UPDATED_AT = null`, because rows are only ever added.
- **`imports`**:
  - Add `user_id`, the stored file `path`, `source_list`, `processed_rows`, `failed_rows` and `updated_at`.
  - Per-row errors go in a new **`import_failures`** table (`import_id`, `row_number`, `errors`, `raw_row`) instead of an `error_log` JSON column. Several chunk jobs writing to one JSON column at once would overwrite each other.
- **Statuses**: string columns backed by PHP string enums, not database enums. The enums are `ContactStatus`, `CampaignStatus`, `ActivityType`, `EmailEventType` and `ImportStatus`, cast on the models.

---

## Phase 0: Foundations

- In `AppServiceProvider::configureDefaults()`, turn on `Model::shouldBeStrict(! app()->isProduction())`.
- Create the enums in `app/Enums`. Each gets `label()`, and `ContactStatus` also gets `color()` for the UI.
- Sidebar nav: Pipeline, Contacts, Companies, Campaigns, Imports.

## Phase 1: Schema, Models, Factories

- Run `make:model -mfs` for Company, Contact, Campaign, CampaignStep, Activity, EmailEvent, Import, ImportFailure and Tag. Run `make:migration` for the `campaign_contact` and `contact_tag` pivots.
- Foreign-key delete behaviour:
  - `contacts.company_id`: `nullOnDelete`
  - activities, email events and pivot rows: `cascadeOnDelete`
  - `email_events.activity_id`: `nullOnDelete`
- Models:
  - Typed relationships and `#[Fillable]`.
  - A `CampaignEnrollment` pivot model via `->using()->withPivot()->withTimestamps()`.
  - `#[Scope]` scopes: `withStatus()`, `contactable()` (not unsubscribed and not bounced), and `dueForSend()` on enrollments.
- Factories get states such as `withStatus()`, `unsubscribed()` and `enriched()`. The seeder creates about 500 contacts across 50 companies.

## Phase 2: Contacts, Companies, Tags, Timeline

- Routes: `Route::resource` for `contacts` and `companies`, and `tags` without `show`.
- Actions:
  - `ChangeContactStatus`: updates the status and writes a status-change activity (old and new status) in one `DB::transaction`.
  - `LogActivity`: the only way activities get written.
- **Contacts index**:
  - Filters: status, tag, company, source list, text.
  - Sort columns come from an allow-list, with `id` as the tie-breaker.
  - Paginated, eager-loading `company:id,name` and `tags`.
- **Contact show**: the timeline is a paginated activity list, newest first, loaded as a deferred prop with a skeleton.
- Separate controllers instead of custom actions: `ContactStatusController@update`, `ContactActivityController@store` (notes and calls) and `ContactTagController`.
- Form requests on every write, passing on only `validated()` / `safe()->only()` data. Routes are protected by `auth` + `verified`.

## Phase 3: Pipeline Kanban

- `PipelineController@index` shows one column per status:
  - Each column holds the top N contacts by `score desc, id desc`.
  - Column totals come from one `groupBy('status')` count query.
- Each column uses Inertia v3 infinite scroll and merge props for "load more".
- Dragging a card calls `ContactStatusController@update` through Wayfinder with an optimistic update, which Inertia rolls back automatically if the server rejects it.

## Phase 4: CSV Import

1. `ImportController@store`:
   - `StoreImportRequest` checks `file`, `mimes:csv,txt`, `max:20480` and a required `source_list`.
   - Save the file with `->store('imports')` on the private disk.
   - Create the `Import` row (`processing`) and dispatch `ProcessImport`, then redirect to `imports.show`.
2. `ProcessImport`:
   - Stream the file with `LazyCollection` and map the header row.
   - Split rows into chunks of 500 and put them in a `Bus::batch` of `ImportContactsChunk` jobs with `allowFailures()`.
   - The batch's `finally` callback sets the import status.
3. `ImportContactsChunk`:
   - Validate each row. Failed rows go to `import_failures`.
   - Normalize email and domain. Match companies by domain, then contacts by email.
   - Write with `upsert` / `createOrFirst`, which rely on the unique indexes, so concurrent chunk jobs can't create duplicates.
   - Update the counters with `increment()`.
   - Keep `$timeout` below the queue's `retry_after` of 90 seconds.
4. The `imports/show` page polls every 2 seconds until the import is finished, showing a progress bar and a failures table.

## Phase 5: Dealer List Import

Contact lists come in two formats, and the importer needs to handle both fully instead of calling an enrichment API:

- **Researched dealer lists:** State, City, Dealership / Group, Contact Name, Title, Public Email, Email Status, Source Type, Source URL, Research Date, Notes.
- **Apollo exports:** First Name, Last Name, Title, Company Name, Company Name for Emails, Email, Email Status, Primary Email Source, Primary Email Verification Source, Email Confidence, Primary Email Catch-all Status, Primary Email Last Verified At, Seniority, Departments, Sub Departments, and more columns still to be confirmed (phones, employees, industry, LinkedIn, website, location).

- **Column aliases:** "Dealership / Group" maps to company and "Public Email" maps to email. "Contact Name" and "Title" already map.
- **Email Status:** mapped to `email_status`. The exact values in both formats need confirming before the mapping is written. For Apollo, Email Status and Catch-all Status are combined: a verified address on a catch-all domain is risky, not valid.
- **Apollo columns:**
  - **Phones:** the first filled of Work Direct, Mobile, Corporate, Other and Home becomes the contact's phone. Company Phone goes on the company.
  - **Location:** Company City and Company State are the dealership's location. They share the company `city` and `state` fields with the dealer lists' City and State.
  - **Company data:** Keywords, Technologies, Annual Revenue, funding, SIC/NAICS codes, Number of Retail Locations, Company LinkedIn and Parent company are stored raw in `companies.enrichment_data`.
  - **Ids:** Apollo Contact Id and Apollo Account Id are stored, so people and companies can be matched again without an email or website.
  - **Seniority and Departments** become contact fields that can be filtered.
  - **Lists** becomes tags.
  - **Email Bounced** marks the contact bounced.
  - **Ignored:** Stage, Replied and Last Contacted. DealFlow tracks the pipeline itself.
- **States:** stored as two-letter codes. Apollo writes full names ("Texas") and the dealer lists use codes ("TX"), so both are normalized to codes, including in the company form.
- **Dealership location:**
  - Add `city` and `state` columns to `companies` and fill them from the list.
  - With no website column, companies without a domain are matched by name and state, so two dealerships with the same name in different states stay separate.
  - A domain is still taken from a work email address when there is one.
- **Research details:** each imported contact gets an "Imported" timeline entry, a new `ActivityType::Imported`. It holds the source list, Source Type, Source URL, Research Date and Notes, so you can see where a lead came from and what was known about them.
- **Existing contacts:** matching by email stays as it is, and only blank fields are filled in. A contact already in the database still gets an "Imported" entry when a new list contains them, so the timeline shows every list they appeared in.
- **Sending rule (decided): verified only.** Phase 6 changes the `contactable()` scope so campaigns only email addresses whose status is valid (verified and not catch-all).
- **UI:** show city and state on company pages and in the contact list, and let contacts be filtered by state.

## Phase 6: Campaigns and Sending (Mailgun)

- Install `symfony/mailgun-mailer` and `symfony/http-client`. Add the `mailgun` mailer, a `mailgun` block in `config/services.php` (domain, secret, endpoint, webhook signing key) and a global Reply-To in `config/mail.php`.
- **Sending rule:** `contactable()` only allows verified addresses (valid status) that haven't unsubscribed.
- **Sending safeguards** (`config/outreach.php`, set in `.env`): a daily limit across all campaigns, and a weekday business-hours window in a set timezone.
- Campaign CRUD and status changes. A campaign can't be activated without at least one email.
- **Campaign emails** are nested under the campaign with scoped bindings and appended in order. They can be edited any time and removed only while the campaign is a draft, since removing one later would shift contacts' place in the sequence.
- **Merge fields** such as `{{first_name}}` and `{{company}}`, with fallbacks like `{{first_name|there}}`.
- **Enrolling** happens from the contact list: filter it, then "Add to campaign". Only verified contacts are added, and duplicates are skipped.
- `CampaignStepMail`:
  - DealFlow sets its own `Message-ID` and stores it, for matching replies and events whichever provider sends.
  - One-click unsubscribe headers (`List-Unsubscribe` and `List-Unsubscribe-Post`).
  - Mailgun variables: `enrollment_id`, `campaign_id`, `contact_id`, `step`.
  - The body is escaped plain text shown as simple HTML.
- `SendCampaignStep` (unique per enrollment):
  - Re-checks that the campaign is active and the contact is still contactable. If the contact isn't, the enrollment is stopped.
  - Respects the daily limit.
  - Records the `sent` email event as soon as the email is accepted, so a retry finishes the bookkeeping without emailing twice.
  - Logs "Email Sent" and schedules the next email, or finishes the sequence.
- `campaigns:send-due` runs every five minutes (`withoutOverlapping`, `onOneServer`). It only queues inside the sending window, up to what's left of the daily limit, oldest-due first. The scheduler has to be running: `php artisan schedule:work` locally, or the platform's scheduler in production.
- **Unsubscribe:** opening the permanent signed link shows a confirmation page, because mail scanners follow links. Unsubscribing is a POST from that page or from a mail client's one-click request (CSRF-exempt, authenticated by the signature). It stops every campaign the contact is in and logs it on their timeline.

## Phase 7: Mailgun Webhooks and Reply Detection

- **`VerifyMailgunSignature`** checks an HMAC-SHA256 of `timestamp` + `token` against the webhook signing key, using `hash_equals`.
  - Timestamps more than 15 minutes old are rejected.
  - Each token is stored briefly in the cache, and a reused token is rejected.
  - It reads both the JSON format (event webhooks) and form fields (inbound Routes).
  - It fails closed: with no signing key configured, every webhook is rejected.
- **Webhook routes** live in `routes/webhooks.php`, are exempt from CSRF, and are throttled.
- **`POST /webhooks/mailgun/events`:**
  - Mailgun events map as follows: `delivered`, `opened` and `clicked` keep their names, a permanent `failed` becomes bounced, and `complained` and `unsubscribed` keep theirs. `accepted` and temporary failures are ignored.
  - The contact is found from the `contact_id` variable DealFlow attached when sending, then from the email's Message-ID (DealFlow's own, or the provider's), then from the recipient's address.
  - The raw event is stored, deduplicated by Mailgun's event id, and `ProcessEmailEvent` is queued. Mailgun always gets a 200.
- **`ProcessEmailEvent`:**
  - A bounce marks the address bounced, stops every campaign and logs it.
  - A spam complaint marks the address complained and unsubscribes the contact.
  - An unsubscribe unsubscribes the contact.
  - Only the first open or click of each email is logged, adding 1 or 2 points of score.
  - Everything can be re-run safely.
- **`POST /webhooks/mailgun/inbound`:** a Mailgun Route forwards mail sent to the Reply-To address. The sender, the ids in `In-Reply-To`/`References`, the reply text (quoted text removed) and auto-reply signals (`Auto-Submitted`, `Precedence`, "Out of Office" subjects) are passed to `ProcessInboundReply`.
- **`ProcessInboundReply`:**
  - The sender's address identifies the contact. The email being replied to is only used when the sender isn't a contact, such as an assistant.
  - A real reply stops every campaign the contact is in, and moves New or Contacted contacts to Replied. Contacts further along the pipeline keep their stage.
  - Auto-replies are logged but change nothing.
  - The same reply delivered twice is recorded once.

## Phase 8 (optional): Dashboard and Search

- **Dashboard (built):**
  - Stat tiles for contacts and how many are verified, emails sent in the last 7 days, and the 30-day reply rate.
  - The 30-day bounce rate, with a Healthy / Watch / Too high reading at 2% and 5%.
  - A meter of today's sends against the daily limit, showing whether the sending window is open.
  - A 14-day daily sends chart, bucketed by day in the outreach timezone.
  - Pipeline counts by stage.
  - Deferred sections: running campaign results (sent, replied, reply rate, bounced), the latest real replies (auto-replies excluded) and recent imports.
  - Charts use the brand teal, validated for light and dark surfaces.
- **Search:** Scout + Meilisearch on `Contact`, only if contact volume makes the indexed database filters too slow. It needs approval for new dependencies.

---

## Testing

Pest feature tests per phase, using factory states:

- **Status changes**: the status-change action writes an activity, and invalid transitions are rejected.
- **Imports**: `Storage::fake` + `Bus::assertBatched`. The chunk job gets a fixture CSV with duplicates and bad rows, and the tests assert on de-duplication and `import_failures`.
- **Contact lists**: fixtures with the real dealer-list and Apollo header rows, covering email status mapping, company matching by name and state, and the Imported timeline entry.
- **Sending**: `Mail::fake`. Unsubscribed contacts are skipped, the step advances, and a retry doesn't send twice.
- **Webhooks**:
  - A bad or stale signature is rejected, and a reused token is rejected.
  - A duplicate event is ignored.
  - An inbound reply stops the sequence.
- **Access**: guests are redirected and unverified users are blocked.

Run `vendor/bin/pint --dirty --format agent` after PHP changes. Larastan (`phpstan.neon`) is available for static analysis.
