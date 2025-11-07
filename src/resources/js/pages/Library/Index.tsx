import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import MediaPlayer from '@/components/media-player';
import MediaUploadButton from '@/components/media-upload-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, FileAudio, FileVideo, Play, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface MediaFile {
    id: number;
    file_path: string;
    file_hash: string;
    mime_type: string;
    filesize: number;
    duration?: number;
    public_url?: string;
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
    is_duplicate: boolean;
    duplicate_detected_at?: string;
    processing_status: 'pending' | 'processing' | 'completed' | 'failed';
    processing_started_at?: string;
    processing_completed_at?: string;
    processing_error?: string;
    created_at: string;
    updated_at: string;
    media_file?: MediaFile;
}

interface LibraryIndexProps {
    libraryItems: LibraryItem[];
    flash?: {
        success?: string;
        warning?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Library',
        href: '/library',
    },
];

export default function LibraryIndex({ libraryItems, flash }: LibraryIndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<number | null>(null);
    const [playingItem, setPlayingItem] = useState<LibraryItem | null>(null);
    const { delete: destroyForm } = useForm();

    // Auto-refresh for processing items using custom polling
    useEffect(() => {
        const hasProcessingItems = libraryItems.some((item) => item.processing_status === 'pending' || item.processing_status === 'processing');

        if (!hasProcessingItems) return;

        const interval = setInterval(() => {
            router.reload({ only: ['libraryItems'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [libraryItems]);

    const handleUploadSuccess = () => {
        // Reload the page to show new items
        router.reload({ only: ['libraryItems'] });
    };

    const handleDeleteClick = (itemId: number) => {
        setItemToDelete(itemId);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (itemToDelete) {
            destroyForm(route('library.destroy', itemToDelete), {
                onSuccess: () => {
                    // Item deleted successfully
                    router.reload({ only: ['libraryItems'] });
                },
            });
        }
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getFileIcon = (mimeType?: string) => {
        if (mimeType?.startsWith('audio/')) {
            return <FileAudio className="h-8 w-8 text-blue-500" />;
        }
        if (mimeType?.startsWith('video/')) {
            return <FileVideo className="h-8 w-8 text-purple-500" />;
        }
        return <FileAudio className="h-8 w-8 text-gray-500" />;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Media Library" />

            {flash?.success && (
                <Alert className="mb-4 border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}

            {flash?.warning && (
                <Alert className="mb-4 border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    <AlertDescription>{flash.warning}</AlertDescription>
                </Alert>
            )}

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Media Library</h1>
                    <MediaUploadButton onUploadSuccess={handleUploadSuccess} />
                </div>

                {libraryItems.length === 0 ? (
                    <Card className="flex items-center justify-center p-12">
                        <div className="text-center">
                            <h3 className="mt-4 text-lg font-semibold">No media files yet</h3>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Upload your first media file to get started</p>
                        </div>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {libraryItems.map((item) => (
                            <Card
                                key={item.id}
                                className={`relative overflow-hidden ${item.is_duplicate ? 'border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-900/10' : ''}`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            {getFileIcon(item.media_file?.mime_type)}
                                            <div className="min-w-0 flex-1">
                                                <button
                                                    onClick={() => setPlayingItem(item)}
                                                    className="flex w-full cursor-pointer items-center gap-2 truncate border-0 bg-transparent text-left text-lg leading-tight font-medium transition-colors hover:text-foreground/80"
                                                    title={item.title}
                                                >
                                                    <Play className="h-4 w-4" />
                                                    {item.title}
                                                </button>
                                                <CardDescription className="text-xs">
                                                    {new Date(item.created_at).toLocaleDateString()}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDeleteClick(item.id)}
                                            className="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    {item.is_duplicate && (
                                        <div className="flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300">
                                            <AlertCircle className="h-3 w-3" />
                                            <span>Duplicate file - will be removed automatically</span>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardContent className="overflow-hidden">
                                    {item.description && (
                                        <p className="mb-3 line-clamp-2 overflow-hidden text-sm text-gray-600 dark:text-gray-400">
                                            {item.description}
                                        </p>
                                    )}
                                    {item.media_file && (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            <p>Size: {formatFileSize(item.media_file.filesize)}</p>
                                            <p>Type: {item.media_file.mime_type}</p>
                                            {item.media_file.duration && (
                                                <p>
                                                    Duration: {Math.floor(item.media_file.duration / 60)}:
                                                    {(item.media_file.duration % 60).toString().padStart(2, '0')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <DeleteConfirmDialog
                isOpen={deleteDialogOpen}
                onClose={() => setDeleteDialogOpen(false)}
                onConfirm={handleDeleteConfirm}
                title="Delete Media Item"
                description="Are you sure you want to remove this item from your library? This action cannot be undone."
                confirmText="Delete"
                cancelText="Cancel"
                variant="destructive"
            />

            {/* Media Player Modal */}
            {playingItem && <MediaPlayer libraryItem={playingItem} isOpen={true} onClose={() => setPlayingItem(null)} />}

            <DeleteConfirmDialog
                isOpen={deleteDialogOpen}
                onClose={() => setDeleteDialogOpen(false)}
                onConfirm={handleDeleteConfirm}
                title="Delete Media Item"
                description="Are you sure you want to remove this item from your library? This action cannot be undone."
                confirmText="Delete"
                cancelText="Cancel"
                variant="destructive"
            />
        </AppLayout>
    );
}
