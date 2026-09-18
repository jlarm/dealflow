import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useEffect } from 'react';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import { index, show } from '@/actions/App/Http/Controllers/ImportController';
import ImportStatusBadge from '@/components/import-status-badge';
import Pagination from '@/components/pagination';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { Import, ImportFailure, Paginated } from '@/types';

export default function ImportsShow({
    import: item,
    failures,
}: {
    import: Import;
    failures: Paginated<ImportFailure>;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Imports', href: index() },
            { title: item.filename, href: show(item.id) },
        ],
    });

    const isProcessing = item.status === 'processing';

    const { stop } = usePoll(
        2000,
        { only: ['import', 'failures'] },
        { autoStart: isProcessing },
    );

    useEffect(() => {
        if (!isProcessing) {
            stop();
        }
    }, [isProcessing, stop]);

    const percentage =
        item.row_count > 0
            ? Math.round((item.processed_rows / item.row_count) * 100)
            : 0;

    return (
        <>
            <Head title={item.filename} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {item.filename}
                            </h1>
                            <ImportStatusBadge
                                status={item.status}
                                label={item.status_label}
                            />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Source list “{item.source_list}”
                            {item.user && ` · uploaded by ${item.user.name}`}
                        </p>
                    </div>

                    {item.status === 'completed' && (
                        <Button variant="outline" asChild>
                            <Link
                                href={contactsIndex({
                                    query: { source_list: item.source_list },
                                })}
                            >
                                View contacts from this list
                            </Link>
                        </Button>
                    )}
                </div>

                {item.error && (
                    <Alert variant="destructive">
                        <CircleAlert />
                        <AlertTitle>The import did not finish</AlertTitle>
                        <AlertDescription>{item.error}</AlertDescription>
                    </Alert>
                )}

                <div className="space-y-3 rounded-xl border p-4">
                    <div className="flex items-center justify-between text-sm">
                        <span className="font-medium">
                            {isProcessing && item.row_count === 0
                                ? 'Reading file…'
                                : `${item.processed_rows} of ${item.row_count} rows processed`}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {percentage}%
                        </span>
                    </div>
                    <div
                        className="bg-muted h-2 overflow-hidden rounded-full"
                        role="progressbar"
                        aria-valuenow={percentage}
                        aria-valuemin={0}
                        aria-valuemax={100}
                    >
                        <div
                            className="h-full rounded-full bg-[#008F95] transition-[width] duration-500"
                            style={{ width: `${percentage}%` }}
                        />
                    </div>
                    <dl className="grid grid-cols-3 gap-4 pt-1 text-sm">
                        <Stat label="Rows" value={item.row_count} />
                        <Stat
                            label="Imported"
                            value={item.processed_rows - item.failed_rows}
                        />
                        <Stat label="Failed" value={item.failed_rows} />
                    </dl>
                </div>

                {failures.meta.total > 0 && (
                    <div className="space-y-3">
                        <h2 className="font-semibold">Failed rows</h2>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Row
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Problem
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Data
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {failures.data.map((failure) => (
                                        <tr key={failure.id}>
                                            <td className="px-4 py-3 align-top tabular-nums">
                                                {failure.row_number}
                                            </td>
                                            <td className="px-4 py-3 align-top text-red-600 dark:text-red-400">
                                                {failure.errors.join(' ')}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 align-top">
                                                {Object.entries(failure.raw_row)
                                                    .filter(
                                                        ([, value]) => value,
                                                    )
                                                    .map(
                                                        ([column, value]) =>
                                                            `${column}: ${value}`,
                                                    )
                                                    .join(' · ')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={failures.meta} />
                    </div>
                )}
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="text-lg font-semibold tabular-nums">{value}</dd>
        </div>
    );
}
