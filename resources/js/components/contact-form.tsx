import { Form, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import type { RouteFormDefinition } from '@/wayfinder';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CompanyOption, Contact } from '@/types';

const NO_COMPANY = 'none';

type ContactFormProps = {
    form: RouteFormDefinition<'post'>;
    companies: CompanyOption[];
    contact?: Contact;
    defaultCompanyId?: number | null;
    cancelHref: string;
    submitLabel: string;
};

export default function ContactForm({
    form,
    companies,
    contact,
    defaultCompanyId = null,
    cancelHref,
    submitLabel,
}: ContactFormProps) {
    const companyId = contact ? contact.company_id : defaultCompanyId;

    return (
        <Form
            {...form}
            transform={(data) => ({
                ...data,
                company_id:
                    data.company_id === NO_COMPANY ? null : data.company_id,
            })}
            className="max-w-2xl space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <Field
                            name="first_name"
                            label="First name"
                            defaultValue={contact?.first_name}
                            error={errors.first_name}
                            autoComplete="off"
                        />
                        <Field
                            name="last_name"
                            label="Last name"
                            defaultValue={contact?.last_name}
                            error={errors.last_name}
                            autoComplete="off"
                        />
                        <Field
                            name="email"
                            label="Email"
                            type="email"
                            defaultValue={contact?.email}
                            error={errors.email}
                            autoComplete="off"
                        />
                        <Field
                            name="phone"
                            label="Phone"
                            defaultValue={contact?.phone}
                            error={errors.phone}
                            autoComplete="off"
                        />
                        <Field
                            name="title"
                            label="Job title"
                            defaultValue={contact?.title}
                            error={errors.title}
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="company_id">Company</Label>
                            <Select
                                name="company_id"
                                defaultValue={
                                    companyId ? String(companyId) : NO_COMPANY
                                }
                            >
                                <SelectTrigger
                                    id="company_id"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_COMPANY}>
                                        No company
                                    </SelectItem>
                                    {companies.map((company) => (
                                        <SelectItem
                                            key={company.id}
                                            value={String(company.id)}
                                        >
                                            {company.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.company_id} />
                        </div>

                        <Field
                            name="linkedin_url"
                            label="LinkedIn URL"
                            type="url"
                            defaultValue={contact?.linkedin_url}
                            error={errors.linkedin_url}
                            placeholder="https://www.linkedin.com/in/…"
                        />
                        <Field
                            name="source_list"
                            label="Source list"
                            defaultValue={contact?.source_list}
                            error={errors.source_list}
                        />
                        <Field
                            name="score"
                            label="Score (0–100)"
                            type="number"
                            min={0}
                            max={100}
                            required
                            defaultValue={String(contact?.score ?? 0)}
                            error={errors.score}
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>{submitLabel}</Button>
                        <Button variant="ghost" asChild>
                            <Link href={cancelHref}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Field({
    name,
    label,
    error,
    defaultValue,
    ...props
}: Omit<ComponentProps<typeof Input>, 'defaultValue'> & {
    name: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={defaultValue ?? ''}
                aria-invalid={error ? true : undefined}
                {...props}
            />
            <InputError message={error} />
        </div>
    );
}
