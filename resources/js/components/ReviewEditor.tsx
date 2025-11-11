'use client';

import { EditorContent, EditorContext, useEditor } from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import React from 'react';

import { Button } from '@/components/tiptap-ui-primitive/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/tiptap-ui-primitive/popover';
import { Spacer } from '@/components/tiptap-ui-primitive/spacer';
import {
    Toolbar,
    ToolbarGroup,
    ToolbarSeparator,
} from '@/components/tiptap-ui-primitive/toolbar';
import { BlockquoteButton } from '@/components/tiptap-ui/blockquote-button';
import { HeadingDropdownMenu } from '@/components/tiptap-ui/heading-dropdown-menu';
import { LinkPopover } from '@/components/tiptap-ui/link-popover';
import { ListDropdownMenu } from '@/components/tiptap-ui/list-dropdown-menu';
import { MarkButton } from '@/components/tiptap-ui/mark-button';
import { UndoRedoButton } from '@/components/tiptap-ui/undo-redo-button';

interface ReviewEditorProps {
    id?: string;
    name?: string;
    value: string; // markdown
    placeholder?: string;
    minLength?: number;
    onChange: (markdown: string) => void;
    error?: string;
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function basicMarkdownToHtml(md: string): string {
    // Minimal conversion for initial render (no lists/headers grouping)
    let html = escapeHtml(md);
    html = html
        .replace(/^###\s+(.*)$/gm, '<h3>$1</h3>')
        .replace(/^##\s+(.*)$/gm, '<h2>$1</h2>')
        .replace(/^#\s+(.*)$/gm, '<h1>$1</h1>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/__(.*?)__/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/_(.*?)_/g, '<em>$1</em>')
        .replace(/`(.*?)`/g, '<code>$1</code>')
        .replace(
            /\[(.*?)\]\((.*?)\)/g,
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline">$1<\/a>',
        )
        .replace(/\n\n+/g, '</p><p>')
        .replace(/\n/g, '<br/>');
    return `<p>${html}</p>`;
}

function applyMarks(
    text: string,
    marks?: Array<{ type: string; attrs?: any }>,
): string {
    if (!marks || marks.length === 0) {
        return text;
    }
    // Apply in order: code, bold, italic, link last
    let result = text;
    const has = (type: string) => marks.some((m) => m.type === type);

    if (has('code')) {
        result = `\`${result}\``;
    }
    if (has('bold')) {
        result = `**${result}**`;
    }
    if (has('italic')) {
        result = `*${result}*`;
    }
    const link = marks.find((m) => m.type === 'link');
    if (link?.attrs?.href) {
        result = `[${result}](${link.attrs.href})`;
    }
    return result;
}

function nodeToMarkdown(node: any): string {
    switch (node.type) {
        case 'doc':
            return (node.content || [])
                .map(nodeToMarkdown)
                .filter(Boolean)
                .join('\n\n');
        case 'paragraph': {
            const parts = (node.content || [])
                .map((n: any) => nodeToMarkdown(n))
                .join('');
            return parts.trim();
        }
        case 'text': {
            const text = node.text || '';
            return applyMarks(text, node.marks);
        }
        case 'heading': {
            const level = Math.min(Math.max(node.attrs?.level || 1, 1), 6);
            const inner = (node.content || [])
                .map((n: any) => nodeToMarkdown(n))
                .join('');
            return `${'#'.repeat(level)} ${inner}`.trim();
        }
        case 'bulletList': {
            const items = (node.content || [])
                .map((li: any) => nodeToMarkdown(li))
                .filter(Boolean);
            return items
                .map((line: string) =>
                    line
                        .split('\n')
                        .map((l: string) => `- ${l}`)
                        .join('\n'),
                )
                .join('\n');
        }
        case 'orderedList': {
            const items = (node.content || [])
                .map((li: any) => nodeToMarkdown(li))
                .filter(Boolean);
            return items
                .map((line: string) =>
                    line
                        .split('\n')
                        .map((l: string) => `1. ${l}`)
                        .join('\n'),
                )
                .join('\n');
        }
        case 'listItem': {
            // Unwrap list item content into paragraphs joined by newlines
            return (node.content || [])
                .map((n: any) => nodeToMarkdown(n))
                .filter(Boolean)
                .join('\n');
        }
        case 'blockquote': {
            const inner = (node.content || [])
                .map((n: any) => nodeToMarkdown(n))
                .join('\n');
            return inner
                .split('\n')
                .map((l: string) => `> ${l}`)
                .join('\n');
        }
        case 'hardBreak':
            return '  \n';
        case 'codeBlock': {
            const text = (node.content || [])
                .map((n: any) => n.text || '')
                .join('');
            return '```\n' + text + '\n```';
        }
        default:
            return '';
    }
}

const EMOJIS = [
    '😀',
    '😁',
    '😂',
    '🤣',
    '😊',
    '😍',
    '😘',
    '😎',
    '🤩',
    '😇',
    '🙂',
    '🤔',
    '😴',
    '🤗',
    '🙌',
    '👍',
    '👎',
    '👏',
    '🔥',
    '💯',
    '🎉',
    '🥳',
    '✨',
    '⭐',
    '🌟',
    '❤️',
    '💙',
    '💚',
    '💛',
    '💜',
];

export default function ReviewEditor({
    id,
    name,
    value,
    onChange,
    error,
    minLength = 10,
    placeholder,
}: ReviewEditorProps) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                codeBlock: false, // keep simple inline code only
                horizontalRule: false,
            }),
        ],
        content: basicMarkdownToHtml(value || ''),
        editorProps: {
            attributes: {
                'aria-label': 'Review content',
                autocomplete: 'off',
                autocorrect: 'off',
                autocapitalize: 'off',
                class: 'prose max-w-none p-3 sm:p-4 min-h-[12rem] focus:outline-none dark:prose-invert',
            },
        },
        onUpdate: ({ editor }) => {
            const json = editor.getJSON();
            const md = nodeToMarkdown(json).trim();
            onChange(md);
        },
    });

    // Keep external value in sync if it changes from parent (rare)
    React.useEffect(() => {
        if (!editor) return;
        const currentMd = nodeToMarkdown(editor.getJSON());
        if (currentMd !== value) {
            editor.commands.setContent(basicMarkdownToHtml(value || ''));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, editor]);

    const words = (value.trim().match(/\S+/g) || []).length;
    const chars = value.length;

    const [showPreview, setShowPreview] = React.useState(false);

    return (
        <div className="space-y-2">
            <EditorContext.Provider value={{ editor }}>
                <Toolbar>
                    <Spacer />
                    <ToolbarGroup>
                        <UndoRedoButton action="undo" />
                        <UndoRedoButton action="redo" />
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <HeadingDropdownMenu levels={[1, 2, 3]} />
                        <ListDropdownMenu
                            types={['bulletList', 'orderedList']}
                        />
                        <BlockquoteButton />
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <MarkButton type="bold" />
                        <MarkButton type="italic" />
                        <MarkButton type="code" />
                        <LinkPopover />
                        {/* Emoji picker */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button
                                    aria-label="Insert emoji"
                                    tooltip="Emoji"
                                >
                                    <span className="tiptap-button-emoji">
                                        😊
                                    </span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent aria-label="Emoji picker">
                                <div className="grid grid-cols-8 gap-2 p-1">
                                    {EMOJIS.map((e) => (
                                        <button
                                            key={e}
                                            type="button"
                                            className="flex h-8 w-8 items-center justify-center rounded hover:bg-muted"
                                            onClick={() => {
                                                if (!editor) return;
                                                editor
                                                    .chain()
                                                    .focus()
                                                    .insertContent(e)
                                                    .run();
                                            }}
                                            aria-label={`Insert ${e}`}
                                        >
                                            {e}
                                        </button>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>
                    </ToolbarGroup>

                    <ToolbarSeparator />

                    <ToolbarGroup>
                        <Button
                            type="button"
                            aria-label={
                                showPreview ? 'Hide preview' : 'Show preview'
                            }
                            tooltip={
                                showPreview ? 'Hide Preview' : 'Show Preview'
                            }
                            onClick={() => setShowPreview((p) => !p)}
                        >
                            {showPreview ? 'Edit' : 'Preview'}
                        </Button>
                    </ToolbarGroup>

                    <Spacer />

                    <ToolbarGroup>
                        <span className="text-xs text-muted-foreground">
                            {words} {words === 1 ? 'word' : 'words'} • {chars}{' '}
                            {chars === 1 ? 'char' : 'chars'}
                        </span>
                    </ToolbarGroup>
                </Toolbar>

                {showPreview ? (
                    <div
                        className="dark:prose-invert rounded-lg border bg-background p-4 text-sm"
                        aria-label="Rendered markdown preview"
                    >
                        {value.trim() ? (
                            <div
                                className="prose dark:prose-invert max-w-none"
                                dangerouslySetInnerHTML={{
                                    __html: basicMarkdownToHtml(value),
                                }}
                            />
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Nothing to preview yet. Start typing above.
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="rounded-lg border bg-background shadow-sm focus-within:ring-2 focus-within:ring-primary/20">
                        <EditorContent
                            id={id}
                            editor={editor}
                            className="min-h-[12rem]"
                            role="textbox"
                            aria-multiline="true"
                        />
                    </div>
                )}
            </EditorContext.Provider>

            {name && <input type="hidden" name={name} value={value} />}

            {error && <p className="text-sm text-destructive">{error}</p>}

            {placeholder && !value && (
                <p className="text-xs text-muted-foreground">{placeholder}</p>
            )}

            <p className="text-xs text-muted-foreground">
                Plain Markdown is stored. Supported: headings, lists, bold,
                italic, inline code, links, and emojis. Minimum {minLength}{' '}
                characters. Use the preview toggle to see rendered output.
            </p>
        </div>
    );
}
