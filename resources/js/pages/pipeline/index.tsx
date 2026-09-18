import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { EllipsisVertical } from 'lucide-react';
import { useState } from 'react';
import type { DragEvent } from 'react';
import { show } from '@/actions/App/Http/Controllers/ContactController';
import { update as updateStatus } from '@/actions/App/Http/Controllers/ContactStatusController';
import { index } from '@/actions/App/Http/Controllers/PipelineController';
import Heading from '@/components/heading';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type {
    Contact,
    ContactStatusValue,
    SimplePaginated,
    StatusOption,
} from '@/types';

type ColumnKey = `stage_${ContactStatusValue}`;

type PipelineProps = {
    statuses: StatusOption[];
    counts: Record<ContactStatusValue, number>;
} & Record<ColumnKey, SimplePaginated<Contact>>;

const columnKey = (status: ContactStatusValue): ColumnKey => `stage_${status}`;

/**
 * Insert a contact into a column, keeping the server's score-descending order.
 */
function insertByScore(contacts: Contact[], contact: Contact): Contact[] {
    const position = contacts.findIndex(
        (existing) =>
            existing.score < contact.score ||
            (existing.score === contact.score && existing.id < contact.id),
    );

    return position === -1
        ? [...contacts, contact]
        : [
              ...contacts.slice(0, position),
              contact,
              ...contacts.slice(position),
          ];
}

export default function PipelineIndex(props: PipelineProps) {
    const { statuses, counts } = props;
    const [dragged, setDragged] = useState<Contact | null>(null);
    const [dropTarget, setDropTarget] = useState<ContactStatusValue | null>(
        null,
    );

    const move = (contact: Contact, to: ContactStatusValue) => {
        const from = contact.status;

        if (from === to) {
            return;
        }

        router
            .optimistic<PipelineProps>((current) => {
                const source = current[columnKey(from)];
                const target = current[columnKey(to)];

                return {
                    [columnKey(from)]: {
                        ...source,
                        data: source.data.filter(
                            (existing) => existing.id !== contact.id,
                        ),
                    },
                    [columnKey(to)]: {
                        ...target,
                        data: insertByScore(target.data, {
                            ...contact,
                            status: to,
                        }),
                    },
                    counts: {
                        ...current.counts,
                        [from]: current.counts[from] - 1,
                        [to]: current.counts[to] + 1,
                    },
                } as Partial<PipelineProps>;
            })
            .patch(
                updateStatus.url(contact.id),
                { status: to },
                { preserveScroll: true, preserveState: true, only: ['counts'] },
            );
    };

    const drop = (event: DragEvent, status: ContactStatusValue) => {
        event.preventDefault();
        setDropTarget(null);

        if (dragged) {
            move(dragged, status);
        }

        setDragged(null);
    };

    return (
        <>
            <Head title="Pipeline" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Pipeline"
                    description="Drag a contact to another stage, or use its menu. Every move is logged on the contact's timeline."
                />

                <div className="-mx-4 overflow-x-auto px-4 pb-2">
                    <div className="flex gap-4">
                        {statuses.map((status) => (
                            <section
                                key={status.value}
                                aria-label={`${status.label} stage`}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDropTarget(status.value);
                                }}
                                onDragLeave={(event) => {
                                    if (
                                        !event.currentTarget.contains(
                                            event.relatedTarget as Node | null,
                                        )
                                    ) {
                                        setDropTarget(null);
                                    }
                                }}
                                onDrop={(event) => drop(event, status.value)}
                                className={cn(
                                    'bg-muted/40 flex w-72 shrink-0 flex-col rounded-xl border',
                                    dropTarget === status.value &&
                                        dragged?.status !== status.value &&
                                        'border-primary/50 bg-primary/5',
                                )}
                            >
                                <header className="flex items-center justify-between px-3 py-2.5">
                                    <StatusBadge status={status} />
                                    <span className="text-muted-foreground text-sm tabular-nums">
                                        {counts[status.value]}
                                    </span>
                                </header>

                                <div className="h-[calc(100vh-15rem)] min-h-80 overflow-y-auto px-2 pb-2">
                                    <PipelineColumn
                                        status={status.value}
                                        contacts={
                                            props[columnKey(status.value)]
                                        }
                                        statuses={statuses}
                                        onDragStart={setDragged}
                                        onDragEnd={() => {
                                            setDragged(null);
                                            setDropTarget(null);
                                        }}
                                        onMove={move}
                                    />
                                </div>
                            </section>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

function PipelineColumn({
    status,
    contacts,
    statuses,
    onDragStart,
    onDragEnd,
    onMove,
}: {
    status: ContactStatusValue;
    contacts: SimplePaginated<Contact>;
    statuses: StatusOption[];
    onDragStart: (contact: Contact) => void;
    onDragEnd: () => void;
    onMove: (contact: Contact, to: ContactStatusValue) => void;
}) {
    if (contacts.data.length === 0) {
        return (
            <p className="text-muted-foreground rounded-lg border border-dashed px-3 py-8 text-center text-sm">
                No contacts
            </p>
        );
    }

    return (
        <InfiniteScroll
            data={columnKey(status)}
            preserveUrl
            className="space-y-2"
            loading={<CardSkeleton />}
        >
            {contacts.data.map((contact) => (
                <PipelineCard
                    key={contact.id}
                    contact={contact}
                    statuses={statuses}
                    onDragStart={onDragStart}
                    onDragEnd={onDragEnd}
                    onMove={onMove}
                />
            ))}
        </InfiniteScroll>
    );
}

function PipelineCard({
    contact,
    statuses,
    onDragStart,
    onDragEnd,
    onMove,
}: {
    contact: Contact;
    statuses: StatusOption[];
    onDragStart: (contact: Contact) => void;
    onDragEnd: () => void;
    onMove: (contact: Contact, to: ContactStatusValue) => void;
}) {
    const subtitle = [contact.title, contact.company?.name]
        .filter(Boolean)
        .join(' · ');

    return (
        <article
            draggable
            onDragStart={(event) => {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(contact.id));
                onDragStart(contact);
            }}
            onDragEnd={onDragEnd}
            className="bg-card group cursor-grab rounded-lg border p-3 text-sm shadow-xs active:cursor-grabbing"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 space-y-0.5">
                    <Link
                        href={show(contact.id)}
                        className="block truncate font-medium hover:underline"
                    >
                        {contact.name}
                    </Link>
                    {subtitle && (
                        <p className="text-muted-foreground truncate text-xs">
                            {subtitle}
                        </p>
                    )}
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="-mt-1 -mr-1 size-7 shrink-0"
                            aria-label={`Move ${contact.name}`}
                        >
                            <EllipsisVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Move to</DropdownMenuLabel>
                        {statuses
                            .filter((status) => status.value !== contact.status)
                            .map((status) => (
                                <DropdownMenuItem
                                    key={status.value}
                                    onSelect={() =>
                                        onMove(contact, status.value)
                                    }
                                >
                                    {status.label}
                                </DropdownMenuItem>
                            ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="mt-2 flex items-center justify-between gap-2">
                <div className="flex min-w-0 flex-wrap gap-1">
                    {contact.tags?.slice(0, 2).map((tag) => (
                        <Badge
                            key={tag.id}
                            variant="outline"
                            className="max-w-28 truncate"
                        >
                            {tag.name}
                        </Badge>
                    ))}
                </div>
                <span
                    className="text-muted-foreground shrink-0 text-xs tabular-nums"
                    title="Score"
                >
                    {contact.score}
                </span>
            </div>
        </article>
    );
}

function CardSkeleton() {
    return (
        <div className="space-y-2 rounded-lg border p-3">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-3 w-24" />
        </div>
    );
}

PipelineIndex.layout = {
    breadcrumbs: [{ title: 'Pipeline', href: index() }],
};
