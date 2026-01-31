<template>
  <div class="rich-text-editor rounded-lg border border-gray-600 bg-gray-800 overflow-hidden">
    <!-- Toolbar -->
    <div
      v-if="editor"
      class="flex flex-wrap items-center gap-1 p-2 border-b border-gray-600 bg-gray-700/50"
    >
      <!-- Undo / Redo -->
      <button
        type="button"
        :class="[editor.can().undo() ? 'text-gray-300 hover:bg-gray-600' : 'text-gray-500 cursor-not-allowed']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.undo')"
        :disabled="!editor.can().undo()"
        @click="editor.chain().focus().undo().run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
        </svg>
      </button>
      <button
        type="button"
        :class="[editor.can().redo() ? 'text-gray-300 hover:bg-gray-600' : 'text-gray-500 cursor-not-allowed']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.redo')"
        :disabled="!editor.can().redo()"
        @click="editor.chain().focus().redo().run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" />
        </svg>
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Heading / Paragraph dropdown (Text Button + Heading Dropdown style) -->
      <div class="relative" ref="headingDropdownRef">
        <button
          type="button"
          class="rounded px-2 py-1.5 text-sm bg-gray-700 text-gray-200 border border-gray-600 hover:bg-gray-600 focus:ring-1 focus:ring-indigo-500 inline-flex items-center gap-1 min-w-[7rem]"
          :title="t('admin.documentation.rich_editor.format')"
          @click="headingDropdownOpen = !headingDropdownOpen"
        >
          <span>{{ headingDropdownLabel }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div
          v-show="headingDropdownOpen"
          class="absolute left-0 top-full mt-1 z-20 py-1 w-56 rounded-lg border border-gray-600 bg-gray-700 shadow-lg"
        >
          <button
            type="button"
            :class="[headingLevel === '0' ? 'bg-indigo-600 text-white' : 'text-gray-200 hover:bg-gray-600']"
            class="w-full text-left px-3 py-2 text-sm flex items-center"
            @click="setHeading('0'); headingDropdownOpen = false"
          >
            {{ t('admin.documentation.rich_editor.paragraph') }}
          </button>
          <button
            v-for="n in 6"
            :key="n"
            type="button"
            :class="[headingLevel === String(n) ? 'bg-indigo-600 text-white' : 'text-gray-200 hover:bg-gray-600']"
            class="w-full text-left px-3 py-2 text-sm font-semibold"
            @click="setHeading(String(n)); headingDropdownOpen = false"
          >
            {{ t('admin.documentation.rich_editor.heading') }} {{ n }}
          </button>
        </div>
      </div>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <button
        type="button"
        :class="[editor.isActive('bold') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.bold')"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <span class="font-bold text-sm">B</span>
      </button>
      <button
        type="button"
        :class="[editor.isActive('italic') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors italic text-sm"
        :title="t('admin.documentation.rich_editor.italic')"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        I
      </button>
      <button
        type="button"
        :class="[editor.isActive('underline') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors text-sm underline"
        :title="t('admin.documentation.rich_editor.underline')"
        @click="editor.chain().focus().toggleUnderline().run()"
      >
        U
      </button>

      <!-- Link -->
      <button
        type="button"
        :class="[editor.isActive('link') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.link')"
        @click="openLinkDialog"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
        </svg>
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Lists (unordered / ordered) -->
      <button
        type="button"
        :class="[editor.isActive('bulletList') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.bullet_list')"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
        </svg>
      </button>
      <button
        type="button"
        :class="[editor.isActive('orderedList') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.ordered_list')"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m-4 4h10M7 4h10M4 4h.01M4 8h.01M4 12h.01" />
        </svg>
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Text align -->
      <button
        type="button"
        :class="[editor.isActive({ textAlign: 'left' }) ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.align_left')"
        @click="editor.chain().focus().setTextAlign('left').run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
      </button>
      <button
        type="button"
        :class="[editor.isActive({ textAlign: 'center' }) ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.align_center')"
        @click="editor.chain().focus().setTextAlign('center').run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16" />
        </svg>
      </button>
      <button
        type="button"
        :class="[editor.isActive({ textAlign: 'right' }) ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.align_right')"
        @click="editor.chain().focus().setTextAlign('right').run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h17" />
        </svg>
      </button>
      <button
        type="button"
        :class="[editor.isActive({ textAlign: 'justify' }) ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors"
        :title="t('admin.documentation.rich_editor.align_justify')"
        @click="editor.chain().focus().setTextAlign('justify').run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Table -->
      <button
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors"
        :title="t('admin.documentation.rich_editor.insert_table')"
        @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16M4 6v12h16V6H4z" />
        </svg>
      </button>
      <button
        v-if="editor.isActive('table')"
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors text-xs"
        :title="t('admin.documentation.rich_editor.add_row')"
        @click="editor.chain().focus().addRowAfter().run()"
      >
        +Row
      </button>
      <button
        v-if="editor.isActive('table')"
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors text-xs"
        :title="t('admin.documentation.rich_editor.add_col')"
        @click="editor.chain().focus().addColumnAfter().run()"
      >
        +Col
      </button>
      <button
        v-if="editor.isActive('table')"
        type="button"
        class="rounded p-1.5 text-red-300 hover:bg-gray-600 transition-colors text-xs"
        :title="t('admin.documentation.rich_editor.delete_table')"
        @click="editor.chain().focus().deleteTable().run()"
      >
        Del table
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Inline code -->
      <button
        type="button"
        :class="[editor.isActive('code') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors font-mono text-xs"
        :title="t('admin.documentation.rich_editor.inline_code')"
        @click="editor.chain().focus().toggleCode().run()"
      >
        &lt;&gt;
      </button>
      <!-- Code block -->
      <button
        type="button"
        :class="[editor.isActive('codeBlock') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded p-1.5 transition-colors font-mono text-xs"
        :title="t('admin.documentation.rich_editor.code_block')"
        @click="editor.chain().focus().toggleCodeBlock().run()"
      >
        &lt;/&gt;
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Image -->
      <button
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors"
        :title="t('admin.documentation.rich_editor.image')"
        @click="openImageDialog"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </button>
      <button
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors"
        :title="t('admin.documentation.rich_editor.upload_image')"
        @click="triggerImageUpload"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
      </button>
      <input
        ref="imageFileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="onImageFileSelect"
      />

      <!-- YouTube -->
      <button
        type="button"
        class="rounded p-1.5 text-gray-300 hover:bg-gray-600 transition-colors"
        :title="t('admin.documentation.rich_editor.youtube')"
        @click="openYoutubeDialog"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
          <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
        </svg>
      </button>

      <span class="w-px h-5 bg-gray-600 mx-0.5" aria-hidden="true" />

      <!-- Toggle HTML / Visual -->
      <button
        type="button"
        :class="[htmlMode ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-600']"
        class="rounded px-2 py-1.5 text-sm font-mono transition-colors ml-auto"
        :title="t('admin.documentation.rich_editor.html_code')"
        @click="toggleHtmlMode"
      >
        HTML
      </button>
    </div>

    <!-- Visual editor -->
    <EditorContent v-show="!htmlMode" :editor="editor" class="prose-editor" />

    <!-- HTML code view -->
    <div v-show="htmlMode" class="p-4">
      <textarea
        v-model="htmlSource"
        class="block w-full min-h-[200px] px-4 py-3 rounded-lg border border-gray-600 bg-gray-900 text-gray-200 font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"
        :placeholder="t('admin.documentation.rich_editor.html_placeholder')"
        spellcheck="false"
        @input="onHtmlSourceInput"
      />
    </div>

    <!-- Image URL dialog (simple prompt for MVP; can be replaced with modal) -->
    <!-- YouTube URL dialog -->

    <!-- Hidden inputs for dialogs: we use prompt() for simplicity; can replace with modal components later -->
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Heading from '@tiptap/extension-heading';
import Underline from '@tiptap/extension-underline';
import Image from '@tiptap/extension-image';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import Youtube from '@tiptap/extension-youtube';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { createLowlight, common } from 'lowlight';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';

const { t } = useI18n();

const props = withDefaults(
  defineProps<{
    modelValue: string;
    placeholder?: string;
    disabled?: boolean;
  }>(),
  { placeholder: '', disabled: false }
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const imageFileInput = ref<HTMLInputElement | null>(null);
const headingDropdownRef = ref<HTMLElement | null>(null);
const headingDropdownOpen = ref(false);
const htmlMode = ref(false);
const htmlSource = ref('');

const lowlight = createLowlight(common);

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      codeBlock: false,
      heading: false,
    }),
    Heading.configure({ levels: [1, 2, 3, 4, 5, 6] }),
    Underline,
    Image.configure({
      inline: false,
      allowBase64: false,
    }),
    Table.configure({ resizable: true }),
    TableRow,
    TableHeader,
    TableCell,
    CodeBlockLowlight.configure({ lowlight }),
    Youtube.configure({
      width: 640,
      height: 360,
      addPasteHandler: true,
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
    }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
  ],
  editorProps: {
    attributes: {
      class:
        'prose-editor-inner min-h-[200px] px-4 py-3 text-gray-200 focus:outline-none max-w-none',
      'data-placeholder': props.placeholder,
    },
    handleDrop(view, event) {
      const files = event.dataTransfer?.files;
      if (files?.length && files[0].type.startsWith('image/')) {
        event.preventDefault();
        uploadImageFile(files[0]);
        return true;
      }
      return false;
    },
    handlePaste(view, event) {
      const items = event.clipboardData?.items;
      if (items) {
        for (const item of items) {
          if (item.kind === 'file' && item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
              event.preventDefault();
              uploadImageFile(file);
              return true;
            }
          }
        }
      }
      return false;
    },
  },
  onUpdate: ({ editor: e }) => {
    emit('update:modelValue', e.getHTML());
  },
});

const headingLevel = computed(() => {
  if (!editor.value) return '0';
  for (let i = 1; i <= 6; i++) {
    if (editor.value.isActive('heading', { level: i })) return String(i);
  }
  return '0';
});

const headingDropdownLabel = computed(() => {
  if (headingLevel.value === '0') return t('admin.documentation.rich_editor.paragraph');
  return `${t('admin.documentation.rich_editor.heading')} ${headingLevel.value}`;
});

function setHeading(value: string) {
  const level = parseInt(value, 10);
  if (!editor.value) return;
  if (level === 0) {
    editor.value.chain().focus().setParagraph().run();
  } else {
    editor.value.chain().focus().setHeading({ level: level as 1 | 2 | 3 | 4 | 5 | 6 }).run();
  }
}

function openLinkDialog() {
  const prevHref = editor.value?.getAttributes('link').href || '';
  const url = window.prompt(
    t('admin.documentation.rich_editor.link_url_prompt') || 'Enter URL:',
    prevHref
  );
  if (url === null) return;
  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    return;
  }
  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function openImageDialog() {
  const url = window.prompt(t('admin.documentation.rich_editor.image_url_prompt') || 'Enter image URL:');
  if (url) {
    editor.value?.chain().focus().setImage({ src: url }).run();
  }
}

function triggerImageUpload() {
  imageFileInput.value?.click();
}

async function onImageFileSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file || !editor.value) return;
  await uploadImageFile(file);
  input.value = '';
}

async function uploadImageFile(file: File) {
  try {
    const formData = new FormData();
    formData.append('image', file);
    const { data } = await api.post<{ data: { url: string } }>(
      '/admin/documentation/upload-image',
      formData,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
      }
    );
    const url = data?.data?.url ?? data?.url;
    if (url) {
      editor.value?.chain().focus().setImage({ src: url }).run();
    }
  } catch (err) {
    console.error('Image upload failed:', err);
    alert(t('admin.documentation.rich_editor.upload_error') || 'Upload failed');
  }
}

function openYoutubeDialog() {
  const url = window.prompt(
    t('admin.documentation.rich_editor.youtube_url_prompt') || 'Enter YouTube video URL:'
  );
  if (url) {
    editor.value?.chain().focus().setYoutubeVideo({ src: url }).run();
  }
}

/** Void elements that have no closing tag */
const VOID_TAGS = new Set(
  'area base br col embed hr img input link meta param source track wbr'.split(' ')
);

function formatHtml(html: string): string {
  if (!html || !html.trim()) return html;
  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const indent = (n: number) => '  '.repeat(n);

    function serialize(node: Node, depth: number): string {
      if (node.nodeType === Node.TEXT_NODE) {
        const text = node.textContent?.replace(/\s+/g, ' ').trim() ?? '';
        return text ? indent(depth) + text : '';
      }
      if (node.nodeType !== Node.ELEMENT_NODE) return '';
      const el = node as Element;
      const tag = el.tagName.toLowerCase();
      const attrs = Array.from(el.attributes)
        .map((a) =>
          a.value != null && a.value !== ''
            ? `${a.name}="${a.value.replace(/"/g, '&quot;')}"`
            : a.name
        )
        .join(' ');
      const attrStr = attrs ? ' ' + attrs : '';
      const children = Array.from(el.childNodes).filter(
        (n) => n.nodeType !== Node.TEXT_NODE || (n.textContent?.trim() ?? '').length > 0
      );
      if (VOID_TAGS.has(tag)) {
        return indent(depth) + `<${tag}${attrStr}>`;
      }
      if (children.length === 0) {
        return indent(depth) + `<${tag}${attrStr}></${tag}>`;
      }
      const inner = children
        .map((child) => serialize(child, depth + 1))
        .filter(Boolean)
        .join('\n');
      return indent(depth) + `<${tag}${attrStr}>\n${inner}\n` + indent(depth) + `</${tag}>`;
    }

    const body = doc.body;
    const parts = Array.from(body.childNodes)
      .map((node) => serialize(node, 0))
      .filter(Boolean);
    return parts.join('\n').trim();
  } catch {
    return html;
  }
}

function toggleHtmlMode() {
  if (htmlMode.value) {
    // Switching from HTML to visual: apply htmlSource to editor and emit
    const html = htmlSource.value || '';
    editor.value?.commands.setContent(html, false);
    emit('update:modelValue', html);
  } else {
    // Switching from visual to HTML: sync and format for readability
    const raw = editor.value?.getHTML() ?? props.modelValue ?? '';
    htmlSource.value = formatHtml(raw);
  }
  htmlMode.value = !htmlMode.value;
}

function onHtmlSourceInput() {
  emit('update:modelValue', htmlSource.value);
}

watch(
  () => props.modelValue,
  (val) => {
    if (!editor.value) return;
    const current = editor.value.getHTML();
    if (val !== current) {
      editor.value.commands.setContent(val || '', false);
    }
  },
  { immediate: false }
);

watch(
  () => props.disabled,
  (disabled) => {
    editor.value?.setEditable(!disabled);
  }
);

function onDocumentClick(e: MouseEvent) {
  const el = headingDropdownRef.value;
  if (el && !el.contains(e.target as Node)) {
    headingDropdownOpen.value = false;
  }
}

onMounted(() => {
  if (props.modelValue && editor.value) {
    const current = editor.value.getHTML();
    if (current === '<p></p>' && props.modelValue) {
      editor.value.commands.setContent(props.modelValue, false);
    }
  }
  document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  editor.value?.destroy();
});
</script>

<style scoped>
.rich-text-editor :deep(.ProseMirror) {
  min-height: 200px;
  outline: none;
  font-size: 1rem;
  line-height: 1.75;
}

.rich-text-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  color: #9ca3af;
  pointer-events: none;
  height: 0;
}

/* Headings – visible sizes in editor */
.rich-text-editor :deep(.ProseMirror h1) {
  font-size: 2.25rem;
  font-weight: 700;
  color: #fff;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
  line-height: 1.2;
}
.rich-text-editor :deep(.ProseMirror h2) {
  font-size: 1.875rem;
  font-weight: 700;
  color: #fff;
  margin-top: 0.75em;
  margin-bottom: 0.5em;
  line-height: 1.3;
}
.rich-text-editor :deep(.ProseMirror h3) {
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  margin-top: 0.75em;
  margin-bottom: 0.5em;
  line-height: 1.4;
}
.rich-text-editor :deep(.ProseMirror h4) {
  font-size: 1.25rem;
  font-weight: 700;
  color: #e5e7eb;
  margin-top: 0.75em;
  margin-bottom: 0.5em;
}
.rich-text-editor :deep(.ProseMirror h5) {
  font-size: 1.125rem;
  font-weight: 700;
  color: #e5e7eb;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}
.rich-text-editor :deep(.ProseMirror h6) {
  font-size: 1rem;
  font-weight: 700;
  color: #e5e7eb;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.rich-text-editor :deep(.ProseMirror p) {
  margin-bottom: 0.75em;
}

.rich-text-editor :deep(.ProseMirror strong) {
  font-weight: 700;
  color: #fff;
}

.rich-text-editor :deep(.ProseMirror em) {
  font-style: italic;
}

.rich-text-editor :deep(.ProseMirror u) {
  text-decoration: underline;
}

.rich-text-editor :deep(.ProseMirror a) {
  color: #818cf8;
  text-decoration: underline;
}

.rich-text-editor :deep(.ProseMirror ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin: 0.5em 0;
}

.rich-text-editor :deep(.ProseMirror ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin: 0.5em 0;
}

.rich-text-editor :deep(.ProseMirror li) {
  margin: 0.25em 0;
}

.rich-text-editor :deep(.ProseMirror blockquote) {
  border-left: 4px solid #4b5563;
  padding-left: 1rem;
  margin: 0.75em 0;
  color: #9ca3af;
}

.rich-text-editor :deep(.ProseMirror code) {
  background: #374151;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
  font-family: ui-monospace, monospace;
}

.rich-text-editor :deep(.ProseMirror table) {
  border-collapse: collapse;
  margin: 0.5em 0;
}

.rich-text-editor :deep(.ProseMirror th),
.rich-text-editor :deep(.ProseMirror td) {
  border: 1px solid #4b5563;
  padding: 0.5em 0.75em;
}

.rich-text-editor :deep(.ProseMirror th) {
  background: #374151;
}

.rich-text-editor :deep(.ProseMirror pre) {
  background: #1f2937;
  border-radius: 0.375rem;
  padding: 1rem;
  overflow-x: auto;
  margin: 0.5em 0;
}

.rich-text-editor :deep(.ProseMirror pre code) {
  background: transparent;
  padding: 0;
  font-size: 0.875rem;
}

.rich-text-editor :deep(.ProseMirror img) {
  max-width: 100%;
  height: auto;
}

.rich-text-editor :deep(.ProseMirror iframe) {
  max-width: 100%;
}

/* Text align in editor */
.rich-text-editor :deep(.ProseMirror [style*="text-align: left"]) {
  text-align: left;
}
.rich-text-editor :deep(.ProseMirror [style*="text-align: center"]) {
  text-align: center;
}
.rich-text-editor :deep(.ProseMirror [style*="text-align: right"]) {
  text-align: right;
}
.rich-text-editor :deep(.ProseMirror [style*="text-align: justify"]) {
  text-align: justify;
}
</style>
