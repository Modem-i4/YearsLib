<script setup>
import ThemeToggle from './ThemeToggle.vue'

defineProps({
  collection: { type: String, required: true },
  isViewer: { type: Boolean, default: false },
})

const logoUrl = '/assets/yearslib-logo.svg'
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
      <ThemeToggle />
    </div>
  </header>
</template>