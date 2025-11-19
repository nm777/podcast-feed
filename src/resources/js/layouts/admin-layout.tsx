import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AdminLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

const adminBreadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Admin', href: '/admin' },
];

export default ({ children, breadcrumbs = [], ...props }: AdminLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={[...adminBreadcrumbs, ...breadcrumbs]} {...props}>
        {children}
    </AppLayoutTemplate>
);