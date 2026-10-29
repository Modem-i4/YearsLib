<script setup>
import { ref } from 'vue'
import { coverPalette, externalPdfUrl, usesEmbeddedViewer } from '../utils/bookPresentation.js'

const props = defineProps({
  book: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
})
const emit = defineEmits(['edit'])

const coverFailed = ref(false)
const pdfUrl = externalPdfUrl(props.book.pdf_url)
const embeddedViewer = usesEmbeddedViewer(props.book.pdf_url)
</script>

<template>
  <article
    class="book-card"
  >
    <button
      v-if="canManage"
      class="book-admin-edit"
      type="button"
      :aria-label="`Редагувати ${book.title}`"
      title="Редагувати"
      @click.stop="emit('edit', book)"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path class="pencil-shape" d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z" />
      </svg>
    </button>
    <a
      v-if="canManage"
      class="book-admin-loan"
      :href="`/admin/books/${book.id}`"
      :aria-label="`Видати ${book.title}`"
      title="Видати"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V7a2 2 0 0 1 4 0v3M11 10V5a2 2 0 0 1 4 0v5M15 10V7a2 2 0 0 1 4 0v7c0 4-2.7 7-7 7h-1.2a6 6 0 0 1-5.3-3.2L3.2 13.5a2 2 0 0 1 3.5-1.9L8 14"/></svg>
    </a>
    <a
      v-if="pdfUrl"
      class="book-cover-link"
      :href="embeddedViewer ? `/book/${book.id}` : pdfUrl"
      :target="!embeddedViewer ? '_blank' : undefined"
      :rel="!embeddedViewer ? 'noopener noreferrer' : undefined"
      :aria-label="embeddedViewer ? `Переглянути книгу «${book.title}»` : `Відкрити книгу «${book.title}» у новій вкладці`"
    >
      <div class="book-cover" :class="`cover-${coverPalette(book)}`">
        <img
          v-if="book.cover_path && !coverFailed"
          class="cover-image"
          :src="book.cover_path"
          :alt="`Обкладинка: ${book.title}`"
          loading="lazy"
          @error="coverFailed = true"
        >
        <div v-else class="cover-fallback" aria-hidden="true">
          <span class="cover-series">{{ book.grade ? `${book.grade} КЛАС` : 'КНИГА' }}</span>
          <strong>{{ book.title }}</strong>
          <span class="cover-author">{{ book.author || 'Автор не вказаний' }}</span>
        </div>
      </div>
    </a>
    <div v-else class="book-cover book-cover-unavailable" :class="`cover-${coverPalette(book)}`">
      <img
        v-if="book.cover_path && !coverFailed"
        class="cover-image"
        :src="book.cover_path"
        :alt="`Обкладинка: ${book.title}`"
        loading="lazy"
        @error="coverFailed = true"
      >
      <div v-else class="cover-fallback" aria-hidden="true">
        <span class="cover-series">{{ book.grade ? `${book.grade} КЛАС` : 'КНИГА' }}</span>
        <strong>{{ book.title }}</strong>
        <span class="cover-author">{{ book.author || 'Автор не вказаний' }}</span>
      </div>
      <span v-if="book.category === 'textbook'" class="cover-unavailable-label">PDF не додано</span>
    </div>

    <div class="book-meta">
      <h3>{{ book.title }}<span v-if="book.year"> ({{ book.year }})</span></h3>
      <div class="book-meta-row">
        <p>{{ book.author || 'Автор не вказаний' }}</p>
        <span v-if="book.grade" class="book-grade">{{ book.grade }} клас</span>
      </div>
      <span class="book-format" :class="{ 'has-pdf': pdfUrl }">
        {{ pdfUrl ? 'PDF' : 'Книга' }}
      </span>
      <span
        v-if="book.category === 'literature' || canManage"
        class="book-availability"
        :class="{ empty: Number(book.available_copies) === 0 }"
      >
        {{ Number(book.available_copies) === 0 ? 'Немає в наявності' : `Наявність: ${book.available_copies}/${book.total_copies}` }}
      </span>
    </div>
  </article>
</template>
