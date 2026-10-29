<script setup>
import { onMounted, ref } from 'vue'
import ThemeToggle from '../components/ThemeToggle.vue'

const checking = ref(true)
const loginKey = ref('')
const loginError = ref('')

async function api(path, options = {}) {
  const response = await fetch(path, {
    credentials: 'same-origin',
    ...options,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.error || 'Не вдалося виконати запит.')
  return payload
}

async function login() {
  loginError.value = ''
  try {
    await api('/api/admin/login', { method: 'POST', body: JSON.stringify({ key: loginKey.value }) })
    window.location.href = '/'
  } catch (error) {
    loginError.value = error.message
  }
}

onMounted(async () => {
  try {
    const session = await api('/api/admin/session')
    if (session.authenticated === true) {
      window.location.replace('/')
      return
    }
  } catch {}
  checking.value = false
})
</script>

<template>
  <main class="library-shell admin-shell min-h-dvh">
    <header class="site-header"><div class="admin-header"><a class="library-brand" href="/"><img class="brand-logo" :src="'/assets/yearslib-logo.svg'" alt=""><span>Бібліотека Years</span></a><ThemeToggle /></div></header>
    <section v-if="checking" class="admin-login-state" role="status">Перевірка сесії…</section>
    <section v-else class="admin-login-wrap">
      <form class="admin-login-form" @submit.prevent="login">
        <p class="admin-eyebrow">БІБЛІОТЕКА YEARS</p><h1>Вхід адміністратора</h1>
        <label class="admin-field"><span>Ключ доступу</span><input v-model="loginKey" type="password" autocomplete="current-password" required autofocus></label>
        <p v-if="loginError" class="admin-error" role="alert">{{ loginError }}</p>
        <button class="admin-primary-button" type="submit">Увійти</button>
      </form>
    </section>
  </main>
</template>
