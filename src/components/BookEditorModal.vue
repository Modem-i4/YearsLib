<script setup>
import { computed, ref } from 'vue'
import { coverPalette } from '../utils/bookPresentation.js'

const props = defineProps({
  book: { type: Object, default: null },
  defaultCategory: { type: String, default: 'textbook' },
  defaultGrade: { type: Number, default: null },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const editorError = ref('')
const coverBusy = ref(false)
const coverPreview = ref(props.book?.cover_path ?? '')
const fileInput = ref(null)
const form = ref({
  id: props.book ? Number(props.book.id) : null,
  title: props.book?.title ?? '',
  year: props.book?.year ?? '',
  category: props.book?.category ?? props.defaultCategory,
  author: props.book?.author ?? '',
  grade: props.book?.grade ?? (props.defaultCategory === 'textbook' ? (props.defaultGrade ?? '') : ''),
  pdf_url: props.book?.pdf_url ?? '',
  cover_path: props.book?.cover_path ?? '',
  total_copies: Number(props.book?.total_copies ?? 1),
})
const isEditing = computed(() => form.value.id !== null)

function close() {
  if (saving.value || coverBusy.value) return
  if (coverPreview.value.startsWith('blob:')) URL.revokeObjectURL(coverPreview.value)
  emit('close')
}

function removeCover() {
  if (coverPreview.value.startsWith('blob:')) URL.revokeObjectURL(coverPreview.value)
  coverPreview.value = ''
  form.value.cover_path = ''
}

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
      if (!blob) return reject(new Error('Браузер не зміг конвертувати зображення.'))
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

async function save() {
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
    total_copies: Number(form.value.total_copies),
  }

  try {
    await api(form.value.id ? `/api/books/${form.value.id}` : '/api/books', {
      method: form.value.id ? 'PUT' : 'POST',
      body: JSON.stringify(payload),
    })
    emit('saved')
  } catch (error) {
    editorError.value = error.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="admin-modal-backdrop" @click.self="close">
    <section class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="book-editor-title">
      <header class="admin-modal-header">
        <div><p class="admin-eyebrow">{{ isEditing ? 'РЕДАГУВАННЯ' : 'НОВИЙ ЗАПИС' }}</p><h2 id="book-editor-title">{{ isEditing ? 'Редагувати книгу' : 'Додати книгу' }}</h2></div>
        <button class="admin-icon-button" type="button" aria-label="Закрити" title="Закрити" @click="close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
      </header>
      <form class="admin-book-form" @submit.prevent="save">
        <label class="admin-field"><span>Назва <b>*</b></span><input v-model="form.title" maxlength="190" required autofocus></label>
        <div class="admin-form-grid">
          <label class="admin-field"><span>Розділ <b>*</b></span><select v-model="form.category" required><option value="textbook">Підручники</option><option value="literature">Література</option></select></label>
          <label class="admin-field"><span>Рік видання</span><input v-model="form.year" type="number" min="1000" max="9999" inputmode="numeric"></label>
        </div>
        <div class="admin-form-grid">
          <label class="admin-field"><span>Автор</span><input v-model="form.author" maxlength="190"></label>
          <label v-if="form.category === 'textbook'" class="admin-field"><span>Клас</span><select v-model="form.grade"><option value="">Не вказано</option><option v-for="grade in 11" :key="grade" :value="String(grade)">{{ grade }} клас</option></select></label>
        </div>
        <label class="admin-field"><span>Кількість примірників</span><input v-model="form.total_copies" type="number" min="0" max="65535" inputmode="numeric" required></label>
        <label class="admin-field"><span>Зовнішнє посилання на PDF</span><input v-model="form.pdf_url" type="url" placeholder="https://…"></label>
        <div class="admin-field">
          <span>Обкладинка</span>
          <div class="admin-cover-field">
            <div class="admin-cover-preview" :class="`cover-${coverPalette(form)}`"><img v-if="coverPreview || form.cover_path" :src="coverPreview || form.cover_path" alt="Попередній перегляд обкладинки"><span v-else>{{ form.title.slice(0, 1) || 'К' }}</span></div>
            <div class="admin-cover-controls">
              <input ref="fileInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp,image/avif" @change="uploadCover">
              <div class="admin-cover-buttons">
                <button class="admin-secondary-button" type="button" :disabled="coverBusy" @click="fileInput?.click()">{{ coverBusy ? 'Конвертація…' : 'Завантажити зображення' }}</button>
                <button v-if="coverPreview || form.cover_path" class="admin-remove-cover" type="button" :disabled="coverBusy" aria-label="Видалити обкладинку" title="Видалити обкладинку" @click="removeCover">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        <p v-if="editorError" class="admin-error" role="alert">{{ editorError }}</p>
        <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="saving || coverBusy" @click="close">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="saving || coverBusy">{{ saving ? 'Збереження…' : (isEditing ? 'Зберегти зміни' : 'Додати книгу') }}</button></footer>
      </form>
    </section>
  </div>
</template>
