<template>
  <Transition name="fade"><div v-if="open && backdrop" class="sheet-backdrop" @click="emit('close')" /></Transition>
  <Transition name="sheet">
    <section v-if="open" ref="sheet" class="sheet" role="dialog" aria-modal="true" :aria-label="title" :style="dragStyle">
      <div class="sheet__drag" @touchstart.passive="start" @touchmove.passive="move" @touchend="end">
        <div class="sheet__grip" />
        <header v-if="title || $slots.head" class="sheet__head">
          <button v-if="back" class="icon-btn icon-btn--flat" :aria-label="t.common.back" @click="emit('back')"><Icon name="back" /></button>
          <slot name="head"><h2 class="sheet__title">{{ title }}</h2></slot>
          <button class="icon-btn icon-btn--flat" :aria-label="t.common.close" @click="emit('close')"><Icon name="close" :size="18" /></button>
        </header>
      </div>
      <div class="sheet__body"><slot /></div>
    </section>
  </Transition>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ open: boolean; title?: string; back?: boolean; backdrop?: boolean }>(), { title: '', back: false, backdrop: true });
const emit = defineEmits<{ close: []; back: [] }>();
const { t } = useAppCopy();

// Naar beneden vegen sluit de sheet.
const offset = ref(0);
let startY: number | null = null;
const dragStyle = computed(() => offset.value ? { transform: `translateY(${offset.value}px)`, transition: 'none' } : undefined);
function start(event: TouchEvent) { startY = event.touches[0]?.clientY ?? null; }
function move(event: TouchEvent) {
  const y = event.touches[0]?.clientY;
  if (startY !== null && y !== undefined) offset.value = Math.max(0, y - startY);
}
function end() {
  if (offset.value > 90) emit('close');
  offset.value = 0;
  startY = null;
}
</script>

<style scoped>
.sheet__drag { flex: none; touch-action: none; }
.icon-btn--flat { width: 36px; height: 36px; border-radius: 50%; background: #e9ebef; box-shadow: none; color: var(--vb-ink-3); }
</style>
