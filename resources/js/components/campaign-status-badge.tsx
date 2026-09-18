import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { CampaignStatusValue } from '@/types';

const statusClasses: Record<CampaignStatusValue, string> = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
    paused: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    completed:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
};

export default function CampaignStatusBadge({
    status,
    label,
}: {
    status: CampaignStatusValue;
    label: string;
}) {
    return (
        <Badge variant="secondary" className={cn(statusClasses[status])}>
            {label}
        </Badge>
    );
}
