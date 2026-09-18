import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Campaign } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export default function CampaignForm({
    form,
    campaign,
    cancelHref,
    submitLabel,
}: {
    form: RouteFormDefinition<'post'>;
    campaign?: Campaign;
    cancelHref: string;
    submitLabel: string;
}) {
    return (
        <Form {...form} className="max-w-xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={campaign?.name ?? ''}
                            placeholder="e.g. Q4 Texas dealer outreach"
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
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
