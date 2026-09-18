import { Head, Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/ImportController';
import Heading from '@/components/heading';
import ImportStatusBadge from '@/components/import-status-badge';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import type { Import, Paginated } from '@/types';

export default function ImportsIndex({
    imports,
}: {
    imports: Paginated<Import>;
}) {
    return (
        <>
            <Head title="Imports" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Imports"
                        description="CSV uploads and how each one went"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Upload />
                            Import CSV
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">File</th>
                                <th className="px-4 py-3 font-medium">
                                    Source list
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Rows
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Failed
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Uploaded
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {imports.data.map((item) => (
                                <tr key={item.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(item.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {item.filename}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.source_list}
                                    </td>
                                    <td className="px-4 py-3">
                                        <ImportStatusBadge
                                            status={item.status}
                                            label={item.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {item.row_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {item.failed_rows}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {item.created_at &&
                                            new Date(
                                                item.created_at,
                                            ).toLocaleString(undefined, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        {item.user && ` · ${item.user.name}`}
                                    </td>
                                </tr>
                            ))}

                            {imports.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        Nothing imported yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={imports.meta} />
            </div>
        </>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Imports', href: index() }],
};
