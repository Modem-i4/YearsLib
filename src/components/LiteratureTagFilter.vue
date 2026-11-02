<script setup>
defineProps({
  tags: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
})

defineEmits(['toggle'])
</script>

<template>
  <div class="literature-filter-row">
    <h2>Теги</h2>
    <div class="literature-filter-list" aria-label="Фільтрувати літературу за тегами">
      <button
        v-for="tag in tags"
        :key="tag.id"
        class="literature-filter-button"
        :class="[`tag-color-${tag.color || 'blue'}`, { 'is-active': selectedIds.includes(Number(tag.id)) }]"
        type="button"
        :aria-pressed="selectedIds.includes(Number(tag.id))"
        @click="$emit('toggle', tag.id)"
      >
        {{ tag.name }}
      </button>
    </div>
  </div>
</template>
