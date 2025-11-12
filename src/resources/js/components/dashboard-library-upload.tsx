import MediaUploadButton from '@/components/media-upload-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { CheckCircle, FileAudio, FileVideo, Loader2, RefreshCw, Upload, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

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
    processing_status: 'pending' | 'processing' | 'completed' | 'failed';
    processing_started_at?: string;
    processing_completed_at?: string;
    processing_error?: string;
    created_at: string;
    updated_at: string;
    media_file?: MediaFile;
}

interface DashboardLibraryUploadProps {
    libraryItems: LibraryItem[];
    onUploadSuccess?: () => void;
}

export default function DashboardLibraryUpload({ libraryItems, onUploadSuccess }: DashboardLibraryUploadProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    // Auto-refresh for processing items using custom polling
    useEffect(() => {
        const hasProcessingItems = libraryItems.some((item) => item.processing_status === 'pending' || item.processing_status === 'processing');

        if (!hasProcessingItems) return;

        const interval = setInterval(() => {
            router.reload({ only: ['libraryItems'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [libraryItems]);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['libraryItems'],
            onFinish: () => setIsRefreshing(false),
        });
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
            return <FileAudio className="h-6 w-6 text-blue-500" />;
        }
        if (mimeType?.startsWith('video/')) {
            return <FileVideo className="h-6 w-6 text-purple-500" />;
        }
        return <FileAudio className="h-6 w-6 text-gray-500" />;
    };

    const getProcessingStatusIcon = (status: string) => {
        switch (status) {
            case 'processing':
                return <Loader2 className="h-4 w-4 animate-spin text-blue-500" />;
            case 'completed':
                return <CheckCircle className="h-4 w-4 text-green-500" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-500" />;
            default:
                return <Loader2 className="h-4 w-4 text-gray-400" />;
        }
    };

    const getProcessingStatusColor = (status: string) => {
        switch (status) {
            case 'processing':
                return 'text-blue-600';
            case 'completed':
                return 'text-green-600';
            case 'failed':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    const recentItems = libraryItems.slice(0, 3);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <h2 className="text-lg font-semibold">Library Items</h2>
                    <Button variant="ghost" size="icon" onClick={handleRefresh} disabled={isRefreshing}>
                        <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                    </Button>
                </div>
                <MediaUploadButton onUploadSuccess={onUploadSuccess} variant="default" size="sm" />
            </div>

            {recentItems.length === 0 ? (
                <Card className="flex items-center justify-center p-8">
                    <div className="text-center">
                        <Upload className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-2 text-sm font-medium">No media files yet</h3>
                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">Upload your first media file to get started</p>
                    </div>
                </Card>
            ) : (
                <div className="space-y-2">
                    {recentItems.map((item) => (
                        <Card key={item.id} className="p-3">
                            <div className="flex items-center gap-3">
                                {getFileIcon(item.media_file?.mime_type)}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="truncate text-sm font-medium">{item.title}</p>
                                        {item.processing_status !== 'completed' && (
                                            <div className="flex items-center gap-1">
                                                {getProcessingStatusIcon(item.processing_status)}
                                                <span className={`text-xs font-medium ${getProcessingStatusColor(item.processing_status)}`}>
                                                    {item.processing_status === 'processing'
                                                        ? 'Processing'
                                                        : item.processing_status === 'failed'
                                                          ? 'Failed'
                                                          : 'Pending'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {item.media_file && formatFileSize(item.media_file.filesize)} •{' '}
                                        {new Date(item.created_at).toLocaleDateString()}
                                        {item.processing_error && <span className="text-red-500"> • {item.processing_error}</span>}
                                    </p>
                                </div>
                            </div>
                        </Card>
                    ))}
                    {libraryItems.length > 3 && (
                        <p className="text-center text-xs text-gray-500 dark:text-gray-400">And {libraryItems.length - 3} more items</p>
                    )}
                </div>
            )}
        </div>
    );
}
