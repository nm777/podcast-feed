import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GripVertical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface MediaFile {
    id: number;
    file_path: string;
    file_hash: string;
    mime_type: string;
    filesize: number;
    duration?: number;
}

interface LibraryItem {
    id: number;
    title: string;
    description?: string;
    source_type: string;
    source_url?: string;
    media_file?: MediaFile | null;
}

interface FeedItem {
    id: number;
    feed_id: number;
    library_item_id: number;
    sequence: number;
    library_item: LibraryItem;
}

interface Feed {
    id: number;
    title: string;
    description?: string;
    is_public: boolean;
    slug: string;
    user_guid: string;
    created_at: string;
    updated_at: string;
    items: FeedItem[];
}

interface EditFeedProps {
    feed: Feed;
    userLibraryItems: LibraryItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Edit Feed',
        href: '',
    },
];

export default function EditFeed({ feed, userLibraryItems }: EditFeedProps) {
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        title: feed.title,
        description: feed.description || '',
        is_public: feed.is_public,
        items: feed.items.map((item) => ({
            id: item.id,
            library_item_id: item.library_item_id,
            sequence: item.sequence,
        })),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/feeds/${feed.id}`);
    };

    const addLibraryItem = (libraryItemId: number) => {
        const newItem = {
            id: 0,
            library_item_id: libraryItemId,
            sequence: data.items.length,
        };
        setData('items', [...data.items, newItem]);
    };

    const removeItem = (index: number) => {
        const newItems = data.items.filter((_, i) => i !== index);
        setData(
            'items',
            newItems.map((item, i) => ({ ...item, sequence: i })),
        );
    };

    const handleDragStart = (index: number) => {
        setDraggedIndex(index);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = (e: React.DragEvent, dropIndex: number) => {
        e.preventDefault();
        if (draggedIndex === null) return;

        const draggedItem = data.items[draggedIndex];
        const newItems = [...data.items];
        newItems.splice(draggedIndex, 1);
        newItems.splice(dropIndex, 0, draggedItem);

        setData(
            'items',
            newItems.map((item, i) => ({ ...item, sequence: i })),
        );
        setDraggedIndex(null);
    };

    const getLibraryItem = (libraryItemId: number) => {
        return userLibraryItems.find((item) => item.id === libraryItemId);
    };

    const formatDuration = (seconds?: number) => {
        if (!seconds) return 'Unknown';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    };

    const formatFileSize = (bytes: number) => {
        const sizes = ['B', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 B';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const availableLibraryItems = userLibraryItems.filter((item) => !data.items.some((feedItem) => feedItem.library_item_id === item.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Feed: ${feed.title}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/dashboard">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-semibold">Edit Feed</h1>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Feed Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Feed Details</CardTitle>
                            <CardDescription>Update your feed's basic information</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
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

                                <div className="pt-4">
                                    <Button type="submit" disabled={processing} className="w-full">
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Media Items */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Feed Items</CardTitle>
                            <CardDescription>Manage the media items in your feed</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {data.items.length === 0 ? (
                                <div className="py-8 text-center text-muted-foreground">
                                    <p>No items in this feed yet</p>
                                    <p className="text-sm">Add items from your library below</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {data.items.map((item, index) => {
                                        const libraryItem = getLibraryItem(item.library_item_id);
                                        if (!libraryItem) return null;

                                        return (
                                            <div
                                                key={index}
                                                draggable
                                                onDragStart={() => handleDragStart(index)}
                                                onDragOver={handleDragOver}
                                                onDrop={(e) => handleDrop(e, index)}
                                                className="flex cursor-move items-center gap-3 rounded-lg border p-3 hover:bg-muted/50"
                                            >
                                                <GripVertical className="h-4 w-4 text-muted-foreground" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-medium">{libraryItem.title}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {libraryItem.media_file ? (
                                                            <>
                                                                {formatDuration(libraryItem.media_file.duration)} •{' '}
                                                                {formatFileSize(libraryItem.media_file.filesize)}
                                                            </>
                                                        ) : (
                                                            'Processing...'
                                                        )}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeItem(index)}
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {/* Add Library Items */}
                            {availableLibraryItems.length > 0 && (
                                <div className="border-t pt-4">
                                    <Label className="text-sm font-medium">Add Library Items</Label>
                                    <div className="mt-2 max-h-48 space-y-2 overflow-y-auto">
                                        {availableLibraryItems.map((libraryItem) => (
                                            <div key={libraryItem.id} className="flex items-center justify-between rounded-lg border p-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">{libraryItem.title}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {libraryItem.media_file ? (
                                                            <>
                                                                {formatDuration(libraryItem.media_file.duration)} •{' '}
                                                                {formatFileSize(libraryItem.media_file.filesize)}
                                                            </>
                                                        ) : (
                                                            'Processing...'
                                                        )}
                                                    </p>
                                                </div>
                                                <Button variant="ghost" size="sm" onClick={() => addLibraryItem(libraryItem.id)}>
                                                    <Plus className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {availableLibraryItems.length === 0 && data.items.length > 0 && (
                                <div className="border-t pt-4">
                                    <p className="text-center text-sm text-muted-foreground">All library items are already in this feed</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
