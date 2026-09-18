import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ImportsCreate({
    expectedColumns,
}: {
    expectedColumns: string[];
}) {
    const [sourceList, setSourceList] = useState('');

    return (
        <>
            <Head title="Import CSV" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="Import CSV"
                    description="Contacts are matched by email. Existing contacts only get their blank fields filled in, and their stage never changes."
                />

                <div className="space-y-2 text-sm">
                    <p className="text-muted-foreground">
                        Apollo exports and dealer research lists both work
                        as-is. Recognised columns (common variations like
                        “Public Email” or “Dealership / Group” work too):
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {expectedColumns.map((column) => (
                            <Badge key={column} variant="outline">
                                {column}
                            </Badge>
                        ))}
                    </div>
                </div>

                <Form {...store.form()} className="space-y-6">
                    {({ processing, progress, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="file">CSV file</Label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        if (file && sourceList === '') {
                                            setSourceList(file.name);
                                        }
                                    }}
                                />
                                <InputError message={errors.file} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="source_list">Source list</Label>
                                <Input
                                    id="source_list"
                                    name="source_list"
                                    value={sourceList}
                                    onChange={(event) =>
                                        setSourceList(event.target.value)
                                    }
                                    placeholder="e.g. Q4 dealer list"
                                    required
                                />
                                <p className="text-muted-foreground text-sm">
                                    Saved on new contacts so you can filter by
                                    where they came from.
                                </p>
                                <InputError message={errors.source_list} />
                            </div>

                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    className="w-full"
                                />
                            )}

                            <div className="flex items-center gap-3">
                                <Button disabled={processing}>
                                    Upload and import
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ImportsCreate.layout = {
    breadcrumbs: [
        { title: 'Imports', href: index() },
        { title: 'Import CSV', href: create() },
    ],
};
