<script setup>
import { computed } from 'vue'
import CatalogPage from './pages/CatalogPage.vue'
import BookViewerPage from './pages/BookViewerPage.vue'

const bookId = computed(() => {
  const match = window.location.pathname.match(/^\/book\/([1-9][0-9]*)$/)
  return match ? Number(match[1]) : null
})
</script>

<template>
  <BookViewerPage v-if="bookId !== null" :book-id="bookId" />
  <CatalogPage v-else />
</template>