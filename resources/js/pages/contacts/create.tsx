import { Head } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ContactController';
import ContactForm from '@/components/contact-form';
import Heading from '@/components/heading';
import type { CompanyOption } from '@/types';

export default function ContactsCreate({
    companies,
    companyId,
}: {
    companies: CompanyOption[];
    companyId: number | null;
}) {
    return (
        <>
            <Head title="New contact" />

            <div className="p-4">
                <Heading
                    title="New contact"
                    description="Add a contact by hand. Bulk imports come from CSV uploads."
                />

                <ContactForm
                    form={store.form()}
                    companies={companies}
                    defaultCompanyId={companyId}
                    cancelHref={index.url()}
                    submitLabel="Create contact"
                />
            </div>
        </>
    );
}

ContactsCreate.layout = {
    breadcrumbs: [
        { title: 'Contacts', href: index() },
        { title: 'New contact', href: create() },
    ],
};
