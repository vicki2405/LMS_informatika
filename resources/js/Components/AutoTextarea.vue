<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  placeholder: { type: String, default: '' },
  minHeight: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const el = ref(null);

function resize() {
  const t = el.value;
  if (!t) return;
  t.style.height = 'auto';
  const h = t.scrollHeight + 2;
  t.style.height = Math.max(h, props.minHeight) + 'px';
}

function onInput(e) {
  emit('update:modelValue', e.target.value);
  resize();
}

onMounted(resize);

defineExpose({ el, resize });
</script>

<template>
  <textarea
    ref="el"
    :value="modelValue"
    :rows="rows"
    :placeholder="placeholder"
    class="resize-none overflow-hidden"
    @input="onInput"
  ></textarea>
</template>
