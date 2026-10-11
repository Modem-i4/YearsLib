<script setup>
import { computed, ref } from 'vue'

const storageKey = 'library.theme'
const root = document.documentElement
const theme = ref(root.dataset.theme === 'dark' ? 'dark' : 'light')
const isDark = computed(() => theme.value === 'dark')

function toggleTheme(event) {
  const nextTheme = isDark.value ? 'light' : 'dark'
  const button = event.currentTarget
  const bounds = button.getBoundingClientRect()
  root.style.setProperty('--theme-origin-x', `${bounds.left + bounds.width / 2}px`)
  root.style.setProperty('--theme-origin-y', `${bounds.top + bounds.height / 2}px`)

  const updateTheme = () => {
    theme.value = nextTheme
    root.dataset.theme = nextTheme
    document.querySelector('meta[name="theme-color"]')?.setAttribute(
      'content',
      nextTheme === 'dark' ? '#020113' : '#f7f9fd',
    )
    try {
      localStorage.setItem(storageKey, nextTheme)
    } catch {}
  }

  if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.startViewTransition(updateTheme)
  } else {
    updateTheme()
  }
}
</script>

<template>
  <button
    class="theme-toggle"
    type="button"
    :aria-label="isDark ? 'Увімкнути світлу тему' : 'Увімкнути темну тему'"
    :aria-pressed="isDark"
    :title="isDark ? 'Світла тема' : 'Темна тема'"
    @click="toggleTheme"
  >
    <svg v-if="!isDark" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="3.8" />
      <path d="M12 2.5v2M12 19.5v2M4.8 4.8l1.4 1.4m11.6 11.6 1.4 1.4M2.5 12h2m15 0h2M4.8 19.2l1.4-1.4M17.8 6.2l1.4-1.4" />
    </svg>
    <svg v-else viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z" />
      <path d="m16.8 4.2.5 1.5 1.5.5-1.5.5-.5 1.5-.5-1.5-1.5-.5 1.5-.5.5-1.5Z" />
    </svg>
  </button>
</template>