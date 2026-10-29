<script setup>
import { computed, ref } from 'vue'
import { coverPalette, externalPdfUrl, usesEmbeddedViewer } from '../utils/bookPresentation.js'

const props = defineProps({
  book: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
})

const coverFailed = ref(false)
const pdfUrl = externalPdfUrl(props.book.pdf_url)
const embeddedViewer = usesEmbeddedViewer(props.book.pdf_url)
const managesLoans = computed(() => props.canManage && props.book.category === 'literature')

function openLoanAccounting(event) {
  if (!managesLoans.value || event.target.closest('a')) return
  window.location.href = `/admin/literature/${props.book.id}`
}

function openLoanAccountingFromKeyboard(event) {
  if (!managesLoans.value) return
  event.preventDefault()
  window.location.href = `/admin/literature/${props.book.id}`
}
</script>

<template>
  <article
    class="book-card"
    :class="{ 'is-manageable': managesLoans }"
    :role="managesLoans ? 'link' : undefined"
    :tabindex="managesLoans ? 0 : undefined"
    :aria-label="managesLoans ? `Відкрити облік видачі книги «${book.title}»` : undefined"
    @click="openLoanAccounting"
    @keydown.enter="openLoanAccountingFromKeyboard"
    @keydown.space="openLoanAccountingFromKeyboard"
  >
    <a
      v-if="pdfUrl || managesLoans"
      class="book-cover-link"
      :href="managesLoans ? `/admin/literature/${book.id}` : (embeddedViewer ? `/book/${book.id}` : pdfUrl)"
      :target="!managesLoans && !embeddedViewer ? '_blank' : undefined"
      :rel="!managesLoans && !embeddedViewer ? 'noopener noreferrer' : undefined"
      :aria-label="managesLoans ? `Відкрити облік видачі книги «${book.title}»` : (embeddedViewer ? `Переглянути книгу «${book.title}»` : `Відкрити книгу «${book.title}» у новій вкладці`)"
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
      <span
        v-if="book.category === 'literature'"
        class="book-availability"
        :class="{ empty: Number(book.available_copies) === 0 }"
      >
        {{ Number(book.available_copies) === 0 ? 'Немає в наявності' : `Наявність: ${book.available_copies}/${book.total_copies}` }}
      </span>
    </div>
  </article>
</template>
