# Dealflow Build Checklist

Progress tracker for `docs/build-plan.md`. Items are checked off as they are completed and their tests pass.

## Phase 0: Foundations
- [ ] Turn on `Model::shouldBeStrict()` outside production in `AppServiceProvider`
- [ ] Enums: `ContactStatus`, `CampaignStatus`, `ActivityType`, `EmailEventType`, `ImportStatus` (with `label()`; `ContactStatus` also gets `color()`)
- [ ] Sidebar nav: Pipeline, Contacts, Companies, Campaigns, Imports

## Phase 1: Schema, Models, Factories
- [ ] `companies` migration, model, factory (`enriched()` state)
- [ ] `contacts` migration, model, factory (`withStatus()`, `unsubscribed()` states)
- [ ] `tags` + `contact_tag` migrations, model, factory
- [ ] `campaigns` migration, model, factory
- [ ] `campaign_steps` migration, model, factory
- [ ] `campaign_contact` migration + `CampaignEnrollment` pivot model
- [ ] `activities` migration, model, factory (no `updated_at`)
- [ ] `email_events` migration, model, factory
- [ ] `imports` + `import_failures` migrations, models, factories
- [ ] Relationships, casts, `#[Fillable]`, scopes (`withStatus`, `contactable`, `dueForSend`)
- [ ] Seeder: ~500 contacts across ~50 companies
- [ ] Model tests passing

## Phase 2: Contacts, Companies, Tags, Timeline
- [ ] `ChangeContactStatus` action (status change + activity in one transaction)
- [ ] `LogActivity` action
- [ ] Contacts resource: index (filters, allow-listed sort, pagination), show, create, edit, delete
- [ ] Contact timeline (deferred prop + skeleton)
- [ ] `ContactStatusController@update`
- [ ] `ContactActivityController@store` (notes, calls)
- [ ] `ContactTagController` (sync)
- [ ] Companies resource
- [ ] Tags management
- [ ] Feature tests passing

## Phase 3: Pipeline Kanban
- [ ] `PipelineController@index` (top N per status + grouped counts)
- [ ] Kanban page with a column per status
- [ ] Infinite scroll / load more per column
- [ ] Drag-to-move with optimistic update + rollback
- [ ] Feature tests passing

## Phase 4: CSV Import
- [ ] `StoreImportRequest` + `ImportController` (store file, create import, dispatch job)
- [ ] `ProcessImport` job (stream CSV, map headers, batch chunks)
- [ ] `ImportContactsChunk` job (validate, normalize, dedupe, `upsert`, counters, failures)
- [ ] Batch `finally` / `then` callbacks (status + trigger enrichment)
- [ ] Imports index + upload page
- [ ] Import show page with polling progress bar + failures table
- [ ] Feature tests passing (fixture CSV with duplicates and bad rows)

## Phase 5: Enrichment
- [ ] `EnrichmentProvider` interface + `ApolloEnrichmentProvider`, bound in `AppServiceProvider`
- [ ] `services.apollo` config + `.env.example` entries
- [ ] `enrichment` rate limiter
- [ ] `EnrichCompany` job (unique, rate limited, backoff, skips enriched)
- [ ] Tests with `Http::fake` (success, 429, connection failure)

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
