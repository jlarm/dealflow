# Dealflow Build Checklist

Progress tracker for `docs/build-plan.md`. Items are checked off as they are completed and their tests pass.

## Phase 0: Foundations
- [x] Turn on `Model::shouldBeStrict()` outside production in `AppServiceProvider`
- [x] Enums: `ContactStatus`, `CampaignStatus`, `ActivityType`, `EmailEventType`, `ImportStatus` (with `label()`; `ContactStatus` also gets `color()`)
- [ ] Top bar nav (`resources/js/lib/navigation.ts`): each item is added in the phase that creates its routes (Contacts/Companies/Tags done in 2, Pipeline in 3, Imports in 4; Campaigns in 6)

## Phase 1: Schema, Models, Factories
- [x] `companies` migration, model, factory (`enriched()` state)
- [x] `contacts` migration, model, factory (`withStatus()`, `unsubscribed()` states)
- [x] `tags` + `contact_tag` migrations, model, factory
- [x] `campaigns` migration, model, factory
- [x] `campaign_steps` migration, model, factory
- [x] `campaign_contact` migration + `CampaignEnrollment` pivot model
- [x] `activities` migration, model, factory (no `updated_at`)
- [x] `email_events` migration, model, factory
- [x] `imports` + `import_failures` migrations, models, factories
- [x] Relationships, casts, `#[Fillable]`, scopes (`withStatus`, `contactable`, `dueForSend`)
- [x] Seeder: ~500 contacts across ~50 companies (`CrmSeeder`)
- [x] `EmailStatus` enum (deliverability) + `withEmailStatus()` / `withoutEmail()` contact states
- [x] Model tests passing

## Phase 2: Contacts, Companies, Tags, Timeline
- [x] `ChangeContactStatus` action (status change + activity in one transaction)
- [x] `LogActivity` action
- [x] Contacts resource: index (filters, allow-listed sort, pagination), show, create, edit, delete
- [x] Contact timeline (deferred prop + skeleton)
- [x] `ContactStatusController@update`
- [x] `ContactActivityController@store` (notes, calls)
- [x] `ContactTagController` (sync)
- [x] Companies resource
- [x] Tags management
- [x] Sidebar nav: Contacts, Companies, Tags
- [x] Feature tests passing
- [x] Manual browser check of the pages

## Branding and Cleanup (between Phases 2 and 3)
- [x] Fix `UserFactory::withTwoFactor()` (empty starter-kit method)
- [x] Switch the app layout from sidebar to top bar
- [x] DealFlow logo, favicons, and app name
- [x] `/` redirects to the dashboard (internal tool, no public homepage)

## Phase 3: Pipeline Kanban
- [x] `PipelineController@index` (top N per status + grouped counts)
- [x] Kanban page with a column per status
- [x] Infinite scroll / load more per column (cursor pagination, so moving cards never skips one)
- [x] Drag-to-move with optimistic update + rollback
- [x] "Move to" menu on each card (keyboard and touch alternative to dragging)
- [x] Pipeline added to the top bar nav
- [x] Feature tests passing

## Phase 4: CSV Import
- [x] `ImportStoreRequest` + `ImportController` (store file, create import, dispatch job)
- [x] `ProcessImport` job (stream CSV, map headers incl. common aliases, resolve companies, batch chunks)
- [x] `ImportContactsChunk` job (validate, normalize, dedupe by email, fill blanks only, counters, failures; one transaction per chunk)
- [x] Batch `finally` callback marks the import completed/failed; uploaded file deleted after reading
- [x] Imports index + upload page
- [x] Import show page with polling progress bar + failures table
- [x] Imports added to the top bar nav
- [x] Feature tests passing (fixture CSV with duplicates and bad rows)

## Phase 5: Contact List Import (Apollo exports + dealer lists)
- [x] Full Apollo export header row
- [x] Decisions: Seniority/Departments as contact fields; Apollo Lists as tags; ignore Apollo Stage/Replied/Last Contacted; campaigns send to verified emails only
- [x] Email status mapping from Email Status + Catch-all Status + Email Bounced (unknown values count as risky)
- [x] Column aliases for both formats (dealership, public email, Apollo phones, company location, ids)
- [x] Companies: `city`, `state`, `phone`, `apollo_account_id`; Apollo company data into `enrichment_data`
- [x] Company matching: Apollo Account Id, then domain, then name + state
- [x] Contacts: `seniority`, `departments`, `apollo_contact_id`; match by email, then Apollo Contact Id
- [x] Apollo Lists → tags
- [x] `ActivityType::Imported` timeline entry with source list and research details
- [x] Show city/state on companies; seniority/departments on contacts; filter contacts by state and seniority
- [x] State names normalized to two-letter codes (Apollo uses "Texas", dealer lists use "TX")
- [x] Tests with fixtures using the real dealer-list and Apollo header rows

## Phase 6: Campaigns and Sending (Mailgun)
- [ ] `contactable()` only allows valid (verified, non-catch-all) email statuses
- [ ] Install `symfony/mailgun-mailer` + `symfony/http-client` (approved)
- [ ] `services.mailgun` config + `.env.example` entries
- [ ] Campaigns resource (CRUD, status)
- [ ] Campaign steps (nested, scoped bindings, ordering)
- [ ] `CampaignEnrollmentController` (bulk enroll a filtered contact set, remove)
- [ ] `CampaignStepMail` mailable (unsubscribe link, `List-Unsubscribe`, `Reply-To`, `X-Mailgun-Variables`)
- [ ] `SendCampaignStep` job (re-check contactable, send, record `message_id`, advance step, idempotent)
- [ ] `campaigns:send-due` scheduled command (`withoutOverlapping`)
- [ ] `UnsubscribeController` (signed route)
- [ ] Feature tests passing

### AI drafting with Claude
- [ ] Claude API client (package needs approval) + `services.anthropic` config + `.env.example` entries
- [ ] `ai_conversations` + `ai_messages` migrations, models, factories (conversation belongs to a campaign step, so the back-and-forth is saved and can be picked up later)
- [ ] System prompt built from campaign context: target audience, step number, earlier steps' content, available merge fields
- [ ] Claude returns a structured draft (subject + body) alongside its chat reply
- [ ] `CampaignStepDraftController` (start a conversation, send a follow-up message asking for changes)
- [ ] Chat panel on the step edit page (message history, input, streamed replies, loading state)
- [ ] "Use this draft" fills the step's subject/body; still editable by hand afterwards
- [ ] Reject drafts that use merge fields that don't exist
- [ ] Feature tests passing (Claude responses faked)

### Draft checks with Jev (TypeSafe)
- [ ] `services.typesafe` config + `.env.example` entries; call the HTTP API with Laravel's `Http` client (no new package)
- [ ] Checks on a step's subject/body: spammy language, clear call to action, tone fits the audience, reads as personal rather than mass mail
- [ ] Show results as warnings on the step edit page, using probabilities with thresholds (don't block saving)
- [ ] Re-run checks when a Claude draft is applied or the step is edited
- [ ] Feature tests passing (Jev responses faked)

## Phase 7: Mailgun Webhooks and Reply Detection
- [ ] `VerifyMailgunSignature` middleware (HMAC, timestamp freshness, token reuse check)
- [ ] `POST /webhooks/mailgun/events` (store with dedupe, dispatch job)
- [ ] `POST /webhooks/mailgun/inbound` (reply matching via `In-Reply-To`)
- [ ] `ProcessEmailEvent` job (reply / bounce / complaint / unsubscribe / open / click handling)
- [ ] Mailgun dashboard: webhooks + inbound Route set up (manual)
- [ ] Feature tests passing

## Phase 8 (optional): Search and Dashboard
- [ ] Scout + Meilisearch on `Contact` (approved)
- [ ] Dashboard: pipeline counts, campaign reply rates, recent imports

## Deliverability (manual)
- [ ] Separate sending subdomain
- [ ] SPF, DKIM and DMARC records set up
