<script setup>
defineProps({
  state: { type: String, required: true },
  message: { type: String, default: '' },
})

defineEmits(['retry'])
</script>

<template>
  <section v-if="state === 'loading'" class="book-grid" aria-label="Завантаження книг" aria-busy="true">
    <div v-for="item in 5" :key="item" class="book-skeleton">
      <div class="skeleton-cover"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line short"></div>
    </div>
  </section>
  <section v-else class="catalog-state" :class="`state-${state}`" :role="state === 'error' ? 'alert' : 'status'">
    <div v-if="state === 'error'">
      <p class="state-label">НЕ ВДАЛОСЯ ЗАВАНТАЖИТИ</p>
      <h2>Каталог тимчасово недоступний</h2>
      <p>{{ message }}</p>
      <button class="retry-button" type="button" @click="$emit('retry')">Спробувати ще раз</button>
    </div>
    <div v-else-if="state === 'empty'">
      <h2>Книг не знайдено</h2>
      <p>{{ message }}</p>
    </div>
    <div v-else>
      <h2>Оберіть клас</h2>
      <p>Покажемо книги, доступні для цього класу.</p>
    </div>
  </section>
</template>