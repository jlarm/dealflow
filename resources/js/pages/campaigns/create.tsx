import { Head } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/CampaignController';
import CampaignForm from '@/components/campaign-form';
import Heading from '@/components/heading';

export default function CampaignsCreate() {
    return (
        <>
            <Head title="New campaign" />

            <div className="p-4">
                <Heading
                    title="New campaign"
                    description="Campaigns start as drafts. Add their emails, then activate them when you're ready to send."
                />

                <CampaignForm
                    form={store.form()}
                    cancelHref={index.url()}
                    submitLabel="Create campaign"
                />
            </div>
        </>
    );
}

CampaignsCreate.layout = {
    breadcrumbs: [
        { title: 'Campaigns', href: index() },
        { title: 'New campaign', href: create() },
    ],
};
