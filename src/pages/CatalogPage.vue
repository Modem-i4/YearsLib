<script setup>
import { useBookCatalog } from '../composables/useBookCatalog.js'
import BookCard from '../components/BookCard.vue'
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
        <span v-if="selectedCollection === 'textbook' && selectedGrade" class="current-grade">{{ selectedGrade }} клас</span>
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
        <BookCard v-for="book in visibleBooks" :key="book.id" :book="book" />
      </section>
    </section>
  </main>
</template>