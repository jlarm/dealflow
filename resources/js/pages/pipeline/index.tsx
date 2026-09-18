import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { EllipsisVertical } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { DragEvent } from 'react';
import { show } from '@/actions/App/Http/Controllers/ContactController';
import { update as moveOnBoard } from '@/actions/App/Http/Controllers/PipelineContactController';
import { index } from '@/actions/App/Http/Controllers/PipelineController';
import Heading from '@/components/heading';
import { statusDotClasses } from '@/components/status-badge';
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
    CursorPaginated,
    StatusOption,
} from '@/types';

type ColumnKey = `stage_${ContactStatusValue}`;

type PipelineProps = {
    statuses: StatusOption[];
    counts: Record<ContactStatusValue, number>;
} & Record<ColumnKey, CursorPaginated<Contact>>;

/** The card being dragged, and its height for sizing the drop placeholder. */
type Drag = { contact: Contact; height: number };

/** Where the dragged card would land: a stage, and a slot among its other cards. */
type DropSlot = { status: ContactStatusValue; index: number };

/** How long a dropped card keeps its highlight. */
const LANDED_HIGHLIGHT_MS = 1600;

const columnKey = (status: ContactStatusValue): ColumnKey => `stage_${status}`;

/** A column's cards without the one being moved. */
const othersIn = (contacts: Contact[], moving: Contact | undefined) =>
    moving ? contacts.filter((contact) => contact.id !== moving.id) : contacts;

export default function PipelineIndex(props: PipelineProps) {
    const { statuses, counts } = props;
    const [drag, setDrag] = useState<Drag | null>(null);
    const [slot, setSlot] = useState<DropSlot | null>(null);
    const [landedId, setLandedId] = useState<number | null>(null);

    useEffect(() => {
        if (landedId === null) {
            return;
        }

        const timeout = setTimeout(
            () => setLandedId(null),
            LANDED_HIGHLIGHT_MS,
        );

        return () => clearTimeout(timeout);
    }, [landedId]);

    /**
     * Place a contact at a slot in a stage: instantly on screen, then on the server,
     * which puts it between the same neighbours even if more cards haven't loaded.
     */
    const move = (contact: Contact, to: ContactStatusValue, at: number) => {
        const from = contact.status;
        const targetOthers = othersIn(props[columnKey(to)].data, contact);
        const above = targetOthers[at - 1];
        const below = targetOthers[at];
        const sourceIndex = props[columnKey(from)].data.findIndex(
            (existing) => existing.id === contact.id,
        );

        if (from === to && sourceIndex === at) {
            return;
        }

        setLandedId(contact.id);

        router
            .optimistic<PipelineProps>((current) => {
                const moved = { ...contact, status: to };
                const target = current[columnKey(to)];
                const updatedTarget = othersIn(target.data, contact);
                updatedTarget.splice(at, 0, moved);

                if (from === to) {
                    return {
                        [columnKey(to)]: { ...target, data: updatedTarget },
                    } as Partial<PipelineProps>;
                }

                const source = current[columnKey(from)];

                return {
                    [columnKey(from)]: {
                        ...source,
                        data: othersIn(source.data, contact),
                    },
                    [columnKey(to)]: { ...target, data: updatedTarget },
                    counts: {
                        ...current.counts,
                        [from]: current.counts[from] - 1,
                        [to]: current.counts[to] + 1,
                    },
                } as Partial<PipelineProps>;
            })
            .patch(
                moveOnBoard.url(contact.id),
                { status: to, above_id: above?.id, below_id: below?.id },
                { preserveScroll: true, preserveState: true, only: ['counts'] },
            );
    };

    const endDrag = () => {
        setDrag(null);
        setSlot(null);
    };

    const drop = (event: DragEvent, status: ContactStatusValue) => {
        event.preventDefault();

        if (drag) {
            const others = othersIn(
                props[columnKey(status)].data,
                drag.contact,
            );
            const at =
                slot?.status === status
                    ? Math.min(slot.index, others.length)
                    : others.length;

            move(drag.contact, status, at);
        }

        endDrag();
    };

    return (
        <>
            <Head title="Pipeline" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Pipeline"
                    description="Drag contacts to reorder them or move them to another stage."
                />

                <div className="-mx-4 overflow-x-auto px-4 pb-2">
                    <div className="flex gap-3">
                        {statuses.map((status) => (
                            <section
                                key={status.value}
                                aria-label={`${status.label} stage`}
                                onDragOver={(event) => {
                                    if (!drag) {
                                        return;
                                    }

                                    event.preventDefault();

                                    // Entering a column through its empty space lands at the end;
                                    // over the cards, each card sets the exact slot.
                                    if (slot?.status !== status.value) {
                                        setSlot({
                                            status: status.value,
                                            index: othersIn(
                                                props[columnKey(status.value)]
                                                    .data,
                                                drag.contact,
                                            ).length,
                                        });
                                    }
                                }}
                                onDragLeave={(event) => {
                                    if (
                                        slot?.status === status.value &&
                                        !event.currentTarget.contains(
                                            event.relatedTarget as Node | null,
                                        )
                                    ) {
                                        setSlot(null);
                                    }
                                }}
                                onDrop={(event) => drop(event, status.value)}
                                className={cn(
                                    'bg-muted/50 flex w-64 shrink-0 flex-col rounded-xl ring-2 ring-transparent transition-all duration-200',
                                    slot?.status === status.value &&
                                        'bg-[#008F95]/5 ring-[#008F95]/25 dark:bg-[#14A0A6]/10',
                                )}
                            >
                                <header className="flex items-center gap-2 px-3 pt-3 pb-2 text-sm">
                                    <span
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            statusDotClasses[status.color],
                                        )}
                                    />
                                    <h2 className="font-medium">
                                        {status.label}
                                    </h2>
                                    <span className="text-muted-foreground ml-auto tabular-nums">
                                        {counts[status.value]}
                                    </span>
                                </header>

                                <div className="h-[calc(100vh-14rem)] min-h-80 [scrollbar-width:thin] overflow-y-auto px-2 pb-2">
                                    <PipelineColumn
                                        status={status.value}
                                        contacts={
                                            props[columnKey(status.value)]
                                        }
                                        statuses={statuses}
                                        drag={drag}
                                        slot={
                                            slot?.status === status.value
                                                ? slot.index
                                                : null
                                        }
                                        landedId={landedId}
                                        onDragStart={setDrag}
                                        onDragEnd={endDrag}
                                        onSlot={(slotIndex) =>
                                            setSlot({
                                                status: status.value,
                                                index: slotIndex,
                                            })
                                        }
                                        onMoveToTop={(contact, to) =>
                                            move(contact, to, 0)
                                        }
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
    drag,
    slot,
    landedId,
    onDragStart,
    onDragEnd,
    onSlot,
    onMoveToTop,
}: {
    status: ContactStatusValue;
    contacts: CursorPaginated<Contact>;
    statuses: StatusOption[];
    drag: Drag | null;
    slot: number | null;
    landedId: number | null;
    onDragStart: (drag: Drag) => void;
    onDragEnd: () => void;
    onSlot: (index: number) => void;
    onMoveToTop: (contact: Contact, to: ContactStatusValue) => void;
}) {
    const others = othersIn(contacts.data, drag?.contact);
    const placeholder = drag && slot !== null && (
        <DropPlaceholder key="drop-placeholder" height={drag.height} />
    );

    if (contacts.data.length === 0) {
        return (
            placeholder || (
                <p className="text-muted-foreground px-3 py-8 text-center text-sm">
                    No contacts
                </p>
            )
        );
    }

    return (
        <InfiniteScroll
            data={columnKey(status)}
            preserveUrl
            className="space-y-1.5"
            loading={<CardSkeleton />}
        >
            {contacts.data.map((contact) => {
                const isDragged = drag?.contact.id === contact.id;
                const position = others.indexOf(contact);

                return [
                    !isDragged && slot === position && placeholder,
                    <PipelineCard
                        key={contact.id}
                        contact={contact}
                        statuses={statuses}
                        isDragged={isDragged}
                        hasLanded={landedId === contact.id}
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                        onDragOverHalf={(isUpperHalf) => {
                            if (!isDragged) {
                                onSlot(isUpperHalf ? position : position + 1);
                            }
                        }}
                        onMoveToTop={onMoveToTop}
                    />,
                ];
            })}
            {slot !== null && slot >= others.length && placeholder}
        </InfiniteScroll>
    );
}

/**
 * The gap that opens where a dragged card will land, pushing the cards below it down.
 */
function DropPlaceholder({ height }: { height: number }) {
    return (
        <div
            aria-hidden
            className="animate-[drop-slot-open_180ms_ease-out] rounded-lg border-2 border-dashed border-[#008F95]/40 bg-[#008F95]/5 dark:border-[#14A0A6]/50"
            style={{ height }}
        />
    );
}

function PipelineCard({
    contact,
    statuses,
    isDragged,
    hasLanded,
    onDragStart,
    onDragEnd,
    onDragOverHalf,
    onMoveToTop,
}: {
    contact: Contact;
    statuses: StatusOption[];
    isDragged: boolean;
    hasLanded: boolean;
    onDragStart: (drag: Drag) => void;
    onDragEnd: () => void;
    onDragOverHalf: (isUpperHalf: boolean) => void;
    onMoveToTop: (contact: Contact, to: ContactStatusValue) => void;
}) {
    return (
        <article
            draggable
            onDragStart={(event) => {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(contact.id));
                const height = event.currentTarget.offsetHeight;
                // Let the browser capture the card as the drag image before it fades.
                setTimeout(() => onDragStart({ contact, height }), 0);
            }}
            onDragEnd={onDragEnd}
            onDragOver={(event) => {
                // This card sets the exact slot, so the column's own handler shouldn't also run.
                event.preventDefault();
                event.stopPropagation();
                const rect = event.currentTarget.getBoundingClientRect();
                onDragOverHalf(event.clientY < rect.top + rect.height / 2);
            }}
            title={contact.title ?? undefined}
            className={cn(
                'bg-card group relative cursor-grab rounded-lg px-3 py-2.5 text-sm shadow-xs ring-1 ring-black/5 transition-all duration-300 hover:shadow-sm active:cursor-grabbing dark:ring-white/10',
                isDragged && 'scale-[0.97] opacity-40 shadow-none',
                hasLanded &&
                    'animate-in fade-in zoom-in-95 ring-2 ring-[#008F95]/60 duration-300 dark:ring-[#14A0A6]/70',
            )}
        >
            <div className="flex items-baseline gap-2 pr-6">
                <Link
                    href={show(contact.id)}
                    className="min-w-0 flex-1 truncate font-medium hover:underline"
                    draggable={false}
                >
                    {contact.name}
                </Link>
                <span
                    className="text-muted-foreground shrink-0 text-xs tabular-nums"
                    aria-label={`Score ${contact.score}`}
                >
                    {contact.score}
                </span>
            </div>
            <p className="text-muted-foreground mt-0.5 truncate text-xs">
                {contact.company?.name ?? 'No company'}
            </p>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="absolute top-1.5 right-1 size-7 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 data-[state=open]:opacity-100 [@media(hover:none)]:opacity-100"
                        aria-label={`Move ${contact.name}`}
                    >
                        <EllipsisVertical />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Move to the top of</DropdownMenuLabel>
                    {statuses
                        .filter((status) => status.value !== contact.status)
                        .map((status) => (
                            <DropdownMenuItem
                                key={status.value}
                                onSelect={() =>
                                    onMoveToTop(contact, status.value)
                                }
                            >
                                {status.label}
                            </DropdownMenuItem>
                        ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </article>
    );
}

function CardSkeleton() {
    return (
        <div className="bg-card space-y-2 rounded-lg px-3 py-2.5 ring-1 ring-black/5 dark:ring-white/10">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-3 w-24" />
        </div>
    );
}

PipelineIndex.layout = {
    breadcrumbs: [{ title: 'Pipeline', href: index() }],
};
