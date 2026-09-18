import {
    Building2,
    FileUp,
    KanbanSquare,
    LayoutGrid,
    Tags,
    Users,
} from 'lucide-react';
import { index as companiesIndex } from '@/actions/App/Http/Controllers/CompanyController';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import { index as importsIndex } from '@/actions/App/Http/Controllers/ImportController';
import { index as pipelineIndex } from '@/actions/App/Http/Controllers/PipelineController';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/TagController';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * The primary app navigation, shared by the header and sidebar layouts.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Pipeline',
        href: pipelineIndex(),
        icon: KanbanSquare,
    },
    {
        title: 'Contacts',
        href: contactsIndex(),
        icon: Users,
    },
    {
        title: 'Companies',
        href: companiesIndex(),
        icon: Building2,
    },
    {
        title: 'Tags',
        href: tagsIndex(),
        icon: Tags,
    },
    {
        title: 'Imports',
        href: importsIndex(),
        icon: FileUp,
    },
];
