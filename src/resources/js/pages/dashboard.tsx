import DashboardLibraryUpload from '@/components/dashboard-library-upload';
import FeedList from '@/components/feed-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

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

interface MediaFile {
    id: number;
    file_path: string;
    file_hash: string;
    mime_type: string;
    filesize: number;
    duration?: number;
    created_at: string;
    updated_at: string;
}

interface LibraryItem {
    id: number;
    user_id: number;
    media_file_id: number;
    title: string;
    description?: string;
    source_type: string;
    source_url?: string;
    created_at: string;
    updated_at: string;
    media_file?: MediaFile;
}

interface DashboardProps {
    feeds: Feed[];
    libraryItems: LibraryItem[];
    flash?: {
        success?: string;
    };
}

export default function Dashboard({ feeds, libraryItems, flash }: DashboardProps) {
    const [isCreateFeedExpanded, setIsCreateFeedExpanded] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        title: string;
        description: string;
        is_public: boolean;
    }>({
        title: '',
        description: '',
        is_public: false,
    });

    const handleUploadSuccess = () => {
        router.reload({ only: ['libraryItems'] });
    };

    const handleCreateFeedSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/feeds', {
            onSuccess: () => {
                reset();
                setIsCreateFeedExpanded(false);
                router.reload({ only: ['feeds'] });
            },
            onError: (errors) => {
                console.error('Error creating feed:', errors);
            },
        });
    };

    const handleCreateFeedCancel = () => {
        reset();
        setIsCreateFeedExpanded(false);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {flash?.success && (
                <div className="mb-4 rounded-md bg-green-50 p-4 dark:bg-green-900/20">
                    <p className="text-sm text-green-800 dark:text-green-200">{flash.success}</p>
                </div>
            )}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-2">
                    {/* Top Left Panel - Feed Management */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Your Feeds</h2>
                            {!isCreateFeedExpanded && (
                                <Button size="sm" onClick={() => setIsCreateFeedExpanded(true)}>
                                    Create New Feed
                                </Button>
                            )}
                        </div>

                        {isCreateFeedExpanded && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Create New Feed</CardTitle>
                                    <CardDescription>Set up a new podcast feed to organize and share your content.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleCreateFeedSubmit} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="title">Title</Label>
                                            <Input
                                                id="title"
                                                type="text"
                                                value={data.title}
                                                onChange={(e) => setData('title', e.target.value)}
                                                placeholder="Enter feed title"
                                                required
                                            />
                                            {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="description">Description</Label>
                                            <Textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) => setData('description', e.target.value)}
                                                placeholder="Enter feed description (optional)"
                                                rows={3}
                                            />
                                            {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                                        </div>

                                        <div className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                id="is_public"
                                                checked={data.is_public}
                                                onChange={(e) => setData('is_public', e.target.checked)}
                                                className="rounded border-gray-300"
                                            />
                                            <Label htmlFor="is_public">Make this feed public</Label>
                                        </div>

                                        <div className="flex gap-2 pt-4">
                                            <Button type="submit" disabled={processing}>
                                                {processing ? 'Creating...' : 'Create Feed'}
                                            </Button>
                                            <Button type="button" variant="outline" onClick={handleCreateFeedCancel}>
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        <FeedList feeds={feeds} />
                    </div>

                    {/* Top Right Panel - Library Items */}
                    <DashboardLibraryUpload libraryItems={libraryItems} onUploadSuccess={handleUploadSuccess} />
                </div>
            </div>
        </AppLayout>
    );
}
