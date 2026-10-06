import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Bold, Italic, List, ListOrdered, Image as ImageIcon, Link as LinkIcon } from 'lucide-react';
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

    if (!editor) return null;

    return (
        <div className="rounded-md border">
            <div className="flex flex-wrap gap-1 border-b p-1">
                <Button type="button" variant="ghost" size="icon" onClick={() => editor.chain().focus().toggleBold().run()}>
                    <Bold className="h-4 w-4" />
                </Button>
                <Button type="button" variant="ghost" size="icon" onClick={() => editor.chain().focus().toggleItalic().run()}>
                    <Italic className="h-4 w-4" />
                </Button>
                <Button type="button" variant="ghost" size="icon" onClick={() => editor.chain().focus().toggleBulletList().run()}>
                    <List className="h-4 w-4" />
                </Button>
                <Button type="button" variant="ghost" size="icon" onClick={() => editor.chain().focus().toggleOrderedList().run()}>
                    <ListOrdered className="h-4 w-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                        const url = window.prompt('Link URL');
                        if (url) editor.chain().focus().setLink({ href: url }).run();
                    }}
                >
                    <LinkIcon className="h-4 w-4" />
                </Button>
                <AssetPicker
                    imageOnly
                    trigger={
                        <Button type="button" variant="ghost" size="icon" title="Insert from library">
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
