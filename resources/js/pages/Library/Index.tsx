import MediaUploadDialog from '@/components/media-upload-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FileAudio, FileVideo, Trash2 } from 'lucide-react';

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

interface LibraryIndexProps {
    libraryItems: LibraryItem[];
    flash?: {
        success?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Library',
        href: '/library',
    },
];

export default function LibraryIndex({ libraryItems, flash }: LibraryIndexProps) {
    const handleUploadSuccess = () => {
        // Reload the page to show new items
        window.location.reload();
    };

    const handleDelete = (itemId: number) => {
        if (confirm('Are you sure you want to remove this item from your library?')) {
            useForm().delete(route('library.destroy', itemId), {
                onSuccess: () => {
                    // Item deleted successfully
                    window.location.reload();
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

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Media Library</h1>
                    <MediaUploadDialog onUploadSuccess={handleUploadSuccess} />
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
                            <Card key={item.id} className="relative">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            {getFileIcon(item.media_file?.mime_type)}
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="truncate text-lg">{item.title}</CardTitle>
                                                <CardDescription className="text-xs">
                                                    {new Date(item.created_at).toLocaleDateString()}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDelete(item.id)}
                                            className="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {item.description && (
                                        <p className="mb-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{item.description}</p>
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
        </AppLayout>
    );
}
