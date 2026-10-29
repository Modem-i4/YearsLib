<script setup>
import { computed, onMounted, ref } from 'vue'
import ThemeToggle from '../components/ThemeToggle.vue'
import { coverPalette } from '../utils/bookPresentation.js'

const authenticated = ref(false)
const checkingSession = ref(true)
const loginKey = ref('')
const loginError = ref('')
const activeCategory = ref('textbook')
const selectedGrade = ref('')
const search = ref('')
const books = ref([])
const grades = ref([])
const loading = ref(false)
const pageError = ref('')
const editorOpen = ref(false)
const saving = ref(false)
const editorError = ref('')
const coverBusy = ref(false)
const coverPreview = ref('')
const fileInput = ref(null)
const form = ref(emptyBook())
const isEditing = computed(() => form.value.id !== null)

function emptyBook(category = activeCategory.value) {
  return {
    id: null,
    title: '',
    year: '',
    category,
    author: '',
    grade: '',
    pdf_url: '',
    cover_path: '',
    total_copies: 1,
  }
}

const visibleBooks = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('uk')
  return books.value.filter((book) => {
    const matchesGrade = selectedGrade.value === '' || String(book.grade ?? '') === selectedGrade.value
    const matchesSearch = !query || [book.title, book.author].some((value) => value?.toLocaleLowerCase('uk').includes(query))
    return matchesGrade && matchesSearch
  })
})

async function api(path, options = {}) {
  const isFormData = options.body instanceof FormData
  const response = await fetch(path, {
    credentials: 'same-origin',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.error || 'Не вдалося виконати запит.')
  return payload
}

async function checkSession() {
  checkingSession.value = true
  try {
    const result = await api('/api/admin/session')
    authenticated.value = result.authenticated === true
    if (authenticated.value) await loadBooks()
  } catch {
    authenticated.value = false
  } finally {
    checkingSession.value = false
  }
}

async function login() {
  loginError.value = ''
  try {
    await api('/api/admin/login', {
      method: 'POST',
      body: JSON.stringify({ key: loginKey.value }),
    })
    loginKey.value = ''
    authenticated.value = true
    await loadBooks()
  } catch (error) {
    loginError.value = error.message
  }
}

async function logout() {
  try {
    await api('/api/admin/logout', { method: 'POST', body: '{}' })
  } finally {
    authenticated.value = false
    books.value = []
  }
}

async function loadBooks() {
  loading.value = true
  pageError.value = ''
  try {
    const params = new URLSearchParams({ category: activeCategory.value })
    if (activeCategory.value === 'textbook' && selectedGrade.value !== '') {
      params.set('grade', selectedGrade.value)
    }
    const result = await api(`/api/books?${params}`)
    books.value = result.data ?? []
    if (activeCategory.value === 'textbook') grades.value = result.grades ?? []
  } catch (error) {
    pageError.value = error.message
  } finally {
    loading.value = false
  }
}

function setCategory(category) {
  activeCategory.value = category
  selectedGrade.value = ''
  search.value = ''
  loadBooks()
}

function openCreate() {
  form.value = emptyBook()
  coverPreview.value = ''
  editorError.value = ''
  editorOpen.value = true
}

function openEdit(book) {
  form.value = {
    id: Number(book.id),
    title: book.title ?? '',
    year: book.year ?? '',
    category: book.category,
    author: book.author ?? '',
    grade: book.grade ?? '',
    pdf_url: book.pdf_url ?? '',
    cover_path: book.cover_path ?? '',
    total_copies: Number(book.total_copies ?? 1),
  }
  coverPreview.value = book.cover_path ?? ''
  editorError.value = ''
  editorOpen.value = true
}

function closeEditor() {
  if (saving.value || coverBusy.value) return
  editorOpen.value = false
  if (coverPreview.value.startsWith('blob:')) URL.revokeObjectURL(coverPreview.value)
  coverPreview.value = ''
}

async function convertToWebp(file) {
  if (file.size > 15 * 1024 * 1024) throw new Error('Вихідне зображення має бути меншим за 15 МБ.')
  const image = await createImageBitmap(file)
  const scale = Math.min(1, 1800 / Math.max(image.width, image.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(image.width * scale))
  canvas.height = Math.max(1, Math.round(image.height * scale))
  const context = canvas.getContext('2d')
  if (!context) throw new Error('Браузер не зміг обробити зображення.')
  context.drawImage(image, 0, 0, canvas.width, canvas.height)
  image.close()

  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Браузер не зміг конвертувати зображення.'))
        return
      }
      resolve(new File([blob], 'cover.webp', { type: 'image/webp' }))
    }, 'image/webp', 0.86)
  })
}

async function uploadCover(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  coverBusy.value = true
  editorError.value = ''
  try {
    const webp = await convertToWebp(file)
    if (webp.size > 5 * 1024 * 1024) throw new Error('Після конвертації обкладинка має бути меншою за 5 МБ.')
    const body = new FormData()
    body.append('cover', webp)
    const result = await api('/api/admin/covers', { method: 'POST', body })
    if (coverPreview.value.startsWith('blob:')) URL.revokeObjectURL(coverPreview.value)
    form.value.cover_path = result.cover_path
    coverPreview.value = URL.createObjectURL(webp)
  } catch (error) {
    editorError.value = error.message || 'Не вдалося завантажити обкладинку.'
  } finally {
    coverBusy.value = false
  }
}

async function saveBook() {
  saving.value = true
  editorError.value = ''
  const payload = {
    title: form.value.title.trim(),
    year: form.value.year === '' ? null : Number(form.value.year),
    category: form.value.category,
    author: form.value.author.trim() || null,
    grade: form.value.category === 'textbook' && form.value.grade !== '' ? Number(form.value.grade) : null,
    pdf_url: form.value.pdf_url.trim() || null,
    cover_path: form.value.cover_path || null,
    total_copies: form.value.category === 'literature' ? Number(form.value.total_copies) : 1,
  }

  try {
    await api(form.value.id ? `/api/books/${form.value.id}` : '/api/books', {
      method: form.value.id ? 'PUT' : 'POST',
      body: JSON.stringify(payload),
    })
    editorOpen.value = false
    await loadBooks()
  } catch (error) {
    editorError.value = error.message
  } finally {
    saving.value = false
  }
}

onMounted(checkSession)
</script>

<template>
  <main class="library-shell admin-shell min-h-dvh">
    <header class="site-header">
      <div class="admin-header">
        <a class="library-brand" href="/" aria-label="Бібліотека Years, до каталогу">
          <img class="brand-logo" :src="'/assets/yearslib-logo.svg'" alt="">
          <span>Бібліотека Years</span>
        </a>
        <div class="admin-header-actions">
          <a class="admin-catalog-link" href="/#textbooks">До каталогу</a>
          <ThemeToggle />
          <button v-if="authenticated" class="admin-logout" type="button" aria-label="Вийти" title="Вийти" @click="logout">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
          </button>
        </div>
      </div>
    </header>

    <section v-if="checkingSession" class="admin-login-state" role="status">Перевірка сесії…</section>
    <section v-else-if="!authenticated" class="admin-login-wrap">
      <form class="admin-login-form" @submit.prevent="login">
        <p class="admin-eyebrow">БІБЛІОТЕКА YEARS</p>
        <h1>Вхід адміністратора</h1>
        <label class="admin-field">
          <span>Ключ доступу</span>
          <input v-model="loginKey" type="password" autocomplete="current-password" required autofocus>
        </label>
        <p v-if="loginError" class="admin-error" role="alert">{{ loginError }}</p>
        <button class="admin-primary-button" type="submit">Увійти</button>
      </form>
    </section>

    <section v-else class="admin-content">
      <div class="admin-title-row">
        <div>
          <p class="admin-eyebrow">КЕРУВАННЯ КАТАЛОГОМ</p>
          <h1>Книги</h1>
        </div>
        <button class="admin-primary-button add-book-button" type="button" aria-label="Додати книгу" title="Додати книгу" @click="openCreate">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          <span>Додати книгу</span>
        </button>
      </div>

      <div class="admin-toolbar">
        <div class="admin-segmented" role="group" aria-label="Категорія книг">
          <button type="button" :class="{ active: activeCategory === 'textbook' }" @click="setCategory('textbook')">Підручники</button>
          <button type="button" :class="{ active: activeCategory === 'literature' }" @click="setCategory('literature')">Література</button>
        </div>
        <select v-if="activeCategory === 'textbook'" v-model="selectedGrade" aria-label="Фільтр за класом" @change="loadBooks">
          <option value="">Усі класи</option>
          <option v-for="grade in grades" :key="grade" :value="String(grade)">{{ grade }} клас</option>
        </select>
        <label class="admin-search">
          <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 4 4"/></svg>
          <input v-model="search" type="search" placeholder="Пошук за назвою чи автором" aria-label="Пошук книг">
        </label>
      </div>

      <p v-if="pageError" class="admin-error" role="alert">{{ pageError }}</p>
      <div v-if="loading" class="admin-table-state" role="status">Завантаження…</div>
      <div v-else-if="visibleBooks.length === 0" class="admin-table-state">Книг не знайдено.</div>
      <div v-else class="admin-book-list">
        <article v-for="book in visibleBooks" :key="book.id" class="admin-book-row">
          <div class="admin-cover-thumb" :class="`cover-${coverPalette(book)}`">
            <img v-if="book.cover_path" :src="book.cover_path" :alt="`Обкладинка: ${book.title}`" loading="lazy">
            <span v-else>{{ book.title.slice(0, 1) }}</span>
          </div>
          <div class="admin-book-info">
            <h2>
              <a v-if="book.category === 'literature'" :href="`/admin/literature/${book.id}`">{{ book.title }}<span v-if="book.year"> ({{ book.year }})</span></a>
              <template v-else>{{ book.title }}<span v-if="book.year"> ({{ book.year }})</span></template>
            </h2>
            <p>{{ book.author || 'Автор не вказаний' }}</p>
          </div>
          <div class="admin-book-tags">
            <span v-if="book.category === 'literature'">Наявність {{ book.available_copies }}/{{ book.total_copies }}</span>
            <span v-if="book.category === 'literature'">Видано {{ book.active_loans_count }}</span>
            <span v-if="book.category === 'textbook'">{{ book.grade ?? '—' }} клас</span>
            <span>{{ book.pdf_url ? 'PDF' : 'Без PDF' }}</span>
          </div>
          <button class="admin-icon-button" type="button" :aria-label="`Редагувати ${book.title}`" title="Редагувати" @click="openEdit(book)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.3-.9L19 8.4a2.1 2.1 0 0 0-3-3L5.3 16.1 4 20Z"/></svg>
          </button>
        </article>
      </div>
    </section>

    <div v-if="editorOpen" class="admin-modal-backdrop" @click.self="closeEditor">
      <section class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="book-editor-title">
        <header class="admin-modal-header">
          <div>
            <p class="admin-eyebrow">{{ isEditing ? 'РЕДАГУВАННЯ' : 'НОВИЙ ЗАПИС' }}</p>
            <h2 id="book-editor-title">{{ isEditing ? 'Редагувати книгу' : 'Додати книгу' }}</h2>
          </div>
          <button class="admin-icon-button" type="button" aria-label="Закрити" title="Закрити" @click="closeEditor">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
          </button>
        </header>

        <form class="admin-book-form" @submit.prevent="saveBook">
          <label class="admin-field">
            <span>Назва <b>*</b></span>
            <input v-model="form.title" maxlength="190" required>
          </label>
          <div class="admin-form-grid">
            <label class="admin-field">
              <span>Розділ <b>*</b></span>
              <select v-model="form.category" required>
                <option value="textbook">Підручники</option>
                <option value="literature">Література</option>
              </select>
            </label>
            <label class="admin-field">
              <span>Рік видання</span>
              <input v-model="form.year" type="number" min="1000" max="9999" inputmode="numeric">
            </label>
          </div>
          <div class="admin-form-grid">
            <label class="admin-field">
              <span>Автор</span>
              <input v-model="form.author" maxlength="190">
            </label>
            <label v-if="form.category === 'textbook'" class="admin-field">
              <span>Клас</span>
              <select v-model="form.grade">
                <option value="">Не вказано</option>
                <option v-for="grade in Array.from({ length: 11 }, (_, index) => index + 1)" :key="grade" :value="String(grade)">{{ grade }} клас</option>
              </select>
            </label>
            <label v-else class="admin-field">
              <span>Кількість примірників</span>
              <input v-model="form.total_copies" type="number" min="0" max="65535" inputmode="numeric" required>
            </label>
          </div>
          <label class="admin-field">
            <span>Зовнішнє посилання на PDF</span>
            <input v-model="form.pdf_url" type="url" placeholder="https://…">
          </label>
          <div class="admin-field">
            <span>Обкладинка</span>
            <div class="admin-cover-field">
              <div class="admin-cover-preview" :class="`cover-${coverPalette(form)}`">
                <img v-if="coverPreview || form.cover_path" :src="coverPreview || form.cover_path" alt="Попередній перегляд обкладинки">
                <span v-else>{{ form.title.slice(0, 1) || 'К' }}</span>
              </div>
              <div class="admin-cover-controls">
                <input ref="fileInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp,image/avif" @change="uploadCover">
                <button class="admin-secondary-button" type="button" :disabled="coverBusy" @click="fileInput?.click()">
                  {{ coverBusy ? 'Конвертація…' : 'Завантажити зображення' }}
                </button>
              </div>
            </div>
          </div>
          <p v-if="editorError" class="admin-error" role="alert">{{ editorError }}</p>
          <footer class="admin-form-actions">
            <button class="admin-secondary-button" type="button" :disabled="saving || coverBusy" @click="closeEditor">Скасувати</button>
            <button class="admin-primary-button" type="submit" :disabled="saving || coverBusy">
              {{ saving ? 'Збереження…' : (isEditing ? 'Зберегти зміни' : 'Додати книгу') }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </main>
</template>
