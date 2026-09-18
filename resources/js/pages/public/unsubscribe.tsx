import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export default function Unsubscribe({
    email,
    unsubscribed,
    action,
}: {
    email: string;
    unsubscribed: boolean;
    action: string;
}) {
    return (
        <>
            <Head title="Unsubscribe" />

            {unsubscribed ? (
                <p className="text-muted-foreground text-center text-sm">
                    {email} won't receive any more of these emails.
                </p>
            ) : (
                <Form
                    action={action}
                    method="post"
                    className="flex flex-col items-center gap-4"
                >
                    {({ processing }) => (
                        <>
                            <p className="text-muted-foreground text-center text-sm">
                                Stop sending emails to {email}?
                            </p>
                            <Button disabled={processing} className="w-full">
                                Unsubscribe
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}

Unsubscribe.layout = {
    title: 'Unsubscribe',
    description: 'Manage the emails you receive from us',
};
