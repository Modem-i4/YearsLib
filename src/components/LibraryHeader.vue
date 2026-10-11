<script setup>
import { onMounted, ref } from 'vue'
import ThemeToggle from './ThemeToggle.vue'

defineProps({
  collection: { type: String, required: true },
  isViewer: { type: Boolean, default: false },
})

const logoUrl = '/assets/yearslib-logo.svg'
const isAdminAuthenticated = ref(false)

onMounted(async () => {
  try {
    const response = await fetch('/api/admin/session', {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) return

    const payload = await response.json()
    isAdminAuthenticated.value = payload.authenticated === true
  } catch {
    // The public navigation remains usable if the session check is unavailable.
  }
})
</script>

<template>
  <header class="site-header">
    <div class="library-header">
      <a class="library-brand" href="/" aria-label="Бібліотека Years, на головну">
        <img class="brand-logo" :src="logoUrl" alt="">
        <span>Бібліотека Years</span>
      </a>
      <nav class="primary-nav" aria-label="Основна навігація">
        <a
          class="nav-link"
          :class="{ 'is-current': collection === 'textbook' }"
          :href="isViewer ? '/#textbooks' : '#textbooks'"
        >Підручники</a>
        <a
          class="nav-link"
          :class="{ 'is-current': collection === 'literature' }"
          :href="isViewer ? '/#literature' : '#literature'"
        >Література</a>
      </nav>
      <div class="library-header-actions">
        <a v-if="isAdminAuthenticated" class="admin-catalog-link" href="/admin">До кабінету</a>
        <ThemeToggle />
      </div>
    </div>
  </header>
</template>
