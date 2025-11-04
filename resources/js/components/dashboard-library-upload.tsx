import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { FileAudio, FileVideo, Plus, Upload } from 'lucide-react';
import { useState } from 'react';

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

interface DashboardLibraryUploadProps {
    libraryItems: LibraryItem[];
    onUploadSuccess?: () => void;
}

export default function DashboardLibraryUpload({ libraryItems, onUploadSuccess }: DashboardLibraryUploadProps) {
    const [isUploadDialogOpen, setIsUploadDialogOpen] = useState(false);
    const [isDragOver, setIsDragOver] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        file: null as File | null,
    });

    const handleFileSelect = (file: File) => {
        setSelectedFile(file);
        setData('file', file);
        if (!data.title) {
            setData('title', file.name.replace(/\.[^/.]+$/, ''));
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOver(false);

        const files = Array.from(e.dataTransfer.files);
        const mediaFile = files.find((file) => file.type.startsWith('audio/') || file.type.startsWith('video/'));

        if (mediaFile) {
            handleFileSelect(mediaFile);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOver(true);
    };

    const handleDragLeave = () => {
        setIsDragOver(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('library.store'), {
            onSuccess: () => {
                reset();
                setSelectedFile(null);
                setIsUploadDialogOpen(false);
                onUploadSuccess?.();
            },
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

    const recentItems = libraryItems.slice(0, 3);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">Library Items</h2>
                <Dialog open={isUploadDialogOpen} onOpenChange={setIsUploadDialogOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Item
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle>Upload Media File</DialogTitle>
                            <DialogDescription>
                                Upload audio or video files to your library. Supported formats: MP3, MP4, M4A, WAV, OGG (Max: 500MB)
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div
                                className={`rounded-lg border-2 border-dashed p-6 text-center transition-colors ${
                                    isDragOver ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600'
                                }`}
                                onDrop={handleDrop}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                            >
                                <Upload className="mx-auto h-12 w-12 text-gray-400" />
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Drag and drop a file here, or click to select</p>
                                <input
                                    type="file"
                                    accept="audio/*,video/*"
                                    onChange={(e) => e.target.files?.[0] && handleFileSelect(e.target.files[0])}
                                    className="hidden"
                                    id="file-upload"
                                />
                                <Label htmlFor="file-upload" className="cursor-pointer text-sm text-blue-600 hover:text-blue-500">
                                    Browse Files
                                </Label>
                            </div>

                            {selectedFile && (
                                <div className="text-sm text-gray-600 dark:text-gray-400">
                                    Selected: {selectedFile.name} ({formatFileSize(selectedFile.size)})
                                </div>
                            )}

                            <div>
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Enter title"
                                    required
                                />
                                {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
                            </div>

                            <div>
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Enter description (optional)"
                                    rows={3}
                                />
                                {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setIsUploadDialogOpen(false);
                                        reset();
                                        setSelectedFile(null);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing || !selectedFile}>
                                    {processing ? 'Uploading...' : 'Upload'}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
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
                                    <p className="truncate text-sm font-medium">{item.title}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {item.media_file && formatFileSize(item.media_file.filesize)} •{' '}
                                        {new Date(item.created_at).toLocaleDateString()}
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
