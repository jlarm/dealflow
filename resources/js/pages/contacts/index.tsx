import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/ContactController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatLocation } from '@/lib/location';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Contact, Paginated, StatusOption, Tag } from '@/types';

type Filters = {
    search: string;
    status: string | null;
    tag: number | null;
    company: number | null;
    source_list: string;
    state: string;
    seniority: string;
    sort: 'name' | 'score' | 'last_contacted_at' | 'created_at';
    direction: 'asc' | 'desc';
};

type ContactsIndexProps = {
    contacts: Paginated<Contact>;
    filters: Filters;
    statuses: StatusOption[];
    tags: Tag[];
    sourceLists: string[];
    states: string[];
    seniorities: string[];
};

const ALL = 'all';

export default function ContactsIndex({
    contacts,
    filters,
    statuses,
    tags,
    sourceLists,
    states,
    seniorities,
}: ContactsIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const searchTimeout = useRef<ReturnType<typeof setTimeout>>(undefined);

    const applyFilters = (changes: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const searchFor = (term: string) => {
        setSearch(term);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(
            () => applyFilters({ search: term }),
            300,
        );
    };

    const sortBy = (sort: Filters['sort']) => {
        applyFilters({
            sort,
            direction:
                filters.sort === sort && filters.direction === 'asc'
                    ? 'desc'
                    : 'asc',
        });
    };

    const statusByValue = new Map(
        statuses.map((status) => [status.value, status]),
    );

    return (
        <>
            <Head title="Contacts" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Contacts"
                        description="Everyone in your pipeline, across every source list"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New contact
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => searchFor(event.target.value)}
                        placeholder="Search name, email, title, or company…"
                        className="w-full sm:w-80"
                    />

                    <FilterSelect
                        value={filters.status}
                        placeholder="All statuses"
                        options={statuses.map((status) => ({
                            value: status.value,
                            label: status.label,
                        }))}
                        onChange={(status) => applyFilters({ status })}
                    />

                    <FilterSelect
                        value={filters.tag ? String(filters.tag) : null}
                        placeholder="All tags"
                        options={tags.map((tag) => ({
                            value: String(tag.id),
                            label: tag.name,
                        }))}
                        onChange={(tag) =>
                            applyFilters({ tag: tag ? Number(tag) : null })
                        }
                    />

                    <FilterSelect
                        value={filters.source_list || null}
                        placeholder="All source lists"
                        options={sourceLists.map((sourceList) => ({
                            value: sourceList,
                            label: sourceList,
                        }))}
                        onChange={(sourceList) =>
                            applyFilters({ source_list: sourceList ?? '' })
                        }
                    />

                    <FilterSelect
                        value={filters.state || null}
                        placeholder="All states"
                        options={states.map((state) => ({
                            value: state,
                            label: state,
                        }))}
                        onChange={(state) =>
                            applyFilters({ state: state ?? '' })
                        }
                    />

                    <FilterSelect
                        value={filters.seniority || null}
                        placeholder="All seniorities"
                        options={seniorities.map((seniority) => ({
                            value: seniority,
                            label: seniority,
                        }))}
                        onChange={(seniority) =>
                            applyFilters({ seniority: seniority ?? '' })
                        }
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <SortableHeader
                                    label="Name"
                                    sort="name"
                                    filters={filters}
                                    onSort={sortBy}
                                />
                                <th className="px-4 py-3 font-medium">
                                    Company
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">Tags</th>
                                <SortableHeader
                                    label="Score"
                                    sort="score"
                                    filters={filters}
                                    onSort={sortBy}
                                />
                                <SortableHeader
                                    label="Last contacted"
                                    sort="last_contacted_at"
                                    filters={filters}
                                    onSort={sortBy}
                                />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {contacts.data.map((contact) => (
                                <tr
                                    key={contact.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(contact.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {contact.name}
                                        </Link>
                                        {contact.title && (
                                            <p className="text-muted-foreground">
                                                {contact.title}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {contact.company?.name ?? '—'}
                                        {contact.company &&
                                            formatLocation(
                                                contact.company.city,
                                                contact.company.state,
                                            ) && (
                                                <p className="text-muted-foreground">
                                                    {formatLocation(
                                                        contact.company.city,
                                                        contact.company.state,
                                                    )}
                                                </p>
                                            )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={statusByValue.get(
                                                contact.status,
                                            )}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {contact.tags?.map((tag) => (
                                                <Badge
                                                    key={tag.id}
                                                    variant="outline"
                                                >
                                                    {tag.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {contact.score}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {contact.last_contacted_at
                                            ? new Date(
                                                  contact.last_contacted_at,
                                              ).toLocaleDateString()
                                            : 'Never'}
                                    </td>
                                </tr>
                            ))}

                            {contacts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        No contacts match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={contacts.meta} />
            </div>
        </>
    );
}

function FilterSelect({
    value,
    placeholder,
    options,
    onChange,
}: {
    value: string | null;
    placeholder: string;
    options: { value: string; label: string }[];
    onChange: (value: string | null) => void;
}) {
    return (
        <Select
            value={value ?? ALL}
            onValueChange={(selected) =>
                onChange(selected === ALL ? null : selected)
            }
        >
            <SelectTrigger className="w-full sm:w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function SortableHeader({
    label,
    sort,
    filters,
    onSort,
}: {
    label: string;
    sort: Filters['sort'];
    filters: Filters;
    onSort: (sort: Filters['sort']) => void;
}) {
    const isActive = filters.sort === sort;
    const Icon = filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <th className="px-4 py-3 font-medium">
            <button
                type="button"
                onClick={() => onSort(sort)}
                className="hover:text-foreground inline-flex items-center gap-1"
            >
                {label}
                {isActive && <Icon className="size-3.5" />}
            </button>
        </th>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Contacts', href: index() }],
};
