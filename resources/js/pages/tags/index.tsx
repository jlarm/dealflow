import { Form, Head, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/TagController';
import ConfirmDelete from '@/components/confirm-delete';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Tag } from '@/types';

export default function TagsIndex({ tags }: { tags: Tag[] }) {
    return (
        <>
            <Head title="Tags" />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <Heading
                    title="Tags"
                    description="Segment contacts beyond their source list, e.g. dealership type or buying stage"
                />

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <div className="flex-1 space-y-2">
                            <div className="flex gap-2">
                                <Input
                                    name="name"
                                    placeholder="New tag name"
                                    aria-label="New tag name"
                                    required
                                />
                                <Button disabled={processing}>Add tag</Button>
                            </div>
                            <InputError message={errors.name} />
                        </div>
                    )}
                </Form>

                <div className="divide-y rounded-xl border">
                    {tags.map((tag) => (
                        <div
                            key={tag.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <Form
                                {...update.form(tag.id)}
                                options={{ preserveScroll: true }}
                                className="flex flex-1 items-start gap-2"
                            >
                                {({ processing, errors, isDirty }) => (
                                    <div className="flex-1 space-y-1">
                                        <div className="flex gap-2">
                                            <Input
                                                name="name"
                                                defaultValue={tag.name}
                                                aria-label={`Rename ${tag.name}`}
                                                required
                                            />
                                            {isDirty && (
                                                <Button
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Save
                                                </Button>
                                            )}
                                        </div>
                                        <InputError message={errors.name} />
                                    </div>
                                )}
                            </Form>

                            <Link
                                href={contactsIndex({ query: { tag: tag.id } })}
                                className="text-muted-foreground w-24 text-right text-sm tabular-nums hover:underline"
                            >
                                {tag.contacts_count}{' '}
                                {tag.contacts_count === 1
                                    ? 'contact'
                                    : 'contacts'}
                            </Link>

                            <ConfirmDelete
                                form={destroy.form(tag.id)}
                                title={`Delete the "${tag.name}" tag?`}
                                description="The tag is removed from every contact. The contacts themselves are not affected."
                                trigger={
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Delete ${tag.name}`}
                                    >
                                        <Trash2 />
                                    </Button>
                                }
                            />
                        </div>
                    ))}

                    {tags.length === 0 && (
                        <p className="text-muted-foreground px-4 py-12 text-center text-sm">
                            No tags yet.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

TagsIndex.layout = {
    breadcrumbs: [{ title: 'Tags', href: index() }],
};
