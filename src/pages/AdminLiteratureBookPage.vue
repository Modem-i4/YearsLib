<script setup>
import { computed, onMounted, ref } from 'vue'
import BookEditorModal from '../components/BookEditorModal.vue'
import LibraryHeader from '../components/LibraryHeader.vue'

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
const returnOpen = ref(false)
const returning = ref(false)
const returningLoan = ref(null)
const returnForm = ref({ quantity: 1, returned_at: localDate() })
const bookEditorOpen = ref(false)

function localDate() {
  const now = new Date()
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

function emptyLoan() {
  const issued = localDate()
  return { borrower_name: '', issued_at: issued, quantity: 1 }
}

const visibleLoans = computed(() => filter.value === 'active'
  ? loans.value.filter((loan) => Number(loan.remaining_quantity) > 0)
  : loans.value)

const bookHeadingDetails = computed(() => {
  if (!book.value) return ''
  return [book.value.year, book.value.grade ? `${book.value.grade} клас` : null].filter(Boolean).join(', ')
})

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
  form.value = {
    borrower_name: loan.borrower_name,
    issued_at: loan.issued_at,
    quantity: Number(loan.quantity),
    returned_quantity: Number(loan.returned_quantity),
  }
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
  const remaining = Number(loan.remaining_quantity)
  if (remaining > 1) {
    returningLoan.value = loan
    returnForm.value = { quantity: remaining, returned_at: localDate() }
    returnOpen.value = true
    return
  }
  try {
    await api(`/api/admin/loans/${loan.id}/return`, { method: 'POST', body: JSON.stringify({ returned_at: localDate(), quantity: 1 }) })
    await load()
  } catch (caught) {
    error.value = caught.message
  }
}

async function submitReturn() {
  returning.value = true
  error.value = ''
  try {
    await api(`/api/admin/loans/${returningLoan.value.id}/return`, { method: 'POST', body: JSON.stringify(returnForm.value) })
    returnOpen.value = false
    returningLoan.value = null
    await load()
  } catch (caught) {
    error.value = caught.message
  } finally {
    returning.value = false
  }
}

async function bookSaved() {
  bookEditorOpen.value = false
  await load()
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('uk-UA').format(new Date(`${value}T12:00:00`))
}

function statusLabel(status) {
  return { active: 'Видано', partial: 'Частково повернено', returned: 'Повернено' }[status] ?? status
}

onMounted(initialize)
</script>

<template>
  <main class="library-shell admin-shell min-h-dvh">
    <LibraryHeader :collection="book?.category ?? 'literature'" :is-viewer="true" />

    <section v-if="checking" class="admin-login-state" role="status">Перевірка сесії…</section>
    <section v-else-if="!authenticated" class="admin-login-state">
      <div><p>Для перегляду видач потрібен вхід адміністратора.</p><a class="admin-primary-button" href="/admin">Увійти</a></div>
    </section>
    <section v-else class="admin-content loan-content">
      <p v-if="error && !book" class="admin-error" role="alert">{{ error }}</p>
      <div v-if="loading && !book" class="admin-table-state">Завантаження…</div>
      <template v-else-if="book">
        <div class="loan-heading">
          <div><p class="admin-eyebrow">ОБЛІК ВИДАЧІ</p><h1>{{ book.title }}<span v-if="bookHeadingDetails"> ({{ bookHeadingDetails }})</span></h1><p>{{ book.author || 'Автор не вказаний' }}</p></div>
          <div class="loan-heading-actions">
            <button class="loan-edit-book" type="button" aria-label="Редагувати книгу" title="Редагувати книгу" @click="bookEditorOpen = true">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z" /></svg>
            </button>
            <button class="loan-issue-button" type="button" :disabled="Number(book.available_copies) === 0" @click="openCreate">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V7a2 2 0 0 1 4 0v3M11 10V5a2 2 0 0 1 4 0v5M15 10V7a2 2 0 0 1 4 0v7c0 4-2.7 7-7 7h-1.2a6 6 0 0 1-5.3-3.2L3.2 13.5a2 2 0 0 1 3.5-1.9L8 14"/></svg>
              <span>Видати книгу</span>
            </button>
          </div>
        </div>

        <div class="loan-stats" aria-label="Статистика видачі">
          <div><span>Наявність</span><strong>{{ book.available_copies }}/{{ book.total_copies }}</strong></div>
          <div><span>Видано</span><strong>{{ book.active_loans_count }}</strong></div>
          <div class="loan-total-stat"><span>Усього примірників</span><strong>{{ book.total_copies }}</strong></div>
        </div>

        <div class="loan-toolbar">
          <div class="admin-segmented"><button :class="{ active: filter === 'active' }" @click="filter = 'active'">Активні</button><button :class="{ active: filter === 'all' }" @click="filter = 'all'">Уся історія</button></div>
        </div>
        <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
        <div v-if="visibleLoans.length === 0" class="admin-table-state">Записів видачі немає.</div>
        <div v-else class="loan-table-wrap">
          <table class="loan-table">
            <thead><tr><th>Хто взяв</th><th>Дата видачі</th><th>Примірники</th><th>Дата повернення</th><th>Статус</th><th>Дії</th></tr></thead>
            <tbody>
              <tr v-for="loan in visibleLoans" :key="loan.id">
                <td data-label="Хто взяв">{{ loan.borrower_name }}</td><td data-label="Дата видачі">{{ formatDate(loan.issued_at) }}</td><td data-label="Примірники">{{ loan.returned_quantity }}/{{ loan.quantity }} повернено</td><td data-label="Дата повернення">{{ formatDate(loan.returned_at) }}</td>
                <td data-label="Статус"><span class="loan-status" :class="loan.status">{{ statusLabel(loan.status) }}</span></td>
                <td data-label="Дії"><div class="loan-actions"><button class="admin-secondary-button" type="button" @click="openEdit(loan)">Редагувати</button><button v-if="Number(loan.remaining_quantity) > 0" class="admin-primary-button" type="button" @click="returnBook(loan)">Повернути</button></div></td>
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
          <label class="admin-field"><span>Скільки видано <b>*</b></span><input v-model="form.quantity" type="number" min="1" max="65535" inputmode="numeric" required></label>
          <label v-if="editingId" class="admin-field"><span>Скільки повернено <b>*</b></span><input v-model="form.returned_quantity" type="number" min="0" :max="form.quantity" inputmode="numeric" required></label>
          <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
          <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="saving" @click="editorOpen = false">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="saving">{{ saving ? 'Збереження…' : 'Зберегти' }}</button></footer>
        </form>
      </section>
    </div>

    <div v-if="returnOpen" class="admin-modal-backdrop" @click.self="!returning && (returnOpen = false)">
      <section class="admin-modal loan-return-modal" role="dialog" aria-modal="true">
        <header class="admin-modal-header"><div><p class="admin-eyebrow">ПОВЕРНЕННЯ</p><h2>Скільки повернено?</h2></div><button class="admin-icon-button" type="button" @click="returnOpen = false">×</button></header>
        <form class="admin-book-form" @submit.prevent="submitReturn">
          <label class="admin-field"><span>Кількість <b>*</b></span><input v-model="returnForm.quantity" type="number" min="1" :max="returningLoan?.remaining_quantity" inputmode="numeric" required autofocus></label>
          <label class="admin-field"><span>Дата повернення <b>*</b></span><input v-model="returnForm.returned_at" type="date" required></label>
          <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
          <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="returning" @click="returnOpen = false">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="returning">{{ returning ? 'Збереження…' : 'Повернено' }}</button></footer>
        </form>
      </section>
    </div>

    <BookEditorModal v-if="bookEditorOpen" :book="book" @close="bookEditorOpen = false" @saved="bookSaved" />
  </main>
</template>
