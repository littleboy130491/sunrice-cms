import * as React from 'react';
import { useEditor, useEditorState, EditorContent, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Bold, Heading2, Heading3, Italic, List, ListOrdered, Quote, Redo2, Undo2, Image as ImageIcon, Link as LinkIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import AssetPicker from '@/components/AssetPicker';
import type { FieldProps } from './types';

export default function RichTextField({ value, onChange }: FieldProps) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false }),
            Image.extend({ addAttributes() {
                return { ...this.parent?.(), 'data-asset-id': { default: null } };
            } }),
        ],
        content: (value as string) ?? '',
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // The value can change from outside (switching language, restoring a
    // revision): show it, without echoing it back as an edit.
    const html = (value as string) ?? '';
    React.useEffect(() => {
        if (editor && !editor.isDestroyed && html !== editor.getHTML()) {
            editor.commands.setContent(html, { emitUpdate: false });
        }
    }, [editor, html]);

    if (!editor) return null;

    return (
        <div className="rounded-md border">
            <div className="flex flex-wrap items-center gap-0.5 border-b p-1">
                <Toolbar editor={editor} />
                <AssetPicker
                    imageOnly
                    trigger={
                        <Button type="button" variant="ghost" size="icon" title="Insert image from library" aria-label="Insert image from library">
                            <ImageIcon className="h-4 w-4" />
                        </Button>
                    }
                    onSelect={(assets) => {
                        assets.forEach((a) => {
                            editor.chain().focus().setImage({ src: a.url }).updateAttributes('image', { 'data-asset-id': a.id }).run();
                        });
                    }}
                />
            </div>
            <EditorContent editor={editor} className="prose prose-sm max-w-none p-3 [&_.ProseMirror]:min-h-32 [&_.ProseMirror]:outline-none" />
        </div>
    );
}

/** Formatting buttons; the active ones (bold text under the cursor…) are highlighted. */
function Toolbar({ editor }: { editor: Editor }) {
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) => ({
            bold: e.isActive('bold'),
            italic: e.isActive('italic'),
            h2: e.isActive('heading', { level: 2 }),
            h3: e.isActive('heading', { level: 3 }),
            bullet: e.isActive('bulletList'),
            ordered: e.isActive('orderedList'),
            quote: e.isActive('blockquote'),
            link: e.isActive('link'),
            canUndo: e.can().undo(),
            canRedo: e.can().redo(),
        }),
    });
    const chain = () => editor.chain().focus();
    const tool = (label: string, icon: React.ReactNode, onClick: () => void, active = false, disabled = false) => (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn('size-8', active && 'bg-accent text-accent-foreground')}
            aria-label={label}
            aria-pressed={active}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {icon}
        </Button>
    );

    return (
        <>
            {tool('Bold', <Bold className="h-4 w-4" />, () => chain().toggleBold().run(), state.bold)}
            {tool('Italic', <Italic className="h-4 w-4" />, () => chain().toggleItalic().run(), state.italic)}
            <span className="mx-1 h-5 w-px bg-border" aria-hidden />
            {tool('Heading', <Heading2 className="h-4 w-4" />, () => chain().toggleHeading({ level: 2 }).run(), state.h2)}
            {tool('Subheading', <Heading3 className="h-4 w-4" />, () => chain().toggleHeading({ level: 3 }).run(), state.h3)}
            {tool('Bulleted list', <List className="h-4 w-4" />, () => chain().toggleBulletList().run(), state.bullet)}
            {tool('Numbered list', <ListOrdered className="h-4 w-4" />, () => chain().toggleOrderedList().run(), state.ordered)}
            {tool('Quote', <Quote className="h-4 w-4" />, () => chain().toggleBlockquote().run(), state.quote)}
            {tool(state.link ? 'Remove link' : 'Link', <LinkIcon className="h-4 w-4" />, () => {
                if (state.link) {
                    chain().unsetLink().run();
                    return;
                }
                const url = window.prompt('Link URL');
                if (url) chain().setLink({ href: url }).run();
            }, state.link)}
            <span className="mx-1 h-5 w-px bg-border" aria-hidden />
            {tool('Undo', <Undo2 className="h-4 w-4" />, () => chain().undo().run(), false, !state.canUndo)}
            {tool('Redo', <Redo2 className="h-4 w-4" />, () => chain().redo().run(), false, !state.canRedo)}
            <span className="mx-1 h-5 w-px bg-border" aria-hidden />
        </>
    );
}
