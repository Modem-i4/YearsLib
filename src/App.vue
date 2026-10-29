<script setup>
import { computed } from 'vue'
import AdminLoginPage from './pages/AdminLoginPage.vue'
import CatalogPage from './pages/CatalogPage.vue'
import BookViewerPage from './pages/BookViewerPage.vue'
import AdminLiteratureBookPage from './pages/AdminLiteratureBookPage.vue'

const isAdmin = window.location.pathname === '/admin'
const adminBookId = computed(() => {
  const match = window.location.pathname.match(/^\/admin\/(?:books|literature)\/([1-9][0-9]*)$/)
  return match ? Number(match[1]) : null
})
const bookId = computed(() => {
  const match = window.location.pathname.match(/^\/book\/([1-9][0-9]*)$/)
  return match ? Number(match[1]) : null
})
</script>

<template>
  <AdminLiteratureBookPage v-if="adminBookId !== null" :book-id="adminBookId" />
  <AdminLoginPage v-else-if="isAdmin" />
  <BookViewerPage v-else-if="bookId !== null" :book-id="bookId" />
  <CatalogPage v-else />
</template>
