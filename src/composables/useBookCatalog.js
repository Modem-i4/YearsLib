import { computed, onMounted, ref, watch } from 'vue'

const savedGradeKey = 'library.selectedGrade'
const savedCollectionKey = 'library.collection'
const collections = ['textbook', 'literature']

function collectionFromHash(hash) {
  if (hash === 'textbooks') return 'textbook'
  return collections.includes(hash) ? hash : null
}

function readSavedGrade() {
  try {
    const grade = Number(localStorage.getItem(savedGradeKey))
    return Number.isInteger(grade) && grade >= 1 && grade <= 11 ? grade : null
  } catch {
    return null
  }
}

function readSavedCollection() {
  const hashCollection = collectionFromHash(window.location.hash.slice(1))
  if (hashCollection) return hashCollection

  try {
    const savedCollection = localStorage.getItem(savedCollectionKey)
    return collections.includes(savedCollection) ? savedCollection : 'textbook'
  } catch {
    return 'textbook'
  }
}

export function useBookCatalog() {
  const selectedCollection = ref(readSavedCollection())
  const selectedGrade = ref(readSavedGrade())
  const gradeOptions = ref([])
  const books = ref([])
  const search = ref('')
  const loading = ref(false)
  const loadError = ref('')
  let requestNumber = 0

  const visibleBooks = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('uk')
    if (!query) return books.value

    return books.value.filter((book) =>
      [book.title, book.author].some((value) => value?.toLocaleLowerCase('uk').includes(query)),
    )
  })

  function selectGrade(grade) {
    selectedGrade.value = grade
    try {
      localStorage.setItem(savedGradeKey, String(grade))
    } catch {}
  }

  async function loadBooks(collection = selectedCollection.value, grade = selectedGrade.value) {
    const currentRequest = ++requestNumber
    books.value = []
    search.value = ''
    loadError.value = ''
    if (collection === 'textbook') gradeOptions.value = []
    loading.value = true

    try {
      const params = new URLSearchParams({ category: collection })
      if (collection === 'textbook' && grade !== null) params.set('grade', String(grade))
      const response = await fetch(`/api/books?${params}`, {
        headers: { Accept: 'application/json' },
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.error || 'Не вдалося завантажити книги.')
      if (currentRequest === requestNumber) {
        books.value = Array.isArray(payload.data) ? payload.data : []
        if (collection === 'textbook') {
          gradeOptions.value = Array.isArray(payload.grades)
            ? payload.grades.map(Number).filter((grade) => Number.isInteger(grade) && grade >= 1 && grade <= 11)
            : [...new Set(books.value.map((book) => Number(book.grade)).filter((grade) => Number.isInteger(grade) && grade >= 1 && grade <= 11))].sort((a, b) => a - b)

          if (selectedGrade.value !== null && !gradeOptions.value.includes(selectedGrade.value)) {
            selectedGrade.value = null
            try {
              localStorage.removeItem(savedGradeKey)
            } catch {}
          }
        }
      }
    } catch (error) {
      if (currentRequest === requestNumber) {
        loadError.value = error instanceof Error ? error.message : 'Не вдалося завантажити книги.'
      }
    } finally {
      if (currentRequest === requestNumber) loading.value = false
    }
  }

  watch([selectedCollection, selectedGrade], ([collection, grade]) => {
    try {
      localStorage.setItem(savedCollectionKey, collection)
    } catch {}

    loadBooks(collection, collection === 'textbook' ? grade : null)
  }, { immediate: true })

  onMounted(() => {
    const syncCollectionFromHash = () => {
      const hashCollection = collectionFromHash(window.location.hash.slice(1))
      if (hashCollection) selectedCollection.value = hashCollection
    }
    window.addEventListener('hashchange', syncCollectionFromHash)
  })

  return {
    gradeOptions,
    selectedCollection,
    selectedGrade,
    books,
    search,
    loading,
    loadError,
    visibleBooks,
    selectGrade,
    loadBooks,
  }
}