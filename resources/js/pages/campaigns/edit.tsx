import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/CampaignController';
import CampaignForm from '@/components/campaign-form';
import Heading from '@/components/heading';
import type { Campaign } from '@/types';

export default function CampaignsEdit({ campaign }: { campaign: Campaign }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Campaigns', href: index() },
            { title: campaign.name, href: show(campaign.id) },
            { title: 'Rename', href: edit(campaign.id) },
        ],
    });

    return (
        <>
            <Head title={`Rename ${campaign.name}`} />

            <div className="p-4">
                <Heading title="Rename campaign" />

                <CampaignForm
                    form={update.form(campaign.id)}
                    campaign={campaign}
                    cancelHref={show.url(campaign.id)}
                    submitLabel="Save"
                />
            </div>
        </>
    );
}
