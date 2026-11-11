<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { coverPalette } from '../utils/bookPresentation.js'
import { convertCoverToWebp } from '../utils/coverImage.js'

const props = defineProps({
  book: { type: Object, default: null },
  defaultCategory: { type: String, default: 'textbook' },
  defaultGrade: { type: Number, default: null },
})
const emit = defineEmits(['close', 'saved', 'deleted'])

const saving = ref(false)
const deleting = ref(false)
const editorError = ref('')
const coverBusy = ref(false)
const pdfBusy = ref(false)
const coverPreview = ref(props.book?.cover_path ?? '')
const fileInput = ref(null)
const cameraInput = ref(null)
const pdfInput = ref(null)
const tags = ref([])
const tagsOpen = ref(false)
const placements = ref([])
const placementsOpen = ref(false)
const editingPlacementId = ref(null)
const placementDraft = ref('')
const placementInput = ref(null)
const placementField = ref(null)
const editingTagId = ref(null)
const tagDraft = ref('')
const tagColor = ref('blue')
const tagShowInFilters = ref(false)
const tagInput = ref(null)
const tagField = ref(null)
const tagColors = ['blue', 'coral', 'yellow', 'green', 'violet', 'pink', 'teal', 'orange', 'slate', 'brown']
const form = ref({
  id: props.book ? Number(props.book.id) : null,
  title: props.book?.title ?? '',
  year: props.book?.year ?? '',
  category: props.book?.category ?? props.defaultCategory,
  author: props.book?.author ?? '',
  grade: props.book?.grade ?? (props.defaultCategory === 'textbook' ? (props.defaultGrade ?? '') : ''),
  is_used: props.book?.is_used === undefined ? true : Boolean(Number(props.book.is_used)),
  placement_id: props.book?.placement?.id ? Number(props.book.placement.id) : '',
  pdf_url: props.book?.pdf_url ?? '',
  cover_path: props.book?.cover_path ?? '',
  total_copies: Number(props.book?.total_copies ?? 1),
  tag_ids: (props.book?.tags ?? []).map((tag) => Number(tag.id)),
})
const isEditing = computed(() => form.value.id !== null)
const selectedTags = computed(() => tags.value.filter((tag) => form.value.tag_ids.includes(Number(tag.id))))
const selectedPlacement = computed(() => placements.value.find((placement) => Number(placement.id) === Number(form.value.placement_id)))

function close() {
  if (saving.value || deleting.value || coverBusy.value || pdfBusy.value) return
  if (coverPreview.value.startsWith('blob:')) URL.revokeObjectURL(coverPreview.value)
  emit('close')
}

async function deleteBook() {
  if (!isEditing.value || !window.confirm(`Видалити книгу «${form.value.title}» разом з усією історією її видач? Цю дію неможливо скасувати.`)) return

  deleting.value = true
  editorError.value = ''
  try {
    await api(`/api/books/${form.value.id}`, { method: 'DELETE' })
    emit('deleted')
  } catch (error) {
    editorError.value = error.message
  } finally {
    deleting.value = false
  }
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

function toggleTag(tagId) {
  const id = Number(tagId)
  form.value.tag_ids = form.value.tag_ids.includes(id)
    ? form.value.tag_ids.filter((selectedId) => selectedId !== id)
    : [...form.value.tag_ids, id]
}

async function focusTagInput() {
  await nextTick()
  tagInput.value?.focus()
  tagInput.value?.select()
}

function setTagInput(element) {
  if (element) tagInput.value = element
}

function startAddTag() {
  editingTagId.value = 'new'
  tagDraft.value = ''
  tagColor.value = tagColors[Math.floor(Math.random() * tagColors.length)]
  tagShowInFilters.value = false
  focusTagInput()
}

function startEditTag(tag) {
  editingTagId.value = Number(tag.id)
  tagDraft.value = tag.name
  tagColor.value = tag.color || 'blue'
  tagShowInFilters.value = Boolean(Number(tag.show_in_filters))
  focusTagInput()
}

function cancelTagEdit() {
  editingTagId.value = null
  tagDraft.value = ''
  tagColor.value = 'blue'
  tagShowInFilters.value = false
}

function closeTagsOnOutsideClick(event) {
  if (tagsOpen.value && !tagField.value?.contains(event.target)) {
    tagsOpen.value = false
    cancelTagEdit()
  }
  if (placementsOpen.value && !placementField.value?.contains(event.target)) {
    placementsOpen.value = false
    cancelPlacementEdit()
  }
}

async function focusPlacementInput() {
  await nextTick()
  placementInput.value?.focus()
  placementInput.value?.select()
}

function setPlacementInput(element) {
  if (element) placementInput.value = element
}

function startAddPlacement() {
  editingPlacementId.value = 'new'
  placementDraft.value = ''
  focusPlacementInput()
}

function startEditPlacement(placement) {
  editingPlacementId.value = Number(placement.id)
  placementDraft.value = placement.name
  focusPlacementInput()
}

function cancelPlacementEdit() {
  editingPlacementId.value = null
  placementDraft.value = ''
}

function selectPlacement(placementId) {
  form.value.placement_id = Number(placementId)
  placementsOpen.value = false
  cancelPlacementEdit()
}

function sortPlacements() {
  placements.value.sort((left, right) => {
    if (left.name === 'Загальне') return -1
    if (right.name === 'Загальне') return 1
    return left.name.localeCompare(right.name, 'uk', { numeric: true })
  })
}

async function savePlacement() {
  const name = placementDraft.value.trim()
  if (!name) return
  editorError.value = ''
  try {
    const isNew = editingPlacementId.value === 'new'
    const result = await api(isNew ? '/api/admin/placements' : `/api/admin/placements/${editingPlacementId.value}`, {
      method: isNew ? 'POST' : 'PUT',
      body: JSON.stringify({ name }),
    })
    if (isNew) {
      placements.value.push(result.data)
      form.value.placement_id = Number(result.data.id)
    } else {
      const placement = placements.value.find((item) => Number(item.id) === Number(result.data.id))
      if (placement) Object.assign(placement, result.data)
    }
    sortPlacements()
    cancelPlacementEdit()
  } catch (error) {
    editorError.value = error.message
  }
}

async function deletePlacement(placement) {
  editorError.value = ''
  try {
    await api(`/api/admin/placements/${placement.id}`, { method: 'DELETE' })
    placements.value = placements.value.filter((item) => Number(item.id) !== Number(placement.id))
    if (Number(form.value.placement_id) === Number(placement.id)) {
      form.value.placement_id = Number(placements.value.find((item) => item.name === 'Загальне')?.id ?? '')
    }
    if (editingPlacementId.value === Number(placement.id)) cancelPlacementEdit()
  } catch (error) {
    editorError.value = error.message
  }
}

async function saveTag() {
  const name = tagDraft.value.trim()
  if (!name) return
  editorError.value = ''
  try {
    const isNew = editingTagId.value === 'new'
    const result = await api(isNew ? '/api/admin/tags' : `/api/admin/tags/${editingTagId.value}`, {
      method: isNew ? 'POST' : 'PUT',
      body: JSON.stringify({ name, color: tagColor.value, show_in_filters: tagShowInFilters.value }),
    })
    if (isNew) {
      tags.value.push(result.data)
      tags.value.sort((left, right) => left.name.localeCompare(right.name, 'uk'))
      form.value.tag_ids.push(Number(result.data.id))
    } else {
      const tag = tags.value.find((item) => Number(item.id) === Number(result.data.id))
      if (tag) Object.assign(tag, result.data)
      tags.value.sort((left, right) => left.name.localeCompare(right.name, 'uk'))
    }
    cancelTagEdit()
  } catch (error) {
    editorError.value = error.message
  }
}

async function deleteTag(tag) {
  editorError.value = ''
  try {
    await api(`/api/admin/tags/${tag.id}`, { method: 'DELETE' })
    tags.value = tags.value.filter((item) => Number(item.id) !== Number(tag.id))
    form.value.tag_ids = form.value.tag_ids.filter((id) => id !== Number(tag.id))
    if (editingTagId.value === Number(tag.id)) cancelTagEdit()
  } catch (error) {
    editorError.value = error.message
  }
}

async function uploadCover(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  coverBusy.value = true
  editorError.value = ''
  try {
    const webp = await convertCoverToWebp(file)
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

async function uploadPdf(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  if (file.size > 35 * 1024 * 1024) {
    editorError.value = 'PDF-файл має бути меншим за 35 МБ.'
    return
  }

  pdfBusy.value = true
  editorError.value = ''
  try {
    const body = new FormData()
    body.append('pdf', file)
    body.append('title', form.value.title.trim())
    if (form.value.category === 'textbook' && form.value.grade !== '') body.append('grade', String(form.value.grade))
    const result = await api('/api/admin/pdfs', { method: 'POST', body })
    form.value.pdf_url = result.pdf_url
  } catch (error) {
    editorError.value = error.message || 'Не вдалося завантажити PDF.'
  } finally {
    pdfBusy.value = false
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
    is_used: form.value.category === 'textbook' ? form.value.is_used : true,
    placement_id: form.value.category === 'literature' && form.value.placement_id !== '' ? Number(form.value.placement_id) : null,
    pdf_url: form.value.pdf_url.trim() || null,
    cover_path: form.value.cover_path || null,
    total_copies: Number(form.value.total_copies),
    tag_ids: form.value.category === 'literature' ? form.value.tag_ids : [],
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

onMounted(async () => {
  document.addEventListener('pointerdown', closeTagsOnOutsideClick)
  try {
    const result = await api('/api/admin/tags')
    tags.value = result.data ?? []
    const placementResult = await api('/api/admin/placements')
    placements.value = placementResult.data ?? []
    if (!form.value.placement_id) {
      form.value.placement_id = Number(placements.value.find((placement) => placement.name === 'Загальне')?.id ?? '')
    }
  } catch (error) {
    editorError.value = error.message
  }
})

onBeforeUnmount(() => document.removeEventListener('pointerdown', closeTagsOnOutsideClick))
</script>

<template>
  <div class="admin-modal-backdrop" @click.self="close">
    <section class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="book-editor-title">
      <header class="admin-modal-header">
        <div><p class="admin-eyebrow">{{ isEditing ? 'РЕДАГУВАННЯ' : 'НОВИЙ ЗАПИС' }}</p><h2 id="book-editor-title">{{ isEditing ? 'Редагувати книгу' : 'Додати книгу' }}</h2></div>
        <div class="admin-modal-header-actions">
          <button v-if="isEditing" class="admin-icon-button admin-delete-book" type="button" :disabled="saving || deleting || coverBusy || pdfBusy" aria-label="Видалити книгу" title="Видалити книгу" @click="deleteBook">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5" /></svg>
          </button>
          <button class="admin-icon-button" type="button" :disabled="saving || deleting || coverBusy || pdfBusy" aria-label="Закрити" title="Закрити" @click="close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
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
          <div v-else ref="placementField" class="admin-field tag-field">
            <span>Розміщення</span>
            <button class="tag-select-trigger" type="button" :aria-expanded="placementsOpen" @click="placementsOpen = !placementsOpen">
              <span :class="{ 'tag-select-placeholder': !selectedPlacement }">{{ selectedPlacement?.name || 'Оберіть розміщення' }}</span>
              <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4" /></svg>
            </button>
            <div v-if="placementsOpen" class="tag-dropdown">
              <button class="tag-add-button" type="button" @click="startAddPlacement">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12" /></svg>
                Додати
              </button>
              <div v-if="editingPlacementId === 'new'" class="tag-edit-row">
                <input :ref="setPlacementInput" v-model="placementDraft" maxlength="80" placeholder="Нове розміщення" @keydown.enter.prevent="savePlacement" @keydown.esc="cancelPlacementEdit">
                <button type="button" aria-label="Зберегти розміщення" title="Зберегти" @click="savePlacement">✓</button>
                <button type="button" aria-label="Скасувати" title="Скасувати" @click="cancelPlacementEdit">×</button>
              </div>
              <div v-for="placement in placements" :key="placement.id" class="tag-option-row">
                <div v-if="editingPlacementId === Number(placement.id)" class="tag-edit-row">
                  <input :ref="setPlacementInput" v-model="placementDraft" maxlength="80" @keydown.enter.prevent="savePlacement" @keydown.esc="cancelPlacementEdit">
                  <button type="button" aria-label="Зберегти розміщення" title="Зберегти" @click="savePlacement">✓</button>
                  <button type="button" aria-label="Скасувати" title="Скасувати" @click="cancelPlacementEdit">×</button>
                </div>
                <template v-else>
                  <label class="tag-option"><input type="radio" name="placement" :checked="Number(form.placement_id) === Number(placement.id)" @change="selectPlacement(placement.id)"><span>{{ placement.name }}</span></label>
                  <template v-if="placement.name !== 'Загальне'">
                    <button class="tag-row-action" type="button" aria-label="Редагувати розміщення" title="Редагувати" @click="startEditPlacement(placement)"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M13.8 3.2a1.8 1.8 0 0 1 2.5 2.5L7 15l-3 .8.8-3Z" /></svg></button>
                    <button class="tag-row-action danger" type="button" aria-label="Видалити розміщення" title="Видалити" @click="deletePlacement(placement)"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 6 8 8M14 6l-8 8" /></svg></button>
                  </template>
                </template>
              </div>
            </div>
          </div>
        </div>
        <label v-if="form.category === 'textbook'" class="admin-checkbox-field">
          <input v-model="form.is_used" type="checkbox">
          <span>Підручник використовується</span>
        </label>
        <div v-if="form.category === 'literature'" ref="tagField" class="admin-field tag-field">
          <span>Теги</span>
          <button class="tag-select-trigger" type="button" :aria-expanded="tagsOpen" @click="tagsOpen = !tagsOpen">
            <span v-if="selectedTags.length" class="tag-selected-list"><span v-for="tag in selectedTags" :key="tag.id" class="book-tag" :class="`tag-color-${tag.color || 'blue'}`">{{ tag.name }}</span></span>
            <span v-else class="tag-select-placeholder">Оберіть теги</span>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4" /></svg>
          </button>
          <div v-if="tagsOpen" class="tag-dropdown">
            <button class="tag-add-button" type="button" @click="startAddTag">
              <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12" /></svg>
              Додати
            </button>
            <div v-if="editingTagId === 'new'" class="tag-edit-row">
              <input :ref="setTagInput" v-model="tagDraft" maxlength="80" placeholder="Назва нового тегу" @keydown.enter.prevent="saveTag" @keydown.esc="cancelTagEdit">
              <button type="button" aria-label="Зберегти тег" title="Зберегти" @click="saveTag">✓</button>
              <button type="button" aria-label="Скасувати" title="Скасувати" @click="cancelTagEdit">×</button>
              <div class="tag-color-options" aria-label="Колір тегу"><button v-for="color in tagColors" :key="color" class="tag-color-swatch" :class="[`tag-color-${color}`, { active: tagColor === color }]" type="button" :aria-label="`Обрати колір ${color}`" @click="tagColor = color" /></div>
              <label class="tag-filter-option"><input v-model="tagShowInFilters" type="checkbox"><span>Показати в фільтрах для пошуку</span></label>
            </div>
            <div v-for="tag in tags" :key="tag.id" class="tag-option-row">
              <div v-if="editingTagId === Number(tag.id)" class="tag-edit-row">
                <input :ref="setTagInput" v-model="tagDraft" maxlength="80" @keydown.enter.prevent="saveTag" @keydown.esc="cancelTagEdit">
                <button type="button" aria-label="Зберегти тег" title="Зберегти" @click="saveTag">✓</button>
                <button type="button" aria-label="Скасувати" title="Скасувати" @click="cancelTagEdit">×</button>
                <div class="tag-color-options" aria-label="Колір тегу"><button v-for="color in tagColors" :key="color" class="tag-color-swatch" :class="[`tag-color-${color}`, { active: tagColor === color }]" type="button" :aria-label="`Обрати колір ${color}`" @click="tagColor = color" /></div>
                <label class="tag-filter-option"><input v-model="tagShowInFilters" type="checkbox"><span>Показати в фільтрах для пошуку</span></label>
              </div>
              <template v-else>
                <label class="tag-option"><input type="checkbox" :checked="form.tag_ids.includes(Number(tag.id))" @change="toggleTag(tag.id)"><span>{{ tag.name }}</span></label>
                <button class="tag-color-dot" :class="`tag-color-${tag.color || 'blue'}`" type="button" aria-label="Змінити колір тегу" title="Змінити колір" @click="startEditTag(tag)" />
                <button class="tag-row-action" type="button" aria-label="Редагувати тег" title="Редагувати" @click="startEditTag(tag)"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M13.8 3.2a1.8 1.8 0 0 1 2.5 2.5L7 15l-3 .8.8-3Z" /></svg></button>
                <button class="tag-row-action danger" type="button" aria-label="Видалити тег" title="Видалити" @click="deleteTag(tag)"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 6 8 8M14 6l-8 8" /></svg></button>
              </template>
            </div>
            <p v-if="tags.length === 0 && editingTagId !== 'new'" class="tag-empty">Тегів ще немає.</p>
          </div>
        </div>
        <label class="admin-field"><span>Кількість примірників</span><input v-model="form.total_copies" type="number" min="0" max="65535" inputmode="numeric" required></label>
        <div class="admin-field">
          <label for="book-pdf-url">Зовнішнє посилання на PDF</label>
          <span class="admin-input-with-action">
            <input id="book-pdf-url" v-model="form.pdf_url" type="text" inputmode="url" placeholder="https://…" :disabled="pdfBusy">
            <button class="admin-input-action" type="button" :disabled="pdfBusy" :aria-label="pdfBusy ? 'Завантаження PDF' : 'Прикріпити PDF-файл'" :title="pdfBusy ? 'Завантаження…' : 'Прикріпити PDF-файл'" @click.prevent="pdfInput?.click()">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20.5 11.5-8.7 8.7a5 5 0 0 1-7.1-7.1l9.2-9.2a3.5 3.5 0 0 1 5 5l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.7-8.7" /></svg>
            </button>
            <input ref="pdfInput" class="visually-hidden" type="file" accept="application/pdf,.pdf" @change="uploadPdf">
          </span>
        </div>
        <div class="admin-field">
          <span>Обкладинка</span>
          <div class="admin-cover-field">
            <div class="admin-cover-preview" :class="`cover-${coverPalette(form)}`"><img v-if="coverPreview || form.cover_path" :src="coverPreview || form.cover_path" alt="Попередній перегляд обкладинки"><span v-else>{{ form.title.slice(0, 1) || 'К' }}</span></div>
            <div class="admin-cover-controls">
              <input ref="fileInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp,image/avif,image/heic,image/heif,.heic,.heif" @change="uploadCover">
              <input ref="cameraInput" class="visually-hidden" type="file" accept="image/*" capture="environment" @change="uploadCover">
              <div class="admin-cover-buttons">
                <button class="admin-secondary-button" type="button" :disabled="coverBusy" @click="fileInput?.click()">{{ coverBusy ? 'Конвертація…' : 'Завантажити зображення' }}</button>
                <button class="admin-camera-button" type="button" :disabled="coverBusy" aria-label="Зробити фото обкладинки" title="Зробити фото" @click="cameraInput?.click()">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5h3l1.5-2h7l1.5 2h3v10H4z"/><circle cx="12" cy="13.5" r="3.25"/></svg>
                </button>
                <button v-if="coverPreview || form.cover_path" class="admin-remove-cover" type="button" :disabled="coverBusy" aria-label="Видалити обкладинку" title="Видалити обкладинку" @click="removeCover">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        <p v-if="editorError" class="admin-error" role="alert">{{ editorError }}</p>
        <footer class="admin-form-actions"><button class="admin-secondary-button" type="button" :disabled="saving || deleting || coverBusy || pdfBusy" @click="close">Скасувати</button><button class="admin-primary-button" type="submit" :disabled="saving || deleting || coverBusy || pdfBusy">{{ saving ? 'Збереження…' : (isEditing ? 'Зберегти зміни' : 'Додати книгу') }}</button></footer>
      </form>
    </section>
  </div>
</template>
