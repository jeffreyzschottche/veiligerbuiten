<template>
  <section class="nearby" :class="{ 'is-open': expanded, 'is-danger': topLevel === 'danger' }" :style="{ '--rows': visibleRows }">
    <button class="nearby__head" :aria-expanded="expanded" @click="expanded = !expanded">
      <span class="nearby__grip" />
      <span class="nearby__title">
        <span v-if="items[0]" class="lv-dot" :class="`lv-${items[0].incident.level}`" />
        <span v-else class="nearby__calm" :class="{ 'nearby__calm--cached': cached }"><Icon :name="cached ? 'info' : 'check'" :size="14" :stroke="2.6" /></span>
        {{ items.length ? (topLevel === 'danger' ? t.levels.danger : t.map.nearby) : cached ? t.map.saved : t.map.calm }}
      </span>
      <span class="nearby__meta">{{ items.length ? count : status }}</span>
    </button>

    <p v-if="!items.length" class="nearby__empty">{{ !hasLocation ? t.map.noLocation : cached ? t.map.cachedEmpty : t.map.calmBody }}</p>
    <ul v-else class="nearby__list">
      <li v-for="{ incident, distance: meters } in shown" :key="incident.id">
        <button class="nearby__item" :class="{ 'is-fading': (incident.freshness ?? 1) < 0.35 }" @click="emit('select', incident.id)">
          <span class="nearby__icon" :class="`lv-${incident.level}`"><Icon :name="categoryOf(incident.category).icon" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ incident.title }}</span>
            <span class="row__sub">{{ meters !== null ? distance(meters) : '' }}<template v-if="meters !== null"> · </template>{{ ago(incident.updatedAt ?? null) }}</span>
          </span>
          <span v-if="incident.confirmations > 1" class="nearby__count" :title="fill(t.incident.confirmations, { n: incident.confirmations })">{{ incident.confirmations }}</span>
        </button>
      </li>
    </ul>

    <!-- Bekende plekken staan los van wat nu speelt: rustiger vormgegeven, nooit bovenaan. -->
    <ul v-if="hotspots.length && (expanded || !items.length)" class="nearby__list nearby__list--spots" :aria-label="t.hotspots.label">
      <li v-for="{ hotspot, distance: meters } in hotspots" :key="hotspot.key">
        <button class="nearby__item nearby__item--spot" @click="emit('hotspot', hotspot.key)">
          <span class="nearby__icon nearby__icon--spot"><Icon :name="hotspot.kind === 'stats' ? 'users' : categoryOf(hotspot.category).icon" :size="17" /></span>
          <span v-if="hotspot.kind === 'stats'" class="row__main">
            <span class="row__title">{{ t.hotspots.stats.title }}</span>
            <span class="row__sub">{{ distance(meters) }} · {{ hotspot.name }} · {{ fill(t.hotspots.stats.row, { n: hotspot.count }) }}</span>
          </span>
          <span v-else class="row__main">
            <span class="row__title">{{ t.hotspots.titles[hotspot.category] ?? t.hotspots.label }}</span>
            <span class="row__sub">{{ distance(meters) }} · {{ fill(t.hotspots.row, { n: hotspot.count, days: windowDays }) }}</span>
          </span>
        </button>
      </li>
    </ul>
    <p v-if="expanded && items.length" class="nearby__foot">{{ status }}</p>
  </section>
</template>

<script setup lang="ts">
import type { Hotspot, Incident } from '~/types/Incident';
import { categoryOf } from '~/utils/taxonomy';

const props = withDefaults(defineProps<{
  items: Array<{ incident: Incident; distance: number | null }>;
  hotspots?: Array<{ hotspot: Hotspot; distance: number }>;
  hasLocation: boolean;
  status: string;
  cached?: boolean;
}>(), { hotspots: () => [] });
const emit = defineEmits<{ select: [id: number]; hotspot: [key: string] }>();
const expanded = defineModel<boolean>('expanded', { default: false });
const { t, fill, distance, ago } = useAppCopy();

/** Venster waarover de server bekende plekken berekent (config veiligbuiten.hotspots.window_days). */
const windowDays = 30;
// Ingeklapt: hoogstens twee regels (Hick/Miller: kort en overzichtelijk). Uitgeklapt: alles.
const shown = computed(() => expanded.value ? props.items : props.items.slice(0, 2));
const visibleRows = computed(() => shown.value.length);
const topLevel = computed(() => props.items[0]?.incident.level ?? null);
const count = computed(() => props.items.length === 1 ? t.value.map.countOne : fill(t.value.map.count, { n: props.items.length }));
</script>

<style scoped>
.nearby { position: absolute; z-index: 15; right: 10px; bottom: calc(var(--tabbar-h) + 10px + var(--ad-h, 0px)); left: 10px; max-height: calc(100% - var(--safe-top) - var(--tabbar-h) - var(--ad-h, 0px) - 120px); display: flex; flex-direction: column; overflow: hidden; border-radius: 24px; background: #fff; box-shadow: var(--vb-shadow-lg); transition: max-height .3s var(--vb-ease), bottom .3s var(--vb-ease); }
/* Von Restorff: gevaar valt op door een dunne rode rand, niet door een schreeuwerig vlak. */
.nearby.is-danger { box-shadow: var(--vb-shadow-lg), inset 0 3px 0 var(--vb-danger); }
.nearby.is-danger .nearby__title { color: var(--vb-danger); }
.nearby__head { position: relative; display: flex; align-items: center; gap: 10px; width: 100%; min-height: 58px; padding: 14px 18px 10px; border: 0; background: none; text-align: left; flex: none; }
.nearby__grip { position: absolute; top: 6px; left: 50%; width: 32px; height: 4px; border-radius: 9px; background: #dcdfe5; transform: translateX(-50%); }
.nearby__title { display: flex; flex: 1; align-items: center; gap: 10px; font-size: 17px; font-weight: 660; letter-spacing: -.02em; }
.nearby__calm { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; color: #fff; background: var(--vb-ok); }
.nearby__calm--cached { background: var(--vb-info); }
.nearby__meta { color: var(--vb-muted); font-size: 13px; font-weight: 520; }
.nearby__empty { margin: -4px 18px 16px 48px; color: var(--vb-muted); font-size: 14px; line-height: 1.4; }
.nearby__list { margin: 0; padding: 0 8px 8px; overflow-y: auto; list-style: none; overscroll-behavior: contain; }
.nearby__list--spots { flex: none; margin-top: -4px; padding-top: 6px; box-shadow: inset 0 1px 0 var(--vb-line-2); }
.nearby__item { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 60px; padding: 8px 10px; border: 0; border-radius: 16px; background: none; text-align: left; transition: opacity .2s; }
.nearby__item:active { background: var(--vb-paper); }
/* Oud nieuws: nog zichtbaar, maar trekt minder aandacht. */
.nearby__item.is-fading { opacity: .62; }
.nearby__item--spot { min-height: 52px; }
.nearby__item--spot .row__title { font-size: 15px; font-weight: 540; }
.nearby__icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 13px; color: var(--lv); background: var(--lv-soft); flex: none; }
.nearby__icon--spot { width: 36px; height: 36px; margin: 0 2px; border: 1.5px dashed #c9ced8; border-radius: 50%; color: var(--vb-ink-3); background: transparent; }
.nearby__count { display: grid; place-items: center; min-width: 26px; height: 26px; padding: 0 7px; border-radius: 99px; color: var(--vb-ink-3); background: var(--vb-paper); font-family: var(--vb-mono); font-size: 12px; font-weight: 600; }
.nearby__foot { margin: 0; padding: 0 18px 14px; color: var(--vb-subtle); font-size: 12px; }
</style>
