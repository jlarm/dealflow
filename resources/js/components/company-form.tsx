import { Form, Link } from '@inertiajs/react';
import type { RouteFormDefinition } from '@/wayfinder';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Company } from '@/types';

type CompanyFormProps = {
    form: RouteFormDefinition<'post'>;
    company?: Company;
    cancelHref: string;
    submitLabel: string;
};

const fields = [
    { name: 'name', label: 'Name', required: true },
    { name: 'domain', label: 'Domain', placeholder: 'acme.com' },
    { name: 'industry', label: 'Industry' },
    { name: 'size', label: 'Employees', placeholder: '51-200' },
    { name: 'city', label: 'City' },
    { name: 'state', label: 'State', placeholder: 'TX' },
    { name: 'phone', label: 'Phone' },
] as const;

export default function CompanyForm({
    form,
    company,
    cancelHref,
    submitLabel,
}: CompanyFormProps) {
    return (
        <Form {...form} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        {fields.map((field) => (
                            <div key={field.name} className="grid gap-2">
                                <Label htmlFor={field.name}>
                                    {field.label}
                                </Label>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    defaultValue={company?.[field.name] ?? ''}
                                    required={'required' in field}
                                    placeholder={
                                        'placeholder' in field
                                            ? field.placeholder
                                            : undefined
                                    }
                                    aria-invalid={
                                        errors[field.name] ? true : undefined
                                    }
                                />
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}
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
