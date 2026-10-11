<script setup>
defineProps({
  grades: { type: Array, required: true },
  selectedGrade: { type: Number, default: null },
})

defineEmits(['select'])
</script>

<template>
  <div class="grade-selector" role="group" aria-label="Фільтр за класом">
    <button
      v-for="grade in grades"
      :key="grade"
      class="grade-button"
      :class="[`grade-tone-${(grade - 1) % 3}`, { 'is-active': selectedGrade === grade }]"
      type="button"
      :aria-pressed="selectedGrade === grade"
      @click="$emit('select', grade)"
    >
      {{ grade }}
    </button>
  </div>
</template>