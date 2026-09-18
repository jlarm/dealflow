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

## Phase 5: Dealer List Import
- [ ] Confirm the values used in the Email Status column (and Source Type) with a sample
- [ ] Column aliases: "Dealership / Group" → company, "Public Email" → email
- [ ] Map Email Status → `email_status`
- [ ] `city` and `state` on companies, filled from the list
- [ ] Match domain-less companies by name + state
- [ ] `ActivityType::Imported` timeline entry with source list, Source Type, Source URL, Research Date, Notes
- [ ] Decide the sending rule for unverified emails (`contactable()` scope)
- [ ] Show city/state on companies and contacts; filter contacts by state
- [ ] Tests with a fixture using the real dealer-list header row

## Phase 6: Campaigns and Sending (Mailgun)
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
