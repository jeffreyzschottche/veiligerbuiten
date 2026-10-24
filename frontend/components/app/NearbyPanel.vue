<template>
  <section class="nearby" :class="{ 'is-open': expanded }" :style="{ '--rows': visibleRows }">
    <button class="nearby__head" :aria-expanded="expanded" @click="expanded = !expanded">
      <span class="nearby__grip" />
      <span class="nearby__title">
        <span v-if="items[0]" class="lv-dot" :class="`lv-${items[0].incident.level}`" />
        <span v-else class="nearby__calm"><Icon name="check" :size="14" :stroke="2.6" /></span>
        {{ items.length ? t.map.nearby : t.map.calm }}
      </span>
      <span class="nearby__meta">{{ items.length ? count : status }}</span>
    </button>

    <p v-if="!items.length" class="nearby__empty">{{ hasLocation ? t.map.calmBody : t.map.noLocation }}</p>
    <ul v-else class="nearby__list">
      <li v-for="{ incident, distance: meters } in shown" :key="incident.id">
        <button class="nearby__item" @click="emit('select', incident.id)">
          <span class="nearby__icon" :class="`lv-${incident.level}`"><Icon :name="categoryOf(incident.category).icon" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ incident.title }}</span>
            <span class="row__sub">{{ meters !== null ? distance(meters) : '' }}<template v-if="meters !== null"> · </template>{{ ago(incident.updatedAt) }}<template v-if="incident.sourceName"> · {{ incident.sourceName }}</template></span>
          </span>
          <span v-if="incident.confirmations > 1" class="nearby__count" :title="fill(t.incident.confirmations, { n: incident.confirmations })">{{ incident.confirmations }}</span>
        </button>
      </li>
    </ul>
    <p v-if="expanded && items.length" class="nearby__foot">{{ status }}</p>
  </section>
</template>

<script setup lang="ts">
import type { Incident } from '~/types/Incident';
import { categoryOf } from '~/utils/taxonomy';

const props = defineProps<{ items: Array<{ incident: Incident; distance: number | null }>; hasLocation: boolean; status: string }>();
const emit = defineEmits<{ select: [id: number] }>();
const expanded = defineModel<boolean>('expanded', { default: false });
const { t, fill, distance, ago } = useAppCopy();

const shown = computed(() => expanded.value ? props.items : props.items.slice(0, 2));
const visibleRows = computed(() => shown.value.length);
const count = computed(() => props.items.length === 1 ? t.value.map.countOne : fill(t.value.map.count, { n: props.items.length }));
</script>

<style scoped>
.nearby { position: absolute; z-index: 15; right: 10px; bottom: calc(var(--tabbar-h) + 10px); left: 10px; max-height: calc(100% - var(--safe-top) - var(--tabbar-h) - 120px); display: flex; flex-direction: column; overflow: hidden; border-radius: 24px; background: #fff; box-shadow: var(--vb-shadow-lg); transition: max-height .3s var(--vb-ease); }
.nearby__head { position: relative; display: flex; align-items: center; gap: 10px; width: 100%; min-height: 58px; padding: 14px 18px 10px; border: 0; background: none; text-align: left; flex: none; }
.nearby__grip { position: absolute; top: 6px; left: 50%; width: 32px; height: 4px; border-radius: 9px; background: #dcdfe5; transform: translateX(-50%); }
.nearby__title { display: flex; flex: 1; align-items: center; gap: 10px; font-size: 17px; font-weight: 660; letter-spacing: -.02em; }
.nearby__calm { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; color: #fff; background: var(--vb-ok); }
.nearby__meta { color: var(--vb-muted); font-size: 13px; font-weight: 520; }
.nearby__empty { margin: -4px 18px 16px 48px; color: var(--vb-muted); font-size: 14px; line-height: 1.4; }
.nearby__list { margin: 0; padding: 0 8px 8px; overflow-y: auto; list-style: none; overscroll-behavior: contain; }
.nearby__item { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 60px; padding: 8px 10px; border: 0; border-radius: 16px; background: none; text-align: left; }
.nearby__item:active { background: var(--vb-paper); }
.nearby__icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 13px; color: var(--lv); background: var(--lv-soft); flex: none; }
.nearby__count { display: grid; place-items: center; min-width: 26px; height: 26px; padding: 0 7px; border-radius: 99px; color: var(--vb-ink-3); background: var(--vb-paper); font-family: var(--vb-mono); font-size: 12px; font-weight: 600; }
.nearby__foot { margin: 0; padding: 0 18px 14px; color: var(--vb-subtle); font-size: 12px; }
</style>
