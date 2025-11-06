import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import { Copy, Edit, Eye, EyeOff, Rss, Trash2 } from 'lucide-react';

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

interface FeedListProps {
    feeds: Feed[];
    canEdit?: boolean;
}

export default function FeedList({ feeds, canEdit = true }: FeedListProps) {
    const { toast } = useToast();

    const handleDelete = (feedId: number) => {
        if (confirm('Are you sure you want to delete this feed?')) {
            router.delete(`/feeds/${feedId}`, {
                onSuccess: () => {
                    // Feed deleted successfully
                },
                onError: (errors) => {
                    console.error('Error deleting feed:', errors);
                },
            });
        }
    };

    const handleCopyUrl = async (feed: Feed) => {
        const fullUrl = window.location.origin + getFeedUrl(feed);

        const fallbackCopyTextToClipboard = (text: string) => {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                toast({
                    title: 'URL copied!',
                    description: 'Feed URL has been copied to your clipboard.',
                });
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
                toast({
                    title: 'Failed to copy',
                    description: 'Could not copy the URL to clipboard.',
                    variant: 'destructive',
                });
            }

            document.body.removeChild(textArea);
        };

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(fullUrl);
                toast({
                    title: 'URL copied!',
                    description: 'Feed URL has been copied to your clipboard.',
                });
            } else {
                fallbackCopyTextToClipboard(fullUrl);
            }
        } catch (err) {
            console.error('Failed to copy URL:', err);
            fallbackCopyTextToClipboard(fullUrl);
        }
    };

    const getFeedUrl = (feed: Feed) => {
        const baseUrl = `/rss/${feed.user_guid}/${feed.slug}`;
        if (!feed.is_public && feed.token) {
            return `${baseUrl}?token=${feed.token}`;
        }
        return baseUrl;
    };

    if (feeds.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-8">
                    <Rss className="mb-4 h-12 w-12 text-muted-foreground" />
                    <h3 className="mb-2 text-lg font-semibold">No feeds yet</h3>
                    <p className="text-center text-muted-foreground">Create your first feed to get started with your podcast.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {feeds.map((feed) => (
                <Card key={feed.id}>
                    <CardHeader className="pb-3">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <CardTitle className="text-lg">{feed.title}</CardTitle>
                                <CardDescription className="mt-1 line-clamp-2">{feed.description || 'No description provided'}</CardDescription>
                            </div>
                            <div className="ml-4 flex items-center gap-2">
                                <Badge variant={feed.is_public ? 'default' : 'secondary'}>
                                    {feed.is_public ? (
                                        <>
                                            <Eye className="mr-1 h-3 w-3" />
                                            Public
                                        </>
                                    ) : (
                                        <>
                                            <EyeOff className="mr-1 h-3 w-3" />
                                            Private
                                        </>
                                    )}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <div className="space-y-3">
                            <div className="text-sm text-muted-foreground">
                                <a href={getFeedUrl(feed)} target="_blank" rel="noopener noreferrer" className="underline hover:text-foreground">
                                    {getFeedUrl(feed)}
                                </a>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => handleCopyUrl(feed)}>
                                    <Copy className="h-4 w-4" />
                                </Button>
                                {canEdit && (
                                    <>
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={`/feeds/${feed.id}/edit`}>
                                                <Edit className="h-4 w-4" />
                                            </a>
                                        </Button>
                                        <Button variant="destructive" size="sm" onClick={() => handleDelete(feed.id)}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
