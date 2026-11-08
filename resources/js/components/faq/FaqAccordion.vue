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

/** Toggle one answer while keeping other expanded answers open. */
const toggleItem = (itemId: string) => {
  const index = openItems.value.indexOf(itemId);
  if (index > -1) {
    openItems.value.splice(index, 1);
  } else {
    openItems.value.push(itemId);
  }
};

/** Report the selected local FAQ identifier to the parent feedback handler. */
const handleHelpful = (itemId: string) => {
  emit('helpful', itemId);
};

type InlineResult = { html: string; next: number; closed: boolean };

/**
 * Consume sanitized inline text until its matching emphasis delimiter.
 * Recognize only emphasis and exact local documentation links. At eight nested
 * levels, suppress further emphasis and label recursion while retaining links.
 * Unmatched emphasis delimiters retain their literal text.
 */
const parseInline = (source: string, start = 0, delimiter = '', depth = 0): InlineResult => {
  let html = '';
  let index = start;
  let failedLinkBoundary = -1;
  while (index < source.length) {
    if (delimiter && source.startsWith(delimiter, index)
      && (delimiter === '**' || !source.startsWith('**', index) || source.startsWith('***', index))) {
      return { html, next: index + delimiter.length, closed: true };
    }
    const tryLink = source[index] === '[' && index > failedLinkBoundary;
    const link = tryLink
      ? /^\[([^\]\r\n]+)\]\((\/documentation\/[a-z0-9]+(?:-[a-z0-9]+)*)\)/.exec(source.slice(index))
      : null;
    if (tryLink && !link) {
      // Openings before the same first boundary share the same failed destination.
      // Cache that boundary to avoid rescanning long unmatched '[' runs.
      const boundary = /[\]\r\n]/.exec(source.slice(index + 1));
      failedLinkBoundary = boundary ? index + 1 + boundary.index : source.length;
    }
    if (link) {
      const label = depth < 8 ? parseInline(link[1], 0, '', depth + 1).html : link[1];
      html += `<a href="${link[2]}">${label}</a>`;
      index += link[0].length;
      continue;
    }
    const opening = depth < 8
      ? source.startsWith('**', index) ? '**' : source[index] === '*' ? '*' : ''
      : '';
    if (opening) {
      const nested = parseInline(source, index + opening.length, opening, depth + 1);
      const tag = opening === '**' ? 'strong' : 'em';
      html += nested.closed ? `<${tag}>${nested.html}</${tag}>` : opening + nested.html;
      index = nested.next;
      continue;
    }
    html += source[index];
    index += 1;
  }
  return { html, next: index, closed: false };
};

/**
 * Preserve legacy paragraph/emphasis HTML without attributes, expand the bounded
 * Markdown subset, then sanitize every output with exact documentation URL rules.
 */
const formatAnswer = (answer: string) => {
  if (!answer) return '';
  const legacy = DOMPurify.sanitize(answer, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em'],
    ALLOWED_ATTR: [],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
  });
  const inline = parseInline(legacy).html;
  const paragraphs = inline.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
  return DOMPurify.sanitize(`<p>${paragraphs}</p>`, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a'],
    ALLOWED_ATTR: ['href'],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
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

