import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/CompanyController';
import CompanyForm from '@/components/company-form';
import Heading from '@/components/heading';
import type { Company } from '@/types';

export default function CompaniesEdit({ company }: { company: Company }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: company.name, href: show(company.id) },
            { title: 'Edit', href: edit(company.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${company.name}`} />

            <div className="p-4">
                <Heading title={`Edit ${company.name}`} />

                <CompanyForm
                    form={update.form(company.id)}
                    company={company}
                    cancelHref={show.url(company.id)}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}
