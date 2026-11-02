<template>
  <section class="screen">
    <h1 class="screen__title">{{ t.sources.title }}</h1>
    <p class="screen__intro">{{ t.sources.intro }}</p>

    <div v-for="group in groups" :key="group.id" class="group">
      <p class="group__label">{{ t.sources.groups[group.id] }}</p>
      <div class="card">
        <a v-for="item in group.items" :key="item.name" class="row source" :href="item.url" target="_blank" rel="noopener noreferrer" @click.prevent="open(item.url)">
          <span class="row__main">
            <span class="row__title">
              {{ item.name }}
              <span v-if="item.key && health[item.key]" class="source__state" :class="`is-${health[item.key]}`">{{ health[item.key] === 'live' ? t.sources.live : t.sources.delayed }}</span>
            </span>
            <span class="row__sub">{{ item.what }}</span>
          </span>
          <span class="source__link" aria-hidden="true"><Icon name="external" :size="16" /></span>
        </a>
      </div>
    </div>

    <p class="source__disclaimer">{{ t.sources.disclaimer }}</p>

    <div class="group">
      <p class="group__help source__ask">{{ t.sources.requestIntro }}</p>
      <button class="btn btn--ink btn--block" @click="requesting = true"><Icon name="plus" :size="18" />{{ t.sources.request }}</button>
    </div>

    <SourceRequestSheet :open="requesting" @close="requesting = false" />
  </section>
</template>

<script setup lang="ts">
import type { SourceItem } from '~/locales/app';

type Health = 'live' | 'delayed' | 'off';
interface SourceStatus { source: string; enabled: boolean; state: 'healthy' | 'stale' | 'degraded' | 'disabled' }

const { t } = useAppCopy();
const requesting = ref(false);
/** Live status per bron (uit /sources/status). Onbekend (offline) = geen label, maar de bron blijft staan. */
const health = ref<Record<string, Health>>({});

const groups = computed(() => (['alerts', 'background'] as const).map((id) => ({
  id,
  // Een bron die op de server uit staat, tonen we niet als bron.
  items: t.value.sources.items.filter((item: SourceItem) => item.group === id && !(item.key && health.value[item.key] === 'off')),
})));

onMounted(async () => {
  try {
    const { data } = await useApi().get<{ data: SourceStatus[] }>('/sources/status');
    health.value = Object.fromEntries(data.map((status) => [
      status.source,
      !status.enabled || status.state === 'disabled' ? 'off' : status.state === 'healthy' ? 'live' : 'delayed',
    ]));
  } catch { /* offline: lijst zonder status */ }
});

function open(url: string) {
  window.open(url, '_system');
}
</script>

<style scoped>
.source { color: inherit; text-decoration: none; }
.source .row__title { display: flex; align-items: center; gap: 8px; }
.source__state { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 560; }
.source__state::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.source__state.is-live { color: var(--vb-ok); }
.source__state.is-delayed { color: var(--vb-warn); }
.source__link { margin-left: auto; color: var(--vb-subtle); flex: none; }
.source__disclaimer { margin: -6px 4px 24px; color: var(--vb-subtle); font-size: 13px; line-height: 1.45; }
.source__ask { margin: 0 4px 10px; }
</style>
