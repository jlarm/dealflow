import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Globe, Pencil, Plus, Trash2 } from 'lucide-react';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/CompanyController';
import {
    create as createContact,
    show as showContact,
} from '@/actions/App/Http/Controllers/ContactController';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Company, Contact, Paginated } from '@/types';

export default function CompaniesShow({
    company,
    contacts,
}: {
    company: Company;
    contacts: Paginated<Contact>;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: company.name, href: show(company.id) },
        ],
    });

    const details = [
        company.industry,
        company.size && `${company.size} employees`,
    ].filter(Boolean);

    return (
        <>
            <Head title={company.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {company.name}
                            </h1>
                            {company.enriched_at && (
                                <Badge variant="secondary">Enriched</Badge>
                            )}
                        </div>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 text-sm">
                            {company.domain && (
                                <a
                                    href={`https://${company.domain}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 hover:underline"
                                >
                                    <Globe className="size-3.5" />
                                    {company.domain}
                                </a>
                            )}
                            {details.length > 0 && (
                                <span>{details.join(' · ')}</span>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={createContact({
                                    query: { company: company.id },
                                })}
                            >
                                <Plus />
                                Add contact
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={edit(company.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                        <ConfirmDelete
                            form={destroy.form(company.id)}
                            title={`Delete ${company.name}?`}
                            description="The company is removed, but its contacts are kept without a company."
                            trigger={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Delete company"
                                >
                                    <Trash2 />
                                </Button>
                            }
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Tags</th>
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
                                            href={showContact(contact.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {contact.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {contact.title ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {contact.email ?? '—'}
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
                                </tr>
                            ))}

                            {contacts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        No contacts at this company yet.
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
