import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/CampaignController';
import CampaignStatusBadge from '@/components/campaign-status-badge';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import type { Campaign, Paginated } from '@/types';

export default function CampaignsIndex({
    campaigns,
}: {
    campaigns: Paginated<Campaign>;
}) {
    return (
        <>
            <Head title="Campaigns" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Campaigns"
                        description="Email sequences sent to verified contacts, on weekdays within your sending window"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New campaign
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Emails
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Enrolled
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    In progress
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {campaigns.data.map((campaign) => (
                                <tr
                                    key={campaign.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(campaign.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {campaign.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <CampaignStatusBadge
                                            status={campaign.status}
                                            label={campaign.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {campaign.steps_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {campaign.enrollments_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {campaign.active_enrollments_count}
                                    </td>
                                </tr>
                            ))}

                            {campaigns.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        No campaigns yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={campaigns.meta} />
            </div>
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
