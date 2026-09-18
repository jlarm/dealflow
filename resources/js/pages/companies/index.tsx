import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/CompanyController';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Company, Paginated } from '@/types';

export default function CompaniesIndex({
    companies,
    filters,
}: {
    companies: Paginated<Company>;
    filters: { search: string };
}) {
    const [search, setSearch] = useState(filters.search);
    const searchTimeout = useRef<ReturnType<typeof setTimeout>>(undefined);

    const searchFor = (term: string) => {
        setSearch(term);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(
            () =>
                router.get(index.url(), term ? { search: term } : {}, {
                    preserveState: true,
                    replace: true,
                }),
            300,
        );
    };

    return (
        <>
            <Head title="Companies" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Companies"
                        description="Organizations your contacts work for"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New company
                        </Link>
                    </Button>
                </div>

                <Input
                    type="search"
                    value={search}
                    onChange={(event) => searchFor(event.target.value)}
                    placeholder="Search name or domain…"
                    className="w-full sm:w-80"
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Domain
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Industry
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Employees
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Contacts
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {companies.data.map((company) => (
                                <tr
                                    key={company.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(company.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {company.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {company.domain ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.industry ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.size ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {company.contacts_count}
                                    </td>
                                </tr>
                            ))}

                            {companies.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        No companies found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={companies.meta} />
            </div>
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'Companies', href: index() }],
};
