import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/ContactController';
import ContactForm from '@/components/contact-form';
import Heading from '@/components/heading';
import type { CompanyOption, Contact } from '@/types';

export default function ContactsEdit({
    contact,
    companies,
}: {
    contact: Contact;
    companies: CompanyOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Contacts', href: index() },
            { title: contact.name, href: show(contact.id) },
            { title: 'Edit', href: edit(contact.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${contact.name}`} />

            <div className="p-4">
                <Heading
                    title={`Edit ${contact.name}`}
                    description="Pipeline status is changed from the contact page so every change is logged."
                />

                <ContactForm
                    form={update.form(contact.id)}
                    companies={companies}
                    contact={contact}
                    cancelHref={show.url(contact.id)}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}
