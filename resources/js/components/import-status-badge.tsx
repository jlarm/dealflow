import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Import } from '@/types';

const statusClasses: Record<Import['status'], string> = {
    processing:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
    completed:
        'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
};

export default function ImportStatusBadge({
    status,
    label,
}: {
    status: Import['status'];
    label: string;
}) {
    return (
        <Badge variant="secondary" className={cn(statusClasses[status])}>
            {label}
        </Badge>
    );
}
