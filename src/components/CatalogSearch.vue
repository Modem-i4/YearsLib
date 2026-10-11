<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  count: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  selectedGrade: { type: Number, default: null },
  collection: { type: String, default: 'textbook' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="catalog-toolbar">
    <p class="results-label" aria-live="polite">
      <strong>{{ loading ? '…' : count }}</strong>
      <span>{{ count === 1 ? 'книга' : 'книг' }}</span>
      <template v-if="collection === 'textbook'">
        <span class="results-divider">·</span>
        <span>{{ selectedGrade }} клас</span>
      </template>
      <span v-else class="results-divider">·</span>
      <span v-if="collection === 'literature'">Література</span>
    </p>
    <label class="search-field">
      <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" /></svg>
      <span class="visually-hidden">Пошук за назвою або автором</span>
      <input
        :value="modelValue"
        type="search"
        placeholder="Назва або автор"
        autocomplete="off"
        @input="$emit('update:modelValue', $event.target.value)"
      >
      <kbd>/</kbd>
    </label>
  </div>
</template>