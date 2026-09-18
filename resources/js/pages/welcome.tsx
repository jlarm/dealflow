import { Head, Link, usePage } from '@inertiajs/react';
import { History, KanbanSquare, Send, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: Users,
        title: 'One source of truth',
        description:
            'Import contacts from every CSV export into a single, deduplicated database.',
    },
    {
        icon: KanbanSquare,
        title: 'A real pipeline',
        description:
            'Move leads from New to Won on a kanban board and know where everyone stands.',
    },
    {
        icon: Send,
        title: 'Outreach that tracks itself',
        description:
            'Run email sequences and see sends, opens, replies, and bounces automatically.',
    },
    {
        icon: History,
        title: 'Full history',
        description:
            'Every email, call, note, and status change lands on the contact’s timeline.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
                    <div className="flex items-center">
                        <AppLogo />
                    </div>

                    <Button variant={auth.user ? 'default' : 'outline'} asChild>
                        <Link href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Go to dashboard' : 'Log in'}
                        </Link>
                    </Button>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-6 py-16">
                    <div className="max-w-2xl space-y-6">
                        <p className="text-sm font-medium tracking-wide text-[#008F95] uppercase">
                            CRM for cold outreach
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight text-balance text-[#051D43] sm:text-5xl dark:text-white">
                            Turn scattered CSV exports into a working pipeline.
                        </h1>
                        <p className="text-muted-foreground text-lg text-pretty">
                            DealFlow gives you more structure than a spreadsheet
                            without the overhead of an enterprise CRM, so you
                            know at a glance who’s been contacted and what
                            happened next.
                        </p>
                        <Button size="lg" asChild>
                            <Link href={auth.user ? dashboard() : login()}>
                                {auth.user
                                    ? 'Open DealFlow'
                                    : 'Log in to DealFlow'}
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="bg-card space-y-2 rounded-xl border p-5"
                            >
                                <feature.icon className="size-5 text-[#008F95]" />
                                <h2 className="font-medium">{feature.title}</h2>
                                <p className="text-muted-foreground text-sm">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
