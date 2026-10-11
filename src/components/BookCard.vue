<script setup>
import { ref } from 'vue'
import { coverPalette, externalPdfUrl } from '../utils/bookPresentation.js'

const props = defineProps({
  book: { type: Object, required: true },
})

const coverFailed = ref(false)
const pdfUrl = externalPdfUrl(props.book.pdf_url)
</script>

<template>
  <article class="book-card">
    <a
      v-if="pdfUrl"
      class="book-cover-link"
      :href="pdfUrl"
      target="_blank"
      rel="noopener noreferrer"
      :aria-label="`Відкрити книгу «${book.title}» у новій вкладці`"
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
      <span class="cover-unavailable-label">PDF не додано</span>
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
    </div>
  </article>
</template>