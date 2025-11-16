import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface BookCardProps {
    book: {
        id?: string | number;
        title: string;
        author: string;
        cover_url?: string | null;
        isbn?: string;
        rating?: number;
    };
    onClick?: () => void;
    className?: string;
    actionButton?: {
        icon: React.ReactNode;
        onClick: (e: React.MouseEvent) => void;
        label: string;
        variant?: 'default' | 'ghost' | 'outline';
    };
    isLoading?: boolean;
}

export default function BookCard({
    book,
    onClick,
    className,
    actionButton,
    isLoading = false,
}: BookCardProps) {
    return (
        <Card
            className={cn(
                'overflow-hidden transition-all hover:shadow-lg dark:hover:shadow-lg/20',
                onClick && !isLoading && 'cursor-pointer',
                isLoading && 'pointer-events-none opacity-60',
                className,
            )}
        >
            <CardContent className="p-0">
                <div className="flex h-full flex-col">
                    <div
                        onClick={isLoading ? undefined : onClick}
                        className="relative aspect-[2/3] w-full overflow-hidden bg-muted"
                    >
                        {isLoading && (
                            <div className="absolute inset-0 z-20 flex items-center justify-center bg-background/50 backdrop-blur-sm">
                                <div className="flex flex-col items-center gap-2">
                                    <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                    <p className="text-xs font-medium">
                                        Loading...
                                    </p>
                                </div>
                            </div>
                        )}
                        <img
                            src={(function () {
                                const placeholder =
                                    'https://placehold.co/600x900?text=No%20Cover';
                                const url = book.cover_url ?? '';
                                if (!url) return placeholder;
                                if (url.includes('placeholder.com'))
                                    return placeholder;
                                return url;
                            })()}
                            alt={book.title}
                            className="h-full w-full object-cover"
                            onError={(e) => {
                                const img = e.currentTarget as HTMLImageElement;
                                if ((img as any).dataset.fallbackApplied)
                                    return;
                                (img as any).dataset.fallbackApplied = 'true';
                                img.src =
                                    'https://placehold.co/600x900?text=No%20Cover';
                            }}
                        />
                        {actionButton && !isLoading && (
                            <div className="absolute right-2 bottom-2 z-10">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant={actionButton.variant ?? 'default'}
                                    onClick={actionButton.onClick}
                                    className="h-8 w-8 rounded-full shadow-lg"
                                    aria-label={actionButton.label}
                                >
                                    {actionButton.icon}
                                </Button>
                            </div>
                        )}
                    </div>

                    <div
                        onClick={isLoading ? undefined : onClick}
                        className="flex flex-1 flex-col gap-2 p-3"
                    >
                        <div>
                            <h3 className="line-clamp-2 text-sm leading-tight font-semibold">
                                {book.title}
                            </h3>
                            <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                {book.author}
                            </p>
                        </div>

                        {book.rating !== undefined && (
                            <div className="mt-auto flex items-center gap-1 border-t border-border/40 pt-2">
                                <span className="text-xs font-medium">
                                    {book.rating}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    /5
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
