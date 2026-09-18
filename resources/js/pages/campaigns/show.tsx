import {
    Form,
    Head,
    Link,
    router,
    setLayoutProps,
    usePage,
} from '@inertiajs/react';
import { CircleAlert, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/CampaignController';
import { destroy as removeEnrollment } from '@/actions/App/Http/Controllers/CampaignEnrollmentController';
import { update as updateStatus } from '@/actions/App/Http/Controllers/CampaignStatusController';
import {
    destroy as destroyStep,
    store as storeStep,
    update as updateStep,
} from '@/actions/App/Http/Controllers/CampaignStepController';
import {
    index as contactsIndex,
    show as showContact,
} from '@/actions/App/Http/Controllers/ContactController';
import CampaignStatusBadge from '@/components/campaign-status-badge';
import ConfirmDelete from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    Campaign,
    CampaignEnrollment,
    CampaignStatusValue,
    CampaignStep,
    Paginated,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Stats = {
    enrolled: number;
    active: number;
    completed: number;
    stopped: number;
    sent: number;
};

type CampaignShowProps = {
    campaign: Campaign;
    steps: CampaignStep[];
    enrollments: Paginated<CampaignEnrollment>;
    stats: Stats;
    mergeFields: string[];
};

const stopReasons: Record<string, string> = {
    unsubscribed: 'Unsubscribed',
    email_not_verified: 'Email not verified',
    replied: 'Replied',
    bounced: 'Bounced',
};

export default function CampaignShow({
    campaign,
    steps,
    enrollments,
    stats,
    mergeFields,
}: CampaignShowProps) {
    const { errors } = usePage().props as { errors: Record<string, string> };

    setLayoutProps({
        breadcrumbs: [
            { title: 'Campaigns', href: index() },
            { title: campaign.name, href: show(campaign.id) },
        ],
    });

    const changeStatus = (status: CampaignStatusValue) => {
        router.patch(
            updateStatus.url(campaign.id),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={campaign.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {campaign.name}
                        </h1>
                        <CampaignStatusBadge
                            status={campaign.status}
                            label={campaign.status_label}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {(campaign.status === 'draft' ||
                            campaign.status === 'paused') && (
                            <Button onClick={() => changeStatus('active')}>
                                {campaign.status === 'draft'
                                    ? 'Activate'
                                    : 'Resume'}
                            </Button>
                        )}
                        {campaign.status === 'active' && (
                            <Button
                                variant="outline"
                                onClick={() => changeStatus('paused')}
                            >
                                Pause
                            </Button>
                        )}
                        {campaign.status !== 'completed' &&
                            campaign.status !== 'draft' && (
                                <Button
                                    variant="outline"
                                    onClick={() => changeStatus('completed')}
                                >
                                    Mark completed
                                </Button>
                            )}
                        <Button variant="outline" asChild>
                            <Link href={edit(campaign.id)}>
                                <Pencil />
                                Rename
                            </Link>
                        </Button>
                        <ConfirmDelete
                            form={destroy.form(campaign.id)}
                            title={`Delete ${campaign.name}?`}
                            description="This deletes the campaign, its emails, and its enrollments. Emails already sent stay on each contact's timeline."
                            trigger={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Delete campaign"
                                >
                                    <Trash2 />
                                </Button>
                            }
                        />
                    </div>
                </div>

                {(errors.status || errors.step) && (
                    <Alert variant="destructive">
                        <CircleAlert />
                        <AlertDescription>
                            {errors.status ?? errors.step}
                        </AlertDescription>
                    </Alert>
                )}

                <dl className="grid grid-cols-2 gap-4 rounded-xl border p-4 sm:grid-cols-5">
                    <Stat label="Enrolled" value={stats.enrolled} />
                    <Stat label="In progress" value={stats.active} />
                    <Stat label="Finished" value={stats.completed} />
                    <Stat label="Stopped" value={stats.stopped} />
                    <Stat label="Emails sent" value={stats.sent} />
                </dl>

                <div className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-4 lg:col-span-3">
                        <div className="space-y-1">
                            <h2 className="font-semibold">Emails</h2>
                            <p className="text-muted-foreground text-sm">
                                Merge fields:{' '}
                                {mergeFields.map((field) => (
                                    <code
                                        key={field}
                                        className="bg-muted mr-1 rounded px-1 py-0.5 text-xs"
                                    >{`{{${field}}}`}</code>
                                ))}
                                . Add a fallback with a bar, like{' '}
                                <code className="bg-muted rounded px-1 py-0.5 text-xs">
                                    {'{{first_name|there}}'}
                                </code>
                                .
                            </p>
                        </div>

                        {steps.map((step) => (
                            <StepCard
                                key={step.id}
                                campaign={campaign}
                                step={step}
                            />
                        ))}

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {steps.length === 0
                                        ? 'First email'
                                        : `Add email ${steps.length + 1}`}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <StepForm
                                    form={storeStep.form(campaign.id)}
                                    submitLabel="Add email"
                                    resetOnSuccess
                                    defaultDelay={steps.length === 0 ? 0 : 3}
                                    isFirst={steps.length === 0}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        <div className="space-y-1">
                            <h2 className="font-semibold">Contacts</h2>
                            <p className="text-muted-foreground text-sm">
                                Add contacts from the{' '}
                                <Link
                                    href={contactsIndex()}
                                    className="underline"
                                >
                                    contact list
                                </Link>
                                : filter it, then choose “Add to campaign”. Only
                                verified emails are added.
                            </p>
                        </div>

                        <div className="divide-y rounded-xl border">
                            {enrollments.data.map((enrollment) => (
                                <EnrollmentRow
                                    key={enrollment.id}
                                    campaign={campaign}
                                    enrollment={enrollment}
                                    totalSteps={steps.length}
                                />
                            ))}
                            {enrollments.data.length === 0 && (
                                <p className="text-muted-foreground px-4 py-8 text-center text-sm">
                                    No contacts yet.
                                </p>
                            )}
                        </div>

                        <Pagination meta={enrollments.meta} />
                    </div>
                </div>
            </div>
        </>
    );
}

function StepCard({
    campaign,
    step,
}: {
    campaign: Campaign;
    step: CampaignStep;
}) {
    const [editing, setEditing] = useState(false);

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1">
                    <CardTitle>Email {step.position}</CardTitle>
                    <p className="text-muted-foreground text-sm">
                        {step.position === 1
                            ? step.delay_days === 0
                                ? 'Sent as soon as a contact is enrolled'
                                : `Sent ${step.delay_days} days after enrolling`
                            : `Sent ${step.delay_days} ${step.delay_days === 1 ? 'day' : 'days'} after email ${step.position - 1}`}
                    </p>
                </div>
                <div className="flex gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setEditing(!editing)}
                    >
                        {editing ? 'Cancel' : 'Edit'}
                    </Button>
                    {campaign.status === 'draft' && (
                        <ConfirmDelete
                            form={destroyStep.form({
                                campaign: campaign.id,
                                step: step.id,
                            })}
                            title={`Remove email ${step.position}?`}
                            description="The emails after it move up one place."
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove email ${step.position}`}
                                >
                                    <Trash2 />
                                </Button>
                            }
                        />
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {editing ? (
                    <StepForm
                        form={updateStep.form({
                            campaign: campaign.id,
                            step: step.id,
                        })}
                        step={step}
                        submitLabel="Save email"
                        onSuccess={() => setEditing(false)}
                        isFirst={step.position === 1}
                    />
                ) : (
                    <div className="space-y-2 text-sm">
                        <p className="font-medium">{step.subject}</p>
                        <p className="text-foreground/80 break-words whitespace-pre-line">
                            {step.body}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function StepForm({
    form,
    step,
    submitLabel,
    resetOnSuccess = false,
    defaultDelay = 0,
    isFirst,
    onSuccess,
}: {
    form: RouteFormDefinition<'post'>;
    step?: CampaignStep;
    submitLabel: string;
    resetOnSuccess?: boolean;
    defaultDelay?: number;
    isFirst: boolean;
    onSuccess?: () => void;
}) {
    const fieldId = (name: string) => `${name}-${step?.id ?? 'new'}`;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={resetOnSuccess}
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={fieldId('subject')}>Subject</Label>
                        <Input
                            id={fieldId('subject')}
                            name="subject"
                            defaultValue={step?.subject ?? ''}
                            required
                        />
                        <InputError message={errors.subject} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={fieldId('body')}>Body</Label>
                        <Textarea
                            id={fieldId('body')}
                            name="body"
                            defaultValue={step?.body ?? ''}
                            rows={8}
                            placeholder={
                                'Hi {{first_name|there}},\n\nI noticed {{company}}…'
                            }
                            required
                        />
                        <InputError message={errors.body} />
                    </div>
                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor={fieldId('delay_days')}>
                            {isFirst
                                ? 'Days after enrolling'
                                : 'Days after the previous email'}
                        </Label>
                        <Input
                            id={fieldId('delay_days')}
                            name="delay_days"
                            type="number"
                            min={0}
                            max={90}
                            defaultValue={String(
                                step?.delay_days ?? defaultDelay,
                            )}
                            required
                        />
                        <InputError message={errors.delay_days} />
                    </div>
                    <Button disabled={processing}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}

function EnrollmentRow({
    campaign,
    enrollment,
    totalSteps,
}: {
    campaign: Campaign;
    enrollment: CampaignEnrollment;
    totalSteps: number;
}) {
    return (
        <div className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
            <div className="min-w-0 space-y-0.5">
                {enrollment.contact && (
                    <Link
                        href={showContact(enrollment.contact.id)}
                        className="block truncate font-medium hover:underline"
                    >
                        {enrollment.contact.name}
                    </Link>
                )}
                <p className="text-muted-foreground text-xs">
                    {enrollment.sequence_step} of {totalSteps} sent
                    {enrollment.state === 'active' &&
                        enrollment.next_send_at &&
                        ` · next ${new Date(enrollment.next_send_at).toLocaleDateString()}`}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                {enrollment.state === 'completed' && (
                    <Badge variant="secondary">Finished</Badge>
                )}
                {enrollment.state === 'stopped' && (
                    <Badge variant="outline">
                        {stopReasons[enrollment.stop_reason ?? ''] ?? 'Stopped'}
                    </Badge>
                )}
                <ConfirmDelete
                    form={removeEnrollment.form({
                        campaign: campaign.id,
                        enrollment: enrollment.id,
                    })}
                    title={`Remove ${enrollment.contact?.name ?? 'this contact'} from the campaign?`}
                    description="They won't get any more emails from this campaign. Emails already sent stay on their timeline."
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label="Remove from campaign"
                        >
                            <Trash2 />
                        </Button>
                    }
                />
            </div>
        </div>
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
