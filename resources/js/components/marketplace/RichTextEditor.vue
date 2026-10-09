<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Heading2, Italic, List, ListOrdered, Quote } from '@lucide/vue';
import { watch } from 'vue';

const model = defineModel<string>({ default: '' });

defineProps<{
    labelledby?: string;
}>();

const editor = useEditor({
    content: model.value,
    extensions: [StarterKit.configure({ heading: { levels: [2, 3] } })],
    editorProps: {
        attributes: {
            class: 'prose-listing min-h-40 px-3 py-2 text-sm outline-none',
        },
    },
    onUpdate: ({ editor }) => {
        model.value = editor.isEmpty ? '' : editor.getHTML();
    },
});

// Keep the editor in step when the form is reset from outside.
watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML() && value === '') {
        editor.value.commands.clearContent();
    }
});
</script>

<template>
    <div
        class="rounded-md border bg-background focus-within:ring-[3px] focus-within:ring-ring/50"
    >
        <div
            v-if="editor"
            class="flex flex-wrap gap-1 border-b p-1"
            role="toolbar"
            aria-label="Định dạng"
        >
            <button
                v-for="tool in [
                    {
                        icon: Bold,
                        label: 'Đậm',
                        active: 'bold',
                        run: () => editor!.chain().focus().toggleBold().run(),
                    },
                    {
                        icon: Italic,
                        label: 'Nghiêng',
                        active: 'italic',
                        run: () => editor!.chain().focus().toggleItalic().run(),
                    },
                    {
                        icon: Heading2,
                        label: 'Tiêu đề',
                        active: 'heading',
                        run: () =>
                            editor!
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run(),
                    },
                    {
                        icon: List,
                        label: 'Danh sách',
                        active: 'bulletList',
                        run: () =>
                            editor!.chain().focus().toggleBulletList().run(),
                    },
                    {
                        icon: ListOrdered,
                        label: 'Danh sách số',
                        active: 'orderedList',
                        run: () =>
                            editor!.chain().focus().toggleOrderedList().run(),
                    },
                    {
                        icon: Quote,
                        label: 'Trích dẫn',
                        active: 'blockquote',
                        run: () =>
                            editor!.chain().focus().toggleBlockquote().run(),
                    },
                ]"
                :key="tool.label"
                type="button"
                class="rounded p-1.5 hover:bg-accent"
                :class="{ 'bg-accent': editor.isActive(tool.active) }"
                :aria-label="tool.label"
                :aria-pressed="editor.isActive(tool.active)"
                @click="tool.run"
            >
                <component :is="tool.icon" class="size-4" />
            </button>
        </div>
        <EditorContent :editor="editor" :aria-labelledby="labelledby" />
    </div>
</template>
