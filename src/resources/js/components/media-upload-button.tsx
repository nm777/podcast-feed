import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Globe, Loader2, Plus, Upload, Youtube } from 'lucide-react';
import { useCallback, useState } from 'react';

interface MediaUploadButtonProps {
    onUploadSuccess?: () => void;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'icon';
}

export default function MediaUploadButton({ onUploadSuccess, variant = 'default', size = 'default' }: MediaUploadButtonProps) {
    const [isUploadDialogOpen, setIsUploadDialogOpen] = useState(false);
    const [isDragOver, setIsDragOver] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [inputType, setInputType] = useState<'file' | 'url' | 'youtube'>('file');
    const [isCheckingUrl, setIsCheckingUrl] = useState(false);
    const [isFetchingYouTubeTitle, setIsFetchingYouTubeTitle] = useState(false);
    const [urlDuplicateWarning, setUrlDuplicateWarning] = useState<string | null>(null);
    const [urlCheckTimeout, setUrlCheckTimeout] = useState<NodeJS.Timeout | null>(null);

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        description: '',
        file: null as File | null,
        url: '',
        source_url: '',
    });

    const extractYouTubeVideoId = (url: string): string | null => {
        const regex = /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
    };

    const fetchYouTubeVideoTitle = async (videoId: string): Promise<string | null> => {
        try {
            setIsFetchingYouTubeTitle(true);
            const response = await fetch(`/api/youtube/video-info/${videoId}`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data.title || null;
        } catch (error) {
            console.error('Failed to fetch YouTube video title:', error);
            return null;
        } finally {
            setIsFetchingYouTubeTitle(false);
        }
    };

    const handleFileSelect = (file: File) => {
        setSelectedFile(file);
        setData('file', file);
        setData('url', '');
        setData('source_url', '');
        if (!data.title) {
            setData('title', file.name.replace(/\.[^/.]+$/, ''));
        }
    };

    const checkUrlDuplicate = useCallback(async (url: string) => {
        if (!url || !url.startsWith('http')) {
            return;
        }

        setIsCheckingUrl(true);
        try {
            const response = await fetch('/check-url-duplicate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ url }),
                credentials: 'same-origin',
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${responseText}`);
            }

            const result = JSON.parse(responseText);

            if (result.is_duplicate) {
                setUrlDuplicateWarning(
                    'This URL is a duplicate of a file already in your library. The duplicate will be removed automatically after submission.',
                );
            } else {
                setUrlDuplicateWarning(null);
            }
        } catch (error) {
            // If duplicate check fails, continue without warning
            console.error('URL duplicate check failed:', error);
            setUrlDuplicateWarning(null);
        } finally {
            setIsCheckingUrl(false);
        }
    }, []);

    const handleInputTypeChange = (newType: 'file' | 'url' | 'youtube') => {
        setInputType(newType);
        // Clear all fields when switching input types
        setData('file', null);
        setData('url', '');
        setData('source_url', '');
        setData('title', '');
        setData('description', '');
        setSelectedFile(null);
        setUrlDuplicateWarning(null);
    };

    const handleUrlChange = async (url: string) => {
        setData('url', url);
        setData('file', null);
        setData('source_url', inputType === 'youtube' ? url : '');
        setSelectedFile(null);

        // For YouTube URLs, try to fetch the video title
        if (inputType === 'youtube' && url && !data.title) {
            const videoId = extractYouTubeVideoId(url);
            if (videoId) {
                const title = await fetchYouTubeVideoTitle(videoId);
                if (title) {
                    setData('title', title);
                }
            }
        }

        // For non-YouTube URLs or if title fetching fails, use filename fallback
        if (!data.title && url && inputType !== 'youtube') {
            try {
                const filename = new URL(url).pathname.split('/').pop() || '';
                const title = filename.replace(/\.[^/.]+$/, '');
                if (title) {
                    setData('title', title);
                }
            } catch {
                // Invalid URL, ignore
            }
        }

        // Clear existing timeout
        if (urlCheckTimeout) {
            clearTimeout(urlCheckTimeout);
        }

        // Debounce URL check to avoid too many API calls
        const timeout = setTimeout(() => {
            checkUrlDuplicate(url);
        }, 500);

        setUrlCheckTimeout(timeout);
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

        // Transform data to only include relevant field based on input type
        transform((data) => {
            if (inputType === 'file') {
                return {
                    title: data.title,
                    description: data.description,
                    file: data.file,
                };
            } else if (inputType === 'youtube') {
                return {
                    title: data.title,
                    description: data.description,
                    source_type: 'youtube',
                    source_url: data.url,
                };
            } else {
                return {
                    title: data.title,
                    description: data.description,
                    source_type: 'url',
                    url: data.url,
                };
            }
        });

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

    return (
        <Dialog open={isUploadDialogOpen} onOpenChange={setIsUploadDialogOpen}>
            <DialogTrigger asChild>
                <Button variant={variant} size={size}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Media
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Add Media File</DialogTitle>
                    <DialogDescription>
                        Upload a file, provide a URL, or add a YouTube video to extract audio. Supported formats: MP3, MP4, M4A, WAV, OGG (Max: 500MB)
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label>Source Type</Label>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant={inputType === 'file' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleInputTypeChange('file')}
                            >
                                <Upload className="mr-2 h-4 w-4" />
                                Upload File
                            </Button>
                            <Button
                                type="button"
                                variant={inputType === 'url' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleInputTypeChange('url')}
                            >
                                <Globe className="mr-2 h-4 w-4" />
                                From URL
                            </Button>
                            <Button
                                type="button"
                                variant={inputType === 'youtube' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleInputTypeChange('youtube')}
                            >
                                <Youtube className="mr-2 h-4 w-4" />
                                YouTube
                            </Button>
                        </div>
                    </div>

                    {inputType === 'file' ? (
                        <div>
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
                            {errors.file && <p className="mt-1 text-sm text-red-600">{errors.file}</p>}
                        </div>
                    ) : inputType === 'youtube' ? (
                        <div>
                            <Label htmlFor="url">YouTube URL</Label>
                            <Input
                                id="url"
                                type="url"
                                value={data.url}
                                onChange={(e) => handleUrlChange(e.target.value)}
                                placeholder="https://youtube.com/watch?v=..."
                                required
                            />
                            {errors.url && <p className="mt-1 text-sm text-red-600">{errors.url}</p>}
                            {errors.source_url && <p className="mt-1 text-sm text-red-600">{errors.source_url}</p>}
                            {isFetchingYouTubeTitle && (
                                <div className="mt-2 flex items-center gap-2 text-sm text-blue-600">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span>Fetching video title...</span>
                                </div>
                            )}
                            {urlDuplicateWarning && (
                                <div className="mt-2 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                    <AlertCircle className="h-4 w-4 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                                    <p className="text-sm text-amber-800 dark:text-amber-200">{urlDuplicateWarning}</p>
                                </div>
                            )}
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Audio will be extracted from the YouTube video and added to your library.
                            </p>
                        </div>
                    ) : (
                        <div>
                            <Label htmlFor="url">Media URL</Label>
                            <Input
                                id="url"
                                type="url"
                                value={data.url}
                                onChange={(e) => handleUrlChange(e.target.value)}
                                placeholder="https://example.com/audio.mp3"
                                required
                            />
                            {errors.url && <p className="mt-1 text-sm text-red-600">{errors.url}</p>}
                            {urlDuplicateWarning && (
                                <div className="mt-2 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                    <AlertCircle className="h-4 w-4 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                                    <p className="text-sm text-amber-800 dark:text-amber-200">{urlDuplicateWarning}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {selectedFile && (
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            Selected: {selectedFile.name} ({formatFileSize(selectedFile.size)})
                        </div>
                    )}

                    <div>
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Enter title" required />
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
                                setUrlDuplicateWarning(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || isCheckingUrl || (!selectedFile && !data.url)}>
                            {processing
                                ? 'Processing...'
                                : isCheckingUrl || isFetchingYouTubeTitle
                                  ? 'Checking...'
                                  : inputType === 'file'
                                    ? 'Upload'
                                    : inputType === 'youtube'
                                      ? 'Extract Audio'
                                      : 'Add'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
