# Dealflow

A CRM and lead-tracking web app for managing contact lists, running cold outreach, and tracking leads through a sales pipeline — built to replace scattered CSV exports with a single, structured source of truth.

## What It's For

Contact data usually starts as a pile of disconnected CSV exports — different formats, duplicate entries, no way to tell what's been touched or where a lead stands. Dealflow turns that into a working system:

- **Centralizes contacts** from multiple CSV sources into one deduplicated database
- **Tracks pipeline status** for every contact (New → Contacted → Replied → Qualified → Meeting Booked → Won/Lost)
- **Runs outreach campaigns** — enroll contacts in sequences and track which step they're on
- **Logs every interaction** — emails sent, opens, replies, notes, calls — as a running activity timeline per contact
- **Segments contacts** by tags, source list, industry, or company for targeted outreach instead of one-size-fits-all blasts

## What It Does

**Import & clean data**
CSV files are uploaded and processed in the background (queued jobs so large imports don't time out). Contacts and companies are created or matched against existing records, with per-row error tracking if something fails to import.

**Enrich contacts**
After import, contacts can be enriched with additional data (company size, industry, verified email status) via a third-party provider, keeping the raw enrichment payload for reference without over-normalizing it into rigid fields.

**Track a real pipeline**
Every contact has a status that reflects where they are in the sales process. This status drives a kanban-style board (built with Inertia + React) so leads can be visually moved through stages.

**Send and track outreach**
Contacts are enrolled in campaigns and stepped through email sequences. Sends, opens, clicks, replies, and bounces come back via webhook from the email provider (Postmark/SendGrid) and are logged against the contact automatically.

**Keep a full history**
Every meaningful event — status change, email sent, note added, call logged — is recorded as an append-only activity, giving a complete, chronological view of the relationship with each contact.

## Who It's For

Anyone doing volume-based cold outreach who needs more structure than a spreadsheet but doesn't want the overhead of a full enterprise CRM — sales teams, founders doing their own outbound, or anyone managing thousands of contacts across multiple lists who needs to know, at a glance, who's been contacted and what happened next.

## Tech Stack

- **Backend**: Laravel (queues for imports, enrichment, and email sending)
- **Frontend**: Inertia.js + React
- **Email delivery**: Postmark or SendGrid (webhook-driven event tracking)
- **Search**: Laravel Scout (Meilisearch/Typesense) for fast filtering across large contact volumes
