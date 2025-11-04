import CreateFeedForm from '@/components/create-feed-form';
import FeedList from '@/components/feed-list';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Feed {
    id: number;
    title: string;
    description?: string;
    is_public: boolean;
    slug: string;
    user_guid: string;
    token?: string;
    created_at: string;
    updated_at: string;
}

interface DashboardProps {
    feeds: Feed[];
    flash?: {
        success?: string;
    };
}

export default function Dashboard({ feeds, flash }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {flash?.success && (
                <div className="mb-4 rounded-md bg-green-50 p-4 dark:bg-green-900/20">
                    <p className="text-sm text-green-800 dark:text-green-200">{flash.success}</p>
                </div>
            )}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {/* Top Left Panel - Feed Management */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Your Feeds</h2>
                        </div>
                        <CreateFeedForm />
                        <FeedList feeds={feeds} />
                    </div>

                    {/* Top Middle Panel */}
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
                            <div className="text-center">
                                <h3 className="mb-2 text-lg font-semibold">Library Items</h3>
                                <p className="text-sm">Your media library will appear here</p>
                            </div>
                        </div>
                    </div>

                    {/* Top Right Panel */}
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
                            <div className="text-center">
                                <h3 className="mb-2 text-lg font-semibold">Analytics</h3>
                                <p className="text-sm">Feed statistics and insights</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Bottom Panel */}
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
                        <div className="text-center">
                            <h3 className="mb-2 text-lg font-semibold">Feed Items</h3>
                            <p className="text-sm">Manage your podcast episodes and content</p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
