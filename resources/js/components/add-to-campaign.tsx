import { router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import { store as enroll } from '@/actions/App/Http/Controllers/CampaignEnrollmentController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CampaignOption } from '@/types';

/**
 * Enrolls every verified contact matching the contact list's current filters.
 */
export default function AddToCampaign({
    campaigns,
    filters,
    contactableCount,
}: {
    campaigns: CampaignOption[];
    filters: Record<string, string | number | null>;
    contactableCount: number;
}) {
    const [open, setOpen] = useState(false);
    const [campaignId, setCampaignId] = useState<string>('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.post(enroll.url(Number(campaignId)), filters, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setOpen(false);
                setCampaignId('');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={campaigns.length === 0}>
                    <Send />
                    Add to campaign
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add contacts to a campaign</DialogTitle>
                <DialogDescription>
                    {contactableCount === 0
                        ? 'No contacts matching these filters have a verified email, so there is no one to add.'
                        : `${contactableCount} ${contactableCount === 1 ? 'contact matching these filters has' : 'contacts matching these filters have'} a verified email and will be added. Contacts already in the campaign are skipped.`}
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="campaign">Campaign</Label>
                    <Select value={campaignId} onValueChange={setCampaignId}>
                        <SelectTrigger id="campaign" className="w-full">
                            <SelectValue placeholder="Choose a campaign" />
                        </SelectTrigger>
                        <SelectContent>
                            {campaigns.map((campaign) => (
                                <SelectItem
                                    key={campaign.id}
                                    value={String(campaign.id)}
                                >
                                    {campaign.name}
                                    {campaign.status !== 'active' &&
                                        ` (${campaign.status})`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        onClick={submit}
                        disabled={
                            campaignId === '' ||
                            contactableCount === 0 ||
                            processing
                        }
                    >
                        Add contacts
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
