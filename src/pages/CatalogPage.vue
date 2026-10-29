<script setup>
import { onMounted, ref } from 'vue'
import { useBookCatalog } from '../composables/useBookCatalog.js'
import BookCard from '../components/BookCard.vue'
import BookEditorModal from '../components/BookEditorModal.vue'
import CatalogSearch from '../components/CatalogSearch.vue'
import CatalogState from '../components/CatalogState.vue'
import GradeSelector from '../components/GradeSelector.vue'
import LibraryHeader from '../components/LibraryHeader.vue'

const {
  gradeOptions,
  selectedCollection,
  selectedGrade,
  search,
  loading,
  loadError,
  visibleBooks,
  selectGrade,
  loadBooks,
} = useBookCatalog()

const isAdmin = ref(false)
const editorOpen = ref(false)
const editingBook = ref(null)

function openCreate() {
  editingBook.value = null
  editorOpen.value = true
}

function openEdit(book) {
  editingBook.value = book
  editorOpen.value = true
}

async function bookSaved() {
  editorOpen.value = false
  editingBook.value = null
  await loadBooks(selectedCollection.value, selectedCollection.value === 'textbook' ? selectedGrade.value : null)
}

onMounted(async () => {
  try {
    const response = await fetch('/api/admin/session', { headers: { Accept: 'application/json' } })
    const payload = await response.json()
    isAdmin.value = response.ok && payload.authenticated === true
  } catch {}
})
</script>

<template>
  <main class="library-shell min-h-dvh">
    <LibraryHeader :collection="selectedCollection" />

    <section
      :id="selectedCollection === 'textbook' ? 'textbooks' : 'literature'"
      class="catalog-wrap"
      aria-labelledby="catalog-title"
    >
      <div class="catalog-heading">
        <div>
          <h1 id="catalog-title">{{ selectedCollection === 'textbook' ? 'Підручники' : 'Література' }}</h1>
          <p>{{ selectedCollection === 'textbook' ? 'Навчальні видання за класами.' : 'Книги для читання та знайомства з новими історіями.' }}</p>
        </div>
        <div class="catalog-heading-actions">
          <span v-if="selectedCollection === 'textbook' && selectedGrade" class="current-grade">{{ selectedGrade }} клас</span>
          <button v-if="isAdmin" class="admin-primary-button" type="button" @click="openCreate">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span>Додати книгу</span>
          </button>
        </div>
      </div>

      <section class="catalog-controls" :aria-label="`Фільтри: ${selectedCollection === 'textbook' ? 'підручники' : 'література'}`">
        <div v-if="selectedCollection === 'textbook' && gradeOptions.length > 0" id="class-filter" class="grade-row">
          <h2>Клас</h2>
          <GradeSelector :grades="gradeOptions" :selected-grade="selectedGrade" @select="selectGrade" />
        </div>

        <p v-if="selectedCollection === 'textbook' && !loading && !loadError && gradeOptions.length === 0" id="class-filter" class="no-grade-options">
          Класів із доданими підручниками поки немає.
        </p>

        <CatalogSearch
          v-if="selectedCollection === 'literature' || selectedGrade !== null"
          v-model="search"
          :count="visibleBooks.length"
          :loading="loading"
          :selected-grade="selectedCollection === 'textbook' ? selectedGrade : null"
          :collection="selectedCollection"
        />
      </section>

      <CatalogState v-if="selectedCollection === 'textbook' && selectedGrade === null" state="select" />
      <CatalogState v-else-if="loading" state="loading" />
      <CatalogState
        v-else-if="loadError"
        state="error"
        :message="loadError"
        @retry="loadBooks(selectedCollection, selectedCollection === 'textbook' ? selectedGrade : null)"
      />
      <CatalogState
        v-else-if="visibleBooks.length === 0"
        state="empty"
        :message="search ? 'Змініть пошуковий запит.' : `У розділі «${selectedCollection === 'textbook' ? 'Підручники' : 'Література'}» поки немає записів.`"
      />
      <section v-else class="book-grid" :aria-label="selectedCollection === 'textbook' ? `Підручники для ${selectedGrade} класу` : 'Література'">
        <BookCard v-for="book in visibleBooks" :key="book.id" :book="book" :can-manage="isAdmin" @edit="openEdit" />
      </section>
    </section>

    <BookEditorModal
      v-if="editorOpen"
      :book="editingBook"
      :default-category="selectedCollection"
      :default-grade="selectedGrade"
      @close="editorOpen = false"
      @saved="bookSaved"
    />
  </main>
</template>
