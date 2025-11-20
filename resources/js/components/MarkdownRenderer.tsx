import { cn } from '@/lib/utils';

interface MarkdownRendererProps {
    content: string;
    className?: string;
}

export default function MarkdownRenderer({
    content,
    className,
}: MarkdownRendererProps) {
    // Render with dangerouslySetInnerHTML for formatted text
    const renderFormattedMarkdown = (text: string) => {
        const html = text
            // Escape HTML first
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            // Then apply markdown replacements
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/__(.*?)__/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(
                /\[(.*?)\]\((.*?)\)/g,
                '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline">$1</a>',
            );

        return html;
    };

    return (
        <div
            className={cn(
                'prose prose-sm dark:prose-invert max-w-none',
                'prose-p:leading-relaxed prose-p:mb-2',
                'prose-strong:font-semibold',
                'prose-em:italic',
                'prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-sm prose-code:font-mono',
                'prose-a:text-primary prose-a:underline',
                'prose-li:ml-4',
                className,
            )}
            dangerouslySetInnerHTML={{
                __html: renderFormattedMarkdown(content),
            }}
        />
    );
}
