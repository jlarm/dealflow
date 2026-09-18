# Dealflow Build Plan

Implementation plan for `docs/crm-data-model.md` and `docs/dealflow-overview.md`. It follows the `laravel-best-practices` skill and the conventions the starter kit already uses: `#[Fillable]`/`#[Hidden]` model attributes, a `casts()` method, form requests in `app/Http/Requests/<Area>`, actions in `app/Actions`, `Inertia::render` with lowercase page paths, Wayfinder for routes, and Pest feature tests.

## Resolved Decisions

| # | Decision | Choice |
|---|---|---|
| 1 | Who owns the data | One shared workspace: every verified user sees everything. `user_id` only records who did something (imports, activities). |
| 2 | CSV parsing | No `laravel-excel`. The file is streamed with the built-in `LazyCollection` and `fgetcsv`, then processed in chunks with `Bus::batch`. |
| 3 | Live import progress | Inertia v3 polling (`usePoll`). Add Reverb later only if polling isn't good enough. |
| 4 | Search | Indexed database filters first. Scout + Meilisearch is optional, in Phase 8. |
| 5 | Email provider | **Mailgun.** It's already in use, and Postmark doesn't allow cold or unsolicited outreach. Enrichment sits behind an `EnrichmentProvider` interface, with Apollo first. |

**New dependencies:** Phase 6 needs `symfony/mailgun-mailer` and `symfony/http-client` for Laravel's `mailgun` transport. Nothing else is added unless Phase 8 is approved.

## Changes to the Data Model

- **`campaign_steps`** (new table): `campaign_id`, `position`, `subject`, `body`, `delay_days`, plus a unique index on (`campaign_id`, `position`). The original model had nowhere to store the emails in a sequence.
- **`campaign_contact`**:
  - Add `next_send_at`, `completed_at`, `stopped_at`, `stop_reason` and timestamps.
  - Add a unique index on (`campaign_id`, `contact_id`) and an index on `next_send_at`.
- **`contacts`**:
  - `email` is a nullable unique column, stored lowercase. It's the key for de-duplication.
  - Add `unsubscribed_at` (the do-not-contact / CAN-SPAM list) and `email_status` (the result of email verification during enrichment).
  - Index (`status`, `score`).
- **`companies`**: `domain` is a nullable unique column (the key for matching). Add `enriched_at`.
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
   - The batch's `finally` callback sets the import status. Its `then` callback dispatches enrichment.
3. `ImportContactsChunk`:
   - Validate each row. Failed rows go to `import_failures`.
   - Normalize email and domain. Match companies by domain, then contacts by email.
   - Write with `upsert` / `createOrFirst`, which rely on the unique indexes, so concurrent chunk jobs can't create duplicates.
   - Update the counters with `increment()`.
   - Keep `$timeout` below the queue's `retry_after` of 90 seconds.
4. The `imports/show` page polls every 2 seconds until the import is finished, showing a progress bar and a failures table.

## Phase 5: Enrichment

- An `app/Contracts/EnrichmentProvider` interface with an `ApolloEnrichmentProvider` implementation, bound in `AppServiceProvider`. The key lives in `config/services.php` under `apollo`, read from the environment.
- HTTP calls use `connectTimeout(3)->timeout(10)`, retry only on connection errors, 5xx and 429, and call `throw()`.
- `EnrichCompany` job:
  - `ShouldBeUnique` on the company ID.
  - `RateLimited('enrichment')` middleware, with `$tries` / `$backoff`.
  - Skips any company that already has `enriched_at`.
  - Stores the raw response in `enrichment_data` and fills in `industry` / `size`.

## Phase 6: Campaigns and Sending (Mailgun)

- Install `symfony/mailgun-mailer` and `symfony/http-client`.
- Add a `mailgun` block to `config/services.php`: `domain`, `secret`, `endpoint`, `webhook_signing_key`, `scheme`. Values come from the environment.
- Campaign CRUD, nested `campaigns.steps` routes (`scopeBindings`), and `CampaignEnrollmentController` (bulk-enroll a filtered contact set in chunks; remove an enrollment).
- Scheduled `campaigns:send-due` runs every 5 minutes with `withoutOverlapping()`. It walks `dueForSend()` enrollments in active campaigns with `chunkById` and dispatches `SendCampaignStep` for each.
- `SendCampaignStep`:
  - Re-checks that the contact is still contactable and the enrollment is still active.
  - Sends `CampaignStepMail` with:
    - a signed unsubscribe URL and a `List-Unsubscribe` header
    - `Reply-To` on the inbound domain
    - an `X-Mailgun-Variables` header carrying the `enrollment_id` and `step`
  - Records the `Message-Id` from the sent message, writes the "email sent" activity and updates `last_contacted_at`.
  - Advances `sequence_step` / `next_send_at`, or sets `completed_at` after the last step.
  - Is idempotent: a unique lock on enrollment + step.
- `UnsubscribeController` sits behind a `signed` route and sets `unsubscribed_at`.

## Phase 7: Mailgun Webhooks and Reply Detection

- **Event webhooks**: `POST /webhooks/mailgun/events`.
  - Registered outside the `web` group (no CSRF), with a throttle.
  - `VerifyMailgunSignature` middleware:
    - Computes an HMAC-SHA256 of `timestamp.token` with the webhook signing key and compares it using `hash_equals`.
    - Rejects stale timestamps.
    - Stores each token in the cache briefly and rejects reuse.
  - The controller stores the raw event (deduplicated by `event-data.id`), returns 200 immediately and dispatches `ProcessEmailEvent`.
  - Mailgun event mapping:

    | Mailgun event | Stored as |
    |---|---|
    | `delivered` | delivered |
    | `opened` | opened |
    | `clicked` | clicked |
    | `failed` with `severity=permanent` | bounced |
    | `complained` | complained |
    | `unsubscribed` | unsubscribe |

- **Reply detection**: Mailgun has no "replied" event, so replies arrive through **inbound Routes**.
  - A Mailgun Route forwards mail for the reply-to domain to `POST /webhooks/mailgun/inbound`, using the same signature check.
  - The reply is matched to the original send through the `In-Reply-To` / `References` headers and the stored `message_id`. If that fails, it falls back to the sender's email.
- `ProcessEmailEvent`:
  - A reply sets the status to `replied` (through `ChangeContactStatus`) and stops the enrollment.
  - A bounce or complaint sets `email_status` and stops the enrollment.
  - An unsubscribe sets `unsubscribed_at`.
  - Opens and clicks write activities and optionally bump `score`.
  - It can be re-run safely from stored events.

## Phase 8 (optional): Search and Dashboard

- Scout + Meilisearch on `Contact` (name, email, company, title), once approved.
- Dashboard: pipeline counts by status, reply rate per campaign (conditional `withCount`) and recent imports.

---

## Testing

Pest feature tests per phase, using factory states:

- **Status changes**: the status-change action writes an activity, and invalid transitions are rejected.
- **Imports**: `Storage::fake` + `Bus::assertBatched`. The chunk job gets a fixture CSV with duplicates and bad rows, and the tests assert on de-duplication and `import_failures`.
- **Enrichment**: `Http::preventStrayRequests()` + `Http::fake`, covering connection-failure and 429 paths.
- **Sending**: `Mail::fake`. Unsubscribed contacts are skipped, the step advances, and a retry doesn't send twice.
- **Webhooks**:
  - A bad or stale signature is rejected, and a reused token is rejected.
  - A duplicate event is ignored.
  - An inbound reply stops the sequence.
- **Access**: guests are redirected and unverified users are blocked.

Run `vendor/bin/pint --dirty --format agent` after PHP changes. Larastan (`phpstan.neon`) is available for static analysis.
