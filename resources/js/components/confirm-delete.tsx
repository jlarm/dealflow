import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { RouteFormDefinition } from '@/wayfinder';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ConfirmDeleteProps = {
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    trigger: ReactNode;
};

export default function ConfirmDelete({
    form,
    title,
    description,
    trigger,
}: ConfirmDeleteProps) {
    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form {...form}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
