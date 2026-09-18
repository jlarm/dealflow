import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * A minimal layout for pages outside DealFlow that prospects see, such as
 * the unsubscribe page. It deliberately has no links into the app.
 */
export default function PublicLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-8">
                <div className="flex flex-col items-center gap-4">
                    <AppLogoIcon className="h-9 w-auto" />
                    <div className="space-y-2 text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-muted-foreground text-sm">
                            {description}
                        </p>
                    </div>
                </div>
                {children}
            </div>
        </div>
    );
}
