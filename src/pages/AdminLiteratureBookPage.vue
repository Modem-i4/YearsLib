<script setup>
import { computed, onMounted, ref } from 'vue'
import ThemeToggle from '../components/ThemeToggle.vue'

const props = defineProps({ bookId: { type: Number, required: true } })
const authenticated = ref(false)
const checking = ref(true)
const loading = ref(false)
const error = ref('')
const book = ref(null)
const loans = ref([])
const people = ref([])
const filter = ref('active')
const editorOpen = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = ref(emptyLoan())
const borrowerSuggestionsOpen = ref(false)

function localDate() {
  const now = new Date()
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

function emptyLoan() {
  const issued = localDate()
  return { borrower_name: '', issued_at: issued }
}

const visibleLoans = computed(() => filter.value === 'active'
  ? loans.value.filter((loan) => loan.returned_at === null)
  : loans.value)

const borrowerSuggestions = computed(() => {
  const query = form.value.borrower_name.trim().toLocaleLowerCase('uk-UA')
  return people.value
    .filter((person) => !query || person.toLocaleLowerCase('uk-UA').includes(query))
    .slice(0, 8)
})

function selectBorrower(person) {
  form.value.borrower_name = person
  borrowerSuggestionsOpen.value = false
}

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

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await api(`/api/admin/books/${props.bookId}/loans`)
    book.value = result.book
    loans.value = result.data ?? []
    people.value = result.people ?? []
  } catch (caught) {
    error.value = caught.message
  } finally {
    loading.value = false
  }
}

async function initialize() {
  try {
    const session = await api('/api/admin/session')
    authenticated.value = session.authenticated === true
    if (authenticated.value) await load()
  } catch {
    authenticated.value = false
  } finally {
    checking.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = emptyLoan()
  error.value = ''
  editorOpen.value = true
}

function openEdit(loan) {
  editingId.value = Number(loan.id)
  form.value = { borrower_name: loan.borrower_name, issued_at: loan.issued_at }
  error.value = ''
  editorOpen.value = true
}

async function saveLoan() {
  saving.value = true
  error.value = ''
  try {
    await api(editingId.value ? `/api/admin/loans/${editingId.value}` : `/api/admin/books/${props.bookId}/loans`, {
      method: editingId.value ? 'PUT' : 'POST',
      body: JSON.stringify(form.value),
    })
    editorOpen.value = false
    await load()
  } catch (caught) {
    error.value = caught.message
  } finally {
    saving.value = false
  }
}

async function returnBook(loan) {
  error.value = ''
  try {
    await api(`/api/admin/loans/${loan.id}/return`, { method: 'POST', body: JSON.stringify({ returned_at: localDate() }) })
    await load()
  } catch (caught) {
    error.value = caught.message
  }
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('uk-UA').format(new Date(`${value}T12:00:00`))
}

function statusLabel(status) {
  return { active: 'Видано', returned: 'Повернено' }[status] ?? status
}

onMounted(initialize)
</script>

<template>
  <main class="library-shell admin-shell min-h-dvh">
    <header class="site-header">
      <div class="admin-header">
        <a class="library-brand" href="/admin"><img class="brand-logo" :src="'/assets/yearslib-logo.svg'" alt=""><span>Бібліотека Years</span></a>
        <div class="admin-header-actions"><a class="admin-catalog-link" href="/admin">До списку книг</a><ThemeToggle /></div>
      </div>
    </header>

    <section v-if="checking" class="admin-login-state" role="status">Перевірка сесії…</section>
    <section v-else-if="!authenticated" class="admin-login-state">
      <div><p>Для перегляду видач потрібен вхід адміністратора.</p><a class="admin-primary-button" href="/admin">Увійти</a></div>
    </section>
    <section v-else class="admin-content loan-content">
      <p v-if="error && !book" class="admin-error" role="alert">{{ error }}</p>
      <div v-if="loading && !book" class="admin-table-state">Завантаження…</div>
      <template v-else-if="book">
        <div class="loan-heading">
          <div><p class="admin-eyebrow">ОБЛІК ВИДАЧІ</p><h1>{{ book.title }}</h1><p>{{ book.author || 'Автор не вказаний' }}</p></div>
          <button class="admin-primary-button" type="button" :disabled="Number(book.available_copies) === 0" @click="openCreate">Видати книгу</button>
        </div>

        <div class="loan-stats" aria-label="Статистика видачі">
          <div><span>Наявність</span><strong>{{ book.available_copies }}/{{ book.total_copies }}</strong></div>
          <div><span>Видано</span><strong>{{ book.active_loans_count }}</strong></div>
          <div><span>Усього примірників</span><strong>{{ book.total_copies }}</strong></div>
        </div>

        <div class="loan-toolbar">
          <div class="admin-segmented"><button :class="{ active: filter === 'active' }" @click="filter = 'active'">Активні</button><button :class="{ active: filter === 'all' }" @click="filter = 'all'">Уся історія</button></div>
        </div>
        <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
        <div v-if="visibleLoans.length === 0" class="admin-table-state">Записів видачі немає.</div>
        <div v-else class="loan-table-wrap">
          <table class="loan-table">
            <thead><tr><th>Хто взяв</th><th>Дата видачі</th><th>Дата повернення</th><th>Статус</th><th>Дії</th></tr></thead>
            <tbody>
              <tr v-for="loan in visibleLoans" :key="loan.id">
                <td data-label="Хто взяв">{{ loan.borrower_name }}</td><td data-label="Дата видачі">{{ formatDate(loan.issued_at) }}</td><td data-label="Дата повернення">{{ formatDate(loan.returned_at) }}</td>
                <td data-label="Статус"><span class="loan-status" :class="loan.status">{{ statusLabel(loan.status) }}</span></td>
                <td data-label="Дії"><div class="loan-actions"><button class="admin-secondary-button" type="button" @click="openEdit(loan)">Редагувати</button><button v-if="!loan.returned_at" class="admin-primary-button" type="button" @click="returnBook(loan)">Повернути</button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <div v-if="editorOpen" class="admin-modal-backdrop" @click.self="!saving && (editorOpen = false)">
      <section class="admin-modal loan-modal" role="dialog" aria-modal="true">
        <header class="admin-modal-header"><div><p class="admin-eyebrow">{{ editingId ? 'РЕДАГУВАННЯ' : 'НОВА ВИДАЧА' }}</p><h2>{{ editingId ? 'Редагувати запис' : 'Видати книгу' }}</h2></div><button class="admin-icon-button" type="button" @click="editorOpen = false">×</button></header>
        <form class="admin-book-form" @submit.prevent="saveLoan">
          <div class="admin-field"><label for="borrower-name">Хто взяв <b>*</b></label><span class="borrower-combobox"><input id="borrower-name" v-model="form.borrower_name" maxlength="190" autocomplete="off" role="combobox" aria-autocomplete="list" :aria-expanded="borrowerSuggestionsOpen && borrowerSuggestions.length > 0" required autofocus @focus="borrowerSuggestionsOpen = true" @input="borrowerSuggestionsOpen = true" @keydown.esc="borrowerSuggestionsOpen = false" @blur="borrowerSuggestionsOpen = false"><span v-if="borrowerSuggestionsOpen && borrowerSuggestions.length" class="borrower-suggestions" role="listbox"><button v-for="person in borrowerSuggestions" :key="person" type="button" role="option" @mousedown.prevent="selectBorrower(person)">{{ person }}</button></span></span></div>
          <label class="admin-field"><span>Дата видачі <b>*</b></span><input v-model="form.issued_at" type="date" required></label>
          <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
          <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="saving" @click="editorOpen = false">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="saving">{{ saving ? 'Збереження…' : 'Зберегти' }}</button></footer>
        </form>
      </section>
    </div>
  </main>
</template>
