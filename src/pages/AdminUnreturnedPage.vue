<script setup>
import { computed, onMounted, ref } from 'vue'
import LibraryHeader from '../components/LibraryHeader.vue'

const authenticated = ref(false)
const checking = ref(true)
const loading = ref(false)
const error = ref('')
const loans = ref([])
const literatureSearch = ref('')
const literatureOpen = ref(true)
const textbooksOpen = ref(false)
const returnOpen = ref(false)
const returning = ref(false)
const returningLoan = ref(null)
const returnForm = ref({ quantity: 1, returned_at: localDate() })

const literatureLoans = computed(() => loans.value.filter((loan) => loan.category === 'literature'))
const textbookLoans = computed(() => loans.value.filter((loan) => loan.category === 'textbook'))
const filteredLiteratureLoans = computed(() => {
  const query = literatureSearch.value.trim().toLocaleLowerCase('uk-UA')
  if (!query) return literatureLoans.value
  return literatureLoans.value.filter((loan) => [loan.title, loan.author, loan.borrower_name, loan.placement_name, loan.year]
    .some((value) => String(value ?? '').toLocaleLowerCase('uk-UA').includes(query)))
})
const textbookGroups = computed(() => {
  const groups = new Map()
  for (const loan of textbookLoans.value) {
    const parsedGrade = Number(loan.grade)
    const grade = Number.isInteger(parsedGrade) && parsedGrade >= 1 ? parsedGrade : null
    const key = grade === null ? 'none' : String(grade)
    if (!groups.has(key)) groups.set(key, { grade, loans: [] })
    groups.get(key).loans.push(loan)
  }
  return [...groups.values()]
    .sort((left, right) => left.grade === null ? 1 : right.grade === null ? -1 : left.grade - right.grade)
    .map((group) => ({
      ...group,
      label: group.grade === null ? 'Без класу' : `${group.grade} клас`,
      loans: group.loans.sort((left, right) => left.title.localeCompare(right.title, 'uk-UA') || left.borrower_name.localeCompare(right.borrower_name, 'uk-UA')),
    }))
})

function localDate() {
  const now = new Date()
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
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
    const result = await api('/api/admin/loans/unreturned')
    loans.value = result.data ?? []
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

function openReturn(loan) {
  returningLoan.value = loan
  returnForm.value = { quantity: Number(loan.remaining_quantity), returned_at: localDate() }
  error.value = ''
  returnOpen.value = true
}

async function submitReturn() {
  returning.value = true
  error.value = ''
  try {
    await api(`/api/admin/loans/${returningLoan.value.id}/return`, {
      method: 'POST',
      body: JSON.stringify(returnForm.value),
    })
    returnOpen.value = false
    returningLoan.value = null
    await load()
  } catch (caught) {
    error.value = caught.message
  } finally {
    returning.value = false
  }
}

function formatDate(value) {
  return new Intl.DateTimeFormat('uk-UA').format(new Date(`${value}T12:00:00`))
}

function bookDetails(loan) {
  const location = loan.category === 'textbook'
    ? (loan.grade ? `${loan.grade} клас` : null)
    : (loan.placement_name && loan.placement_name !== 'Загальне' ? loan.placement_name : null)
  return [loan.author, loan.year, location].filter(Boolean).join(' · ')
}

onMounted(initialize)
</script>

<template>
  <main class="library-shell admin-shell min-h-dvh">
    <LibraryHeader collection="unreturned" :is-viewer="true" />

    <section v-if="checking" class="admin-login-state" role="status">Перевірка сесії…</section>
    <section v-else-if="!authenticated" class="admin-login-state">
      <div><p>Для перегляду неповернених книжок потрібен вхід адміністратора.</p><a class="admin-primary-button" href="/admin">Увійти</a></div>
    </section>
    <section v-else class="admin-content loan-content unreturned-content">
      <div class="loan-heading">
        <div><p class="admin-eyebrow">ОБЛІК ВИДАЧІ</p><h1>Неповернені книжки</h1><p>Усі активні видачі літератури та підручників.</p></div>
      </div>

      <p v-if="error && !returnOpen" class="admin-error" role="alert">{{ error }}</p>
      <div v-if="loading" class="admin-table-state">Завантаження…</div>
      <template v-else>
        <section class="unreturned-section">
          <div class="unreturned-section-header">
            <button class="unreturned-section-title" type="button" :aria-expanded="literatureOpen" @click="literatureOpen = !literatureOpen">Література</button>
            <label v-if="literatureLoans.length" class="search-field unreturned-search">
              <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" /></svg>
              <span class="visually-hidden">Пошук неповерненої літератури</span>
              <input v-model="literatureSearch" type="search" placeholder="Назва, автор або позичальник" autocomplete="off">
            </label>
            <strong class="unreturned-section-count">{{ literatureLoans.length }}</strong>
            <button class="unreturned-section-chevron" type="button" :aria-label="literatureOpen ? 'Згорнути літературу' : 'Розгорнути літературу'" :aria-expanded="literatureOpen" @click="literatureOpen = !literatureOpen"><svg viewBox="0 0 20 20" aria-hidden="true" :class="{ open: literatureOpen }"><path d="m6 8 4 4 4-4" /></svg></button>
          </div>
          <div v-if="literatureOpen">
            <div v-if="literatureLoans.length === 0" class="admin-table-state">Усю літературу повернено.</div>
            <template v-else>
              <div v-if="filteredLiteratureLoans.length === 0" class="admin-table-state">За цим запитом нічого не знайдено.</div>
              <div v-else class="loan-table-wrap">
                <table class="loan-table unreturned-table">
                  <thead><tr><th>Книга</th><th>Хто взяв</th><th>Дата видачі</th><th>Неповернено</th><th>Дії</th></tr></thead>
                  <tbody><tr v-for="loan in filteredLiteratureLoans" :key="loan.id"><td data-label="Книга"><a class="unreturned-book-link" :href="`/admin/books/${loan.book_id}`">{{ loan.title }}</a><small v-if="bookDetails(loan)">{{ bookDetails(loan) }}</small></td><td data-label="Хто взяв">{{ loan.borrower_name }}</td><td data-label="Дата видачі">{{ formatDate(loan.issued_at) }}</td><td data-label="Неповернено">{{ loan.remaining_quantity }} із {{ loan.quantity }}</td><td data-label="Дії"><button class="admin-primary-button" type="button" @click="openReturn(loan)">Повернути</button></td></tr></tbody>
                </table>
              </div>
            </template>
          </div>
        </section>

        <section class="unreturned-section">
          <button class="unreturned-section-toggle" type="button" :aria-expanded="textbooksOpen" @click="textbooksOpen = !textbooksOpen">
            <span>Підручники</span><strong>{{ textbookLoans.length }}</strong><svg viewBox="0 0 20 20" aria-hidden="true" :class="{ open: textbooksOpen }"><path d="m6 8 4 4 4-4" /></svg>
          </button>
          <div v-if="textbooksOpen">
            <div v-if="textbookLoans.length === 0" class="admin-table-state">Усі підручники повернено.</div>
            <div v-else class="unreturned-class-groups">
              <section v-for="group in textbookGroups" :key="group.grade ?? 'none'" class="unreturned-class-group">
                <h3>{{ group.label }} <span>{{ group.loans.length }}</span></h3>
                <div class="loan-table-wrap">
                  <table class="loan-table unreturned-table">
                    <thead><tr><th>Книга</th><th>Хто взяв</th><th>Дата видачі</th><th>Неповернено</th><th>Дії</th></tr></thead>
                    <tbody><tr v-for="loan in group.loans" :key="loan.id"><td data-label="Книга"><a class="unreturned-book-link" :href="`/admin/books/${loan.book_id}`">{{ loan.title }}</a><small v-if="bookDetails(loan)">{{ bookDetails(loan) }}</small></td><td data-label="Хто взяв">{{ loan.borrower_name }}</td><td data-label="Дата видачі">{{ formatDate(loan.issued_at) }}</td><td data-label="Неповернено">{{ loan.remaining_quantity }} із {{ loan.quantity }}</td><td data-label="Дії"><button class="admin-primary-button" type="button" @click="openReturn(loan)">Повернути</button></td></tr></tbody>
                  </table>
                </div>
              </section>
            </div>
          </div>
        </section>
      </template>
    </section>

    <div v-if="returnOpen" class="admin-modal-backdrop" @click.self="!returning && (returnOpen = false)">
      <section class="admin-modal loan-return-modal" role="dialog" aria-modal="true">
        <header class="admin-modal-header"><div><p class="admin-eyebrow">ПОВЕРНЕННЯ</p><h2>{{ returningLoan?.title }}</h2></div><button class="admin-icon-button" type="button" :disabled="returning" @click="returnOpen = false">×</button></header>
        <form class="admin-book-form" @submit.prevent="submitReturn">
          <p class="unreturned-borrower">Видача: {{ returningLoan?.borrower_name }}</p>
          <label class="admin-field"><span>Кількість <b>*</b></span><input v-model="returnForm.quantity" type="number" min="1" :max="returningLoan?.remaining_quantity" inputmode="numeric" required autofocus></label>
          <label class="admin-field"><span>Дата повернення <b>*</b></span><input v-model="returnForm.returned_at" type="date" required></label>
          <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
          <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="returning" @click="returnOpen = false">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="returning">{{ returning ? 'Збереження…' : 'Повернено' }}</button></footer>
        </form>
      </section>
    </div>
  </main>
</template>
