<script setup>
import { onMounted, ref } from 'vue'
import ThemeToggle from './ThemeToggle.vue'

defineProps({
  collection: { type: String, required: true },
  isViewer: { type: Boolean, default: false },
})

const logoUrl = '/assets/yearslib-logo.svg'
const isAdminAuthenticated = ref(false)

async function logout() {
  try {
    await fetch('/api/admin/logout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: '{}',
    })
  } finally {
    window.location.reload()
  }
}

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
        <button v-if="isAdminAuthenticated" class="header-logout" type="button" aria-label="Вийти" title="Вийти" @click="logout">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
        </button>
        <ThemeToggle />
      </div>
    </div>
  </header>
</template>
