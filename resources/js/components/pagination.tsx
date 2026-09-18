import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Laravel's paginator labels its previous/next links with HTML entities.
 */
function decodeLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

export default function Pagination({
    meta,
}: {
    meta: Paginated<unknown>['meta'];
}) {
    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <p className="text-muted-foreground">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>

            {meta.last_page > 1 && (
                <nav className="flex flex-wrap items-center gap-1">
                    {meta.links.map((link, position) =>
                        link.url ? (
                            <Link
                                key={`${position}-${link.label}`}
                                href={link.url}
                                preserveScroll
                                className={cn(
                                    'hover:bg-accent rounded-md px-3 py-1.5',
                                    link.active &&
                                        'bg-primary text-primary-foreground hover:bg-primary/90',
                                )}
                            >
                                {decodeLabel(link.label)}
                            </Link>
                        ) : (
                            <span
                                key={`${position}-${link.label}`}
                                className="text-muted-foreground px-3 py-1.5"
                            >
                                {decodeLabel(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </div>
    );
}
