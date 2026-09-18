import { Head } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/CompanyController';
import CompanyForm from '@/components/company-form';
import Heading from '@/components/heading';

export default function CompaniesCreate() {
    return (
        <>
            <Head title="New company" />

            <div className="p-4">
                <Heading
                    title="New company"
                    description="Websites like https://www.acme.com are saved as acme.com."
                />

                <CompanyForm
                    form={store.form()}
                    cancelHref={index.url()}
                    submitLabel="Create company"
                />
            </div>
        </>
    );
}

CompaniesCreate.layout = {
    breadcrumbs: [
        { title: 'Companies', href: index() },
        { title: 'New company', href: create() },
    ],
};
