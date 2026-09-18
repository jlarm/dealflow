import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusOption } from '@/types';

const colorClasses: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    cyan: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
};

export default function StatusBadge({
    status,
    className,
}: {
    status: StatusOption | undefined;
    className?: string;
}) {
    if (!status) {
        return null;
    }

    return (
        <Badge
            variant="secondary"
            className={cn(colorClasses[status.color], className)}
        >
            {status.label}
        </Badge>
    );
}
