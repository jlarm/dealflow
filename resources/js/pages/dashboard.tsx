import { Deferred, Head, Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck, TriangleAlert } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { show as showCampaign } from '@/actions/App/Http/Controllers/CampaignController';
import { show as showContact } from '@/actions/App/Http/Controllers/ContactController';
import { show as showImport } from '@/actions/App/Http/Controllers/ImportController';
import { index as pipelineIndex } from '@/actions/App/Http/Controllers/PipelineController';
import CampaignStatusBadge from '@/components/campaign-status-badge';
import ImportStatusBadge from '@/components/import-status-badge';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CampaignStatusValue, Import, StatusOption } from '@/types';

type Stats = {
    contacts: number;
    contactable: number;
    sent_7_days: number;
    sent_30_days: number;
    contacted_30_days: number;
    replies_30_days: number;
    bounces_30_days: number;
};

type Sending = {
    sent_today: number;
    daily_limit: number;
    window_open: boolean;
    window: string;
};

type CampaignSummary = {
    id: number;
    name: string;
    status: CampaignStatusValue;
    status_label: string;
    enrolled: number;
    contacted: number;
    sent: number;
    replied: number;
    bounced: number;
};

type Reply = {
    id: number;
    contact: { id: number; name: string };
    subject: string | null;
    body: string | null;
    created_at: string | null;
};

type DashboardProps = {
    stats: Stats;
    sending: Sending;
    sendsByDay: { date: string; count: number }[];
    pipeline: (StatusOption & { count: number })[];
    campaigns?: CampaignSummary[];
    recentReplies?: Reply[];
    recentImports?: Import[];
};

/** The brand teal, validated against the light and dark chart surfaces. */
const accent = 'bg-[#008F95] dark:bg-[#14A0A6]';

const numberFormat = new Intl.NumberFormat(undefined, { notation: 'compact' });
const formatNumber = (value: number) => numberFormat.format(value);

/** Format a share as a percentage, or a dash when there is nothing to measure. */
function formatRate(part: number, whole: number): string {
    if (whole === 0) {
        return '—';
    }

    const rate = (part / whole) * 100;

    return `${rate < 10 && rate > 0 ? rate.toFixed(1) : Math.round(rate)}%`;
}

export default function Dashboard({
    stats,
    sending,
    sendsByDay,
    pipeline,
    campaigns,
    recentReplies,
    recentImports,
}: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="sr-only">Dashboard</h1>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <StatTile
                        label="Contacts"
                        value={formatNumber(stats.contacts)}
                        hint={`${formatNumber(stats.contactable)} verified to email`}
                    />
                    <StatTile
                        label="Emails sent"
                        value={formatNumber(stats.sent_7_days)}
                        hint="Last 7 days"
                    />
                    <StatTile
                        label="Reply rate"
                        value={formatRate(
                            stats.replies_30_days,
                            stats.contacted_30_days,
                        )}
                        hint={`${stats.replies_30_days} of ${stats.contacted_30_days} contacts emailed, 30 days`}
                    />
                    <BounceTile
                        bounces={stats.bounces_30_days}
                        sent={stats.sent_30_days}
                    />
                    <SendingMeter sending={sending} />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <section className="rounded-xl border p-4 lg:col-span-3">
                        <SectionHeader
                            title="Campaign emails sent"
                            description="Per day, last 14 days"
                        />
                        <SendsChart days={sendsByDay} />
                    </section>

                    <section className="rounded-xl border p-4 lg:col-span-2">
                        <SectionHeader
                            title="Pipeline"
                            description="Contacts in each stage"
                            href={pipelineIndex.url()}
                            linkLabel="Open board"
                        />
                        <PipelineBars stages={pipeline} />
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <section className="rounded-xl border p-4 lg:col-span-3">
                        <SectionHeader
                            title="Running campaigns"
                            description="Active and paused campaigns"
                        />
                        <Deferred
                            data="campaigns"
                            fallback={<ListSkeleton rows={3} />}
                        >
                            {campaigns && (
                                <CampaignTable campaigns={campaigns} />
                            )}
                        </Deferred>
                    </section>

                    <section className="rounded-xl border p-4 lg:col-span-2">
                        <SectionHeader title="Latest replies" />
                        <Deferred
                            data="recentReplies"
                            fallback={<ListSkeleton rows={4} />}
                        >
                            {recentReplies && (
                                <ReplyList replies={recentReplies} />
                            )}
                        </Deferred>
                    </section>
                </div>

                <section className="rounded-xl border p-4">
                    <SectionHeader title="Recent imports" />
                    <Deferred
                        data="recentImports"
                        fallback={<ListSkeleton rows={2} />}
                    >
                        {recentImports && (
                            <ImportList imports={recentImports} />
                        )}
                    </Deferred>
                </section>
            </div>
        </>
    );
}

function SectionHeader({
    title,
    description,
    href,
    linkLabel,
}: {
    title: string;
    description?: string;
    href?: string;
    linkLabel?: string;
}) {
    return (
        <div className="mb-4 flex items-baseline justify-between gap-4">
            <div>
                <h2 className="font-semibold">{title}</h2>
                {description && (
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                )}
            </div>
            {href && (
                <Link
                    href={href}
                    className="text-muted-foreground shrink-0 text-sm hover:underline"
                >
                    {linkLabel}
                </Link>
            )}
        </div>
    );
}

function StatTile({
    label,
    value,
    hint,
    children,
}: {
    label: string;
    value: string;
    hint?: string;
    children?: ReactNode;
}) {
    return (
        <div className="space-y-1 rounded-xl border p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="text-2xl font-semibold tabular-nums">{value}</p>
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            {children}
        </div>
    );
}

type Health = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    className: string;
};

/**
 * Bounce rate with a status reading. Mail providers start filtering senders
 * whose bounce rate climbs past a few percent.
 */
function BounceTile({ bounces, sent }: { bounces: number; sent: number }) {
    const rate = sent === 0 ? 0 : (bounces / sent) * 100;
    const health: Health =
        rate > 5
            ? {
                  label: 'Too high',
                  icon: CircleAlert,
                  className: 'text-red-700 dark:text-red-400',
              }
            : rate > 2
              ? {
                    label: 'Watch',
                    icon: TriangleAlert,
                    className: 'text-amber-700 dark:text-amber-400',
                }
              : {
                    label: 'Healthy',
                    icon: CircleCheck,
                    className: 'text-green-700 dark:text-green-400',
                };

    return (
        <StatTile
            label="Bounce rate"
            value={formatRate(bounces, sent)}
            hint={`${bounces} of ${sent} sent, 30 days`}
        >
            {sent > 0 && (
                <p
                    className={cn(
                        'flex items-center gap-1 text-xs font-medium',
                        health.className,
                    )}
                >
                    <health.icon className="size-3.5" />
                    {health.label}
                </p>
            )}
        </StatTile>
    );
}

function SendingMeter({ sending }: { sending: Sending }) {
    const share =
        sending.daily_limit === 0
            ? 0
            : Math.min(100, (sending.sent_today / sending.daily_limit) * 100);

    return (
        <StatTile
            label="Sent today"
            value={`${sending.sent_today} / ${sending.daily_limit}`}
            hint={`${sending.window_open ? 'Sending now' : 'Paused until the window opens'} · ${sending.window}`}
        >
            <div
                className="mt-2 h-1.5 overflow-hidden rounded-full bg-[#008F95]/15 dark:bg-[#14A0A6]/20"
                role="meter"
                aria-label="Emails sent today against the daily limit"
                aria-valuenow={sending.sent_today}
                aria-valuemin={0}
                aria-valuemax={sending.daily_limit}
            >
                <div
                    className={cn('h-full rounded-full', accent)}
                    style={{ width: `${share}%` }}
                />
            </div>
        </StatTile>
    );
}

function SendsChart({ days }: { days: { date: string; count: number }[] }) {
    const max = Math.max(...days.map((day) => day.count), 0);
    const total = days.reduce((sum, day) => sum + day.count, 0);
    const label = (date: string) =>
        new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });

    if (total === 0) {
        return (
            <p className="text-muted-foreground flex h-40 items-center justify-center text-sm">
                No campaign emails sent in the last 14 days.
            </p>
        );
    }

    return (
        <figure>
            <div className="flex h-40 items-end gap-0.5 border-b">
                {days.map((day, index) => (
                    <div
                        key={day.date}
                        className="group relative flex h-full flex-1 items-end justify-center"
                        tabIndex={0}
                        aria-label={`${label(day.date)}: ${day.count} sent`}
                    >
                        <div
                            className={cn(
                                'w-full max-w-6 rounded-t-[4px] transition-opacity group-hover:opacity-80',
                                accent,
                            )}
                            style={{
                                height:
                                    day.count === 0
                                        ? 0
                                        : `max(2px, ${(day.count / max) * 100}%)`,
                            }}
                        />
                        <div
                            className={cn(
                                'bg-popover text-popover-foreground pointer-events-none absolute top-0 z-10 hidden rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-sm group-hover:block group-focus:block',
                                index < 3
                                    ? 'left-0'
                                    : index >= days.length - 3
                                      ? 'right-0'
                                      : 'left-1/2 -translate-x-1/2',
                            )}
                        >
                            {label(day.date)} · {day.count} sent
                        </div>
                    </div>
                ))}
            </div>
            <div className="text-muted-foreground mt-1.5 flex justify-between text-xs">
                <span>{label(days[0].date)}</span>
                <span>Busiest day: {max}</span>
                <span>{label(days[days.length - 1].date)}</span>
            </div>
            <figcaption className="sr-only">
                {total} campaign emails sent over the last 14 days.
            </figcaption>
        </figure>
    );
}

function PipelineBars({
    stages,
}: {
    stages: (StatusOption & { count: number })[];
}) {
    const max = Math.max(...stages.map((stage) => stage.count), 1);

    return (
        <ul className="space-y-2.5">
            {stages.map((stage) => (
                <li
                    key={stage.value}
                    className="grid grid-cols-[7.5rem_1fr_auto] items-center gap-3 text-sm"
                >
                    <span className="truncate">{stage.label}</span>
                    <div className="h-3">
                        <div
                            className={cn('h-full rounded-r-[4px]', accent)}
                            style={{
                                width:
                                    stage.count === 0
                                        ? 0
                                        : `max(2px, ${(stage.count / max) * 100}%)`,
                            }}
                            title={`${stage.label}: ${stage.count}`}
                        />
                    </div>
                    <span className="text-muted-foreground w-10 text-right tabular-nums">
                        {formatNumber(stage.count)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

function CampaignTable({ campaigns }: { campaigns: CampaignSummary[] }) {
    if (campaigns.length === 0) {
        return (
            <p className="text-muted-foreground py-6 text-center text-sm">
                No campaigns are running.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-muted-foreground text-left">
                    <tr>
                        <th className="pb-2 font-medium">Campaign</th>
                        <th className="pb-2 text-right font-medium">Sent</th>
                        <th className="pb-2 text-right font-medium">Replied</th>
                        <th className="pb-2 text-right font-medium">
                            Reply rate
                        </th>
                        <th className="pb-2 text-right font-medium">Bounced</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {campaigns.map((campaign) => (
                        <tr key={campaign.id}>
                            <td className="py-2 pr-3">
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={showCampaign(campaign.id)}
                                        className="truncate font-medium hover:underline"
                                    >
                                        {campaign.name}
                                    </Link>
                                    {campaign.status !== 'active' && (
                                        <CampaignStatusBadge
                                            status={campaign.status}
                                            label={campaign.status_label}
                                        />
                                    )}
                                </div>
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {campaign.sent}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {campaign.replied}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {formatRate(
                                    campaign.replied,
                                    campaign.contacted,
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {campaign.bounced}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ReplyList({ replies }: { replies: Reply[] }) {
    if (replies.length === 0) {
        return (
            <p className="text-muted-foreground py-6 text-center text-sm">
                No replies yet.
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {replies.map((reply) => (
                <li key={reply.id} className="space-y-0.5 py-2.5 text-sm">
                    <div className="flex items-baseline justify-between gap-2">
                        <Link
                            href={showContact(reply.contact.id)}
                            className="truncate font-medium hover:underline"
                        >
                            {reply.contact.name}
                        </Link>
                        <span className="text-muted-foreground shrink-0 text-xs">
                            {reply.created_at &&
                                new Date(reply.created_at).toLocaleDateString()}
                        </span>
                    </div>
                    <p className="text-muted-foreground line-clamp-2">
                        {reply.body ?? reply.subject}
                    </p>
                </li>
            ))}
        </ul>
    );
}

function ImportList({ imports }: { imports: Import[] }) {
    if (imports.length === 0) {
        return (
            <p className="text-muted-foreground py-4 text-center text-sm">
                Nothing imported yet.
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {imports.map((item) => (
                <li
                    key={item.id}
                    className="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <Link
                        href={showImport(item.id)}
                        className="truncate font-medium hover:underline"
                    >
                        {item.filename}
                    </Link>
                    <div className="flex shrink-0 items-center gap-3">
                        <span className="text-muted-foreground tabular-nums">
                            {item.processed_rows - item.failed_rows} imported
                            {item.failed_rows > 0 &&
                                ` · ${item.failed_rows} failed`}
                        </span>
                        <ImportStatusBadge
                            status={item.status}
                            label={item.status_label}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function ListSkeleton({ rows }: { rows: number }) {
    return (
        <div className="space-y-3">
            {Array.from({ length: rows }, (_, row) => (
                <Skeleton key={row} className="h-5 w-full" />
            ))}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
