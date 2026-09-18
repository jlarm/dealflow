import {
    Deferred,
    Form,
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import {
    Building2,
    Linkedin,
    Mail,
    Pencil,
    Phone,
    Tags as TagsIcon,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { store as storeActivity } from '@/actions/App/Http/Controllers/ContactActivityController';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ContactController';
import { update as updateStatus } from '@/actions/App/Http/Controllers/ContactStatusController';
import { update as updateTags } from '@/actions/App/Http/Controllers/ContactTagController';
import { show as showCompany } from '@/actions/App/Http/Controllers/CompanyController';
import ConfirmDelete from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    Activity,
    Contact,
    ContactStatusValue,
    Option,
    Paginated,
    StatusOption,
    Tag,
} from '@/types';

type ContactShowProps = {
    contact: Contact;
    activities?: Paginated<Activity>;
    statuses: StatusOption[];
    tags: Tag[];
    loggableActivityTypes: Option[];
};

export default function ContactShow({
    contact,
    activities,
    statuses,
    tags,
    loggableActivityTypes,
}: ContactShowProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Contacts', href: index() },
            { title: contact.name, href: show(contact.id) },
        ],
    });

    const statusByValue = new Map(
        statuses.map((status) => [status.value, status]),
    );

    const changeStatus = (status: ContactStatusValue) => {
        router
            .optimistic<ContactShowProps>((props) => ({
                contact: { ...props.contact, status },
            }))
            .patch(
                updateStatus.url(contact.id),
                { status },
                {
                    preserveScroll: true,
                    only: ['contact', 'activities'],
                    reset: ['activities'],
                },
            );
    };

    const toggleTag = (tag: Tag, checked: boolean) => {
        const selected = contact.tags ?? [];
        const nextTags = checked
            ? [...selected, tag]
            : selected.filter((selectedTag) => selectedTag.id !== tag.id);

        router
            .optimistic<ContactShowProps>((props) => ({
                contact: { ...props.contact, tags: nextTags },
            }))
            .put(
                updateTags.url(contact.id),
                { tag_ids: nextTags.map((nextTag) => nextTag.id) },
                { preserveScroll: true, only: ['contact'] },
            );
    };

    return (
        <>
            <Head title={contact.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {contact.name}
                            </h1>
                            <StatusBadge
                                status={statusByValue.get(contact.status)}
                            />
                            {contact.unsubscribed_at && (
                                <Badge variant="destructive">
                                    Unsubscribed
                                </Badge>
                            )}
                        </div>
                        {(contact.title || contact.company) && (
                            <p className="text-muted-foreground text-sm">
                                {[contact.title, contact.company?.name]
                                    .filter(Boolean)
                                    .join(' at ')}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Select
                            value={contact.status}
                            onValueChange={(status) =>
                                changeStatus(status as ContactStatusValue)
                            }
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label="Pipeline status"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button variant="outline" asChild>
                            <Link href={edit(contact.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>

                        <ConfirmDelete
                            form={destroy.form(contact.id)}
                            title={`Delete ${contact.name}?`}
                            description="This permanently deletes the contact, its timeline, and its campaign enrollments."
                            trigger={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Delete contact"
                                >
                                    <Trash2 />
                                </Button>
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <DetailRow icon={Mail}>
                                    {contact.email ? (
                                        <a
                                            href={`mailto:${contact.email}`}
                                            className="hover:underline"
                                        >
                                            {contact.email}
                                        </a>
                                    ) : (
                                        'No email'
                                    )}
                                    {contact.email_status && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2 capitalize"
                                        >
                                            {contact.email_status}
                                        </Badge>
                                    )}
                                </DetailRow>
                                <DetailRow icon={Phone}>
                                    {contact.phone ?? 'No phone'}
                                </DetailRow>
                                <DetailRow icon={Building2}>
                                    {contact.company ? (
                                        <Link
                                            href={showCompany(
                                                contact.company.id,
                                            )}
                                            className="hover:underline"
                                        >
                                            {contact.company.name}
                                        </Link>
                                    ) : (
                                        'No company'
                                    )}
                                </DetailRow>
                                {contact.linkedin_url && (
                                    <DetailRow icon={Linkedin}>
                                        <a
                                            href={contact.linkedin_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="hover:underline"
                                        >
                                            LinkedIn profile
                                        </a>
                                    </DetailRow>
                                )}

                                <dl className="grid grid-cols-2 gap-3 border-t pt-3">
                                    <Stat label="Score" value={contact.score} />
                                    <Stat
                                        label="Last contacted"
                                        value={formatDate(
                                            contact.last_contacted_at,
                                            'Never',
                                        )}
                                    />
                                    <Stat
                                        label="Source list"
                                        value={contact.source_list ?? '—'}
                                    />
                                    <Stat
                                        label="Added"
                                        value={formatDate(contact.created_at)}
                                    />
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Tags</CardTitle>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="sm">
                                            <TagsIcon />
                                            Edit
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {tags.length === 0 && (
                                            <p className="text-muted-foreground px-2 py-1.5 text-sm">
                                                No tags yet.
                                            </p>
                                        )}
                                        {tags.map((tag) => (
                                            <DropdownMenuCheckboxItem
                                                key={tag.id}
                                                checked={contact.tags?.some(
                                                    (selected) =>
                                                        selected.id === tag.id,
                                                )}
                                                onSelect={(event) =>
                                                    event.preventDefault()
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleTag(tag, checked)
                                                }
                                            >
                                                {tag.name}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </CardHeader>
                            <CardContent>
                                {contact.tags && contact.tags.length > 0 ? (
                                    <div className="flex flex-wrap gap-1">
                                        {contact.tags.map((tag) => (
                                            <Badge
                                                key={tag.id}
                                                variant="outline"
                                            >
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        No tags.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6 lg:col-span-2">
                        <LogActivityForm
                            contact={contact}
                            types={loggableActivityTypes}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Timeline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Deferred
                                    data="activities"
                                    fallback={<TimelineSkeleton />}
                                >
                                    {activities && (
                                        <Timeline
                                            activities={activities}
                                            statusByValue={statusByValue}
                                        />
                                    )}
                                </Deferred>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

function LogActivityForm({
    contact,
    types,
}: {
    contact: Contact;
    types: Option[];
}) {
    const [type, setType] = useState(types[0]?.value ?? 'note');

    return (
        <Card>
            <CardContent>
                <Form
                    {...storeActivity.form(contact.id)}
                    options={{
                        preserveScroll: true,
                        only: ['contact', 'activities'],
                        reset: ['activities'],
                    }}
                    resetOnSuccess={['body']}
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="type" value={type} />
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={type}
                                onValueChange={(value) =>
                                    value && setType(value)
                                }
                            >
                                {types.map((option) => (
                                    <ToggleGroupItem
                                        key={option.value}
                                        value={option.value}
                                        className="px-4"
                                    >
                                        {option.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                            <Textarea
                                name="body"
                                aria-label="Details"
                                placeholder={
                                    type === 'call'
                                        ? 'How did the call go?'
                                        : 'Add a note…'
                                }
                                rows={3}
                            />
                            <InputError message={errors.body ?? errors.type} />
                            <Button disabled={processing}>
                                Log{' '}
                                {types
                                    .find((option) => option.value === type)
                                    ?.label.toLowerCase()}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function Timeline({
    activities,
    statusByValue,
}: {
    activities: Paginated<Activity>;
    statusByValue: Map<ContactStatusValue, StatusOption>;
}) {
    if (activities.data.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                Nothing has happened with this contact yet.
            </p>
        );
    }

    return (
        <InfiniteScroll
            data="activities"
            preserveUrl
            className="space-y-4"
            loading={<TimelineSkeleton />}
        >
            {activities.data.map((activity) => (
                <div key={activity.id} className="flex gap-3 text-sm">
                    <div className="bg-muted-foreground/40 mt-1.5 size-2 shrink-0 rounded-full" />
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-baseline gap-x-2">
                            <span className="font-medium">
                                {activity.type_label}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {formatDateTime(activity.created_at)}
                                {activity.user && ` · ${activity.user.name}`}
                            </span>
                        </div>
                        <ActivityBody
                            activity={activity}
                            statusByValue={statusByValue}
                        />
                    </div>
                </div>
            ))}
        </InfiniteScroll>
    );
}

function ActivityBody({
    activity,
    statusByValue,
}: {
    activity: Activity;
    statusByValue: Map<ContactStatusValue, StatusOption>;
}) {
    if (activity.type === 'status_change') {
        return (
            <div className="flex items-center gap-2">
                <StatusBadge
                    status={statusByValue.get(
                        activity.payload.from as ContactStatusValue,
                    )}
                />
                <span className="text-muted-foreground">→</span>
                <StatusBadge
                    status={statusByValue.get(
                        activity.payload.to as ContactStatusValue,
                    )}
                />
            </div>
        );
    }

    const text = activity.payload.body ?? activity.payload.subject;

    return text ? (
        <p className="text-foreground/80 break-words whitespace-pre-line">
            {text}
        </p>
    ) : null;
}

function TimelineSkeleton() {
    return (
        <div className="space-y-4">
            {[0, 1, 2].map((row) => (
                <div key={row} className="flex gap-3">
                    <Skeleton className="mt-1 size-2 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-4 w-3/4" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function DetailRow({
    icon: Icon,
    children,
}: {
    icon: ComponentType<{ className?: string }>;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-2">
            <Icon className="text-muted-foreground size-4 shrink-0" />
            <span className="min-w-0 truncate">{children}</span>
        </div>
    );
}

function Stat({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="truncate">{value}</dd>
        </div>
    );
}

function formatDate(value: string | null, fallback = '—'): string {
    return value ? new Date(value).toLocaleDateString() : fallback;
}

function formatDateTime(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '';
}
