<script setup>
import { computed, onMounted, ref } from 'vue'
import { externalViewer } from '../utils/bookPresentation.js'
import LibraryHeader from '../components/LibraryHeader.vue'

const props = defineProps({
  bookId: { type: Number, required: true },
})

const book = ref(null)
const loading = ref(true)
const error = ref('')
const viewer = computed(() => externalViewer(book.value?.pdf_url))
const collection = readCollection()
const backHref = `/#${collection === 'literature' ? 'literature' : 'textbooks'}`

function readCollection() {
  try {
    return localStorage.getItem('library.collection') === 'literature' ? 'literature' : 'textbook'
  } catch {
    return 'textbook'
  }
}

onMounted(async () => {
  try {
    const response = await fetch(`/api/books/${props.bookId}`, {
      headers: { Accept: 'application/json' },
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.error || 'Не вдалося завантажити книгу.')
    book.value = payload.data
    const gradeLabel = book.value.grade ? `, ${book.value.grade} клас` : ''
    document.title = `${book.value.title}${gradeLabel} – Бібліотека Years`
  } catch (loadError) {
    error.value = loadError instanceof Error ? loadError.message : 'Не вдалося завантажити книгу.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="library-shell viewer-shell min-h-dvh">
    <LibraryHeader :collection="collection" :is-viewer="true" />

    <section class="viewer-wrap" aria-labelledby="viewer-title">
      <div v-if="loading" class="viewer-state" role="status">Завантаження книги…</div>
      <div v-else-if="error" class="viewer-state" role="alert">
        <h1 id="viewer-title">Не вдалося відкрити книгу</h1>
        <p>{{ error }}</p>
      </div>
      <div v-else-if="book" class="viewer-content">
        <div class="viewer-heading">
          <a class="viewer-back" :href="backHref" aria-label="Назад до каталогу" title="Назад до каталогу">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            <span>Назад</span>
          </a>
          <div>
            <p class="viewer-kicker">{{ book.category === 'textbook' ? 'ПІДРУЧНИК' : 'ЛІТЕРАТУРА' }}</p>
            <h1 id="viewer-title">{{ book.title }}<span v-if="book.year"> ({{ book.year }})</span></h1>
            <p class="viewer-author">
              {{ book.author || 'Автор не вказаний' }}<span v-if="book.grade"> · {{ book.grade }} клас</span>
            </p>
          </div>
          <a
            v-if="viewer?.externalUrl"
            class="viewer-external"
            :href="viewer.externalUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg>
            <span>Відкрити окремо</span>
          </a>
        </div>

        <div v-if="viewer?.type === 'google-drive' || viewer?.type === 'pdf'" class="viewer-frame-wrap">
          <iframe
            class="viewer-frame"
            :src="viewer.source"
            :title="`Перегляд: ${book.title}`"
            allow="fullscreen"
            referrerpolicy="no-referrer"
          ></iframe>
        </div>
        <div v-else class="viewer-state viewer-external-state">
          <h2>Для цього посилання немає вбудованого перегляду</h2>
          <p>Книгу можна відкрити на зовнішньому сайті.</p>
          <a :href="viewer?.externalUrl || book.pdf_url" target="_blank" rel="noopener noreferrer">
            Відкрити в новій вкладці
          </a>
        </div>
      </div>
    </section>
  </main>
</template>