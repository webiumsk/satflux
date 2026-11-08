<template>
  <div class="space-y-4">
    <div
      v-for="item in items"
      :key="item.id"
      class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden transition-all hover:border-indigo-500/50"
    >
      <h3>
        <button
          :id="`${accordionId}-question-${item.id}`"
          type="button"
          :aria-expanded="openItems.includes(item.id)"
          :aria-controls="`${accordionId}-answer-${item.id}`"
          class="w-full px-6 py-4 text-left flex items-center justify-between focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset"
          @click="toggleItem(item.id)"
        >
          <span class="text-lg font-semibold text-white pr-4">
            {{ item.question }}
          </span>
          <svg
            aria-hidden="true"
            :class="[
              'w-5 h-5 text-gray-400 flex-shrink-0 transition-transform',
              openItems.includes(item.id) ? 'transform rotate-180' : ''
            ]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </button>
      </h3>
      
      <div
        :id="`${accordionId}-answer-${item.id}`"
        role="region"
        :aria-labelledby="`${accordionId}-question-${item.id}`"
        v-show="openItems.includes(item.id)"
        class="px-6 pb-4 border-t border-gray-700"
      >
        <div
          class="pt-4 prose prose-invert max-w-none text-gray-300"
          v-html="formatAnswer(item.answer)"
        ></div>
        
        <div class="mt-4 flex items-center justify-between">
          <div class="flex items-center gap-4 text-sm text-gray-400">
            <span v-if="(item.view_count ?? 0) > 0">
              {{ item.view_count }} {{ t('faq.views') }}
            </span>
            <span v-if="(item.helpful_count ?? 0) > 0">
              {{ item.helpful_count }} {{ t('faq.helpful') }}
            </span>
          </div>
          <button
            type="button"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300 transition-colors"
            @click.stop="handleHelpful(item.id)"
          >
            <svg
              class="w-4 h-4 mr-1"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"
              />
            </svg>
            {{ t('faq.was_helpful') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import DOMPurify from 'dompurify';

const { t } = useI18n();

const props = defineProps<{
  items: Array<{
    id: string;
    question: string;
    answer: string;
    view_count?: number;
    helpful_count?: number;
  }>;
  searchQuery?: string;
}>();

const emit = defineEmits<{
  helpful: [itemId: string];
}>();

const openItems = ref<string[]>([]);
const accordionId = useId();

const toggleItem = (itemId: string) => {
  const index = openItems.value.indexOf(itemId);
  if (index > -1) {
    openItems.value.splice(index, 1);
  } else {
    openItems.value.push(itemId);
  }
};

const handleHelpful = (itemId: string) => {
  emit('helpful', itemId);
};

/** Escape authored text before recognizing the small supported Markdown subset. */
const formatAnswer = (answer: string) => {
  if (!answer) return '';
  const entities: Record<string, string> = {
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  };
  const escaped = answer.replace(/[&<>"']/g, character => entities[character]);
  const inline = escaped.replace(
    /\[([^\]\n]+)\]\((\/documentation\/[a-z0-9]+(?:-[a-z0-9]+)*)\)|\*\*([^*\n]+)\*\*|\*([^*\n]+)\*/g,
    (_match, label: string | undefined, href: string | undefined, bold: string | undefined, emphasis: string | undefined) => {
      if (label !== undefined && href !== undefined) return `<a href="${href}">${label}</a>`;
      if (bold !== undefined) return `<strong>${bold}</strong>`;
      return `<em>${emphasis}</em>`;
    },
  );
  const paragraphs = inline.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
  return DOMPurify.sanitize(`<p>${paragraphs}</p>`, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a'],
    ALLOWED_ATTR: ['href'],
    ALLOWED_URI_REGEXP: /^\/documentation\/[a-z0-9]+(?:-[a-z0-9]+)*$/,
  });
};

// Auto-open items that match search query
if (props.searchQuery) {
  props.items.forEach(item => {
    if (
      item.question?.toLowerCase().includes(props.searchQuery!.toLowerCase()) ||
      item.answer?.toLowerCase().includes(props.searchQuery!.toLowerCase())
    ) {
      if (!openItems.value.includes(item.id)) {
        openItems.value.push(item.id);
      }
    }
  });
}
</script>

<style scoped>
.prose {
  color: #d1d5db;
}

.prose p {
  margin-bottom: 1em;
  line-height: 1.75;
}

.prose strong {
  color: #ffffff;
  font-weight: 600;
}

.prose em {
  font-style: italic;
}

.prose a {
  color: #818cf8;
  text-decoration: underline;
}

.prose a:hover {
  color: #a5b4fc;
}
</style>

