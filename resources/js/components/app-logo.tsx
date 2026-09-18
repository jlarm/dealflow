import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="h-6 w-auto shrink-0" />
            <span className="ml-2 truncate text-base leading-tight font-semibold tracking-tight">
                {name}
            </span>
        </>
    );
}
