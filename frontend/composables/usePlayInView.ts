import type { Ref } from 'vue';

export const playingKey = Symbol('vb-playing') as InjectionKey<Ref<boolean>>;

/**
 * Speelt mockup-animaties alleen af zolang ze in beeld zijn. Zonder beweging
 * (of vóór hydratie) blijft de eindstand staan, zodat er nooit een leeg scherm is.
 */
export function usePlayInView(target: Ref<HTMLElement | null>) {
  const playing = ref(false);
  let observer: IntersectionObserver | undefined;

  onMounted(() => {
    if (!target.value || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    observer = new IntersectionObserver(([entry]) => { playing.value = entry?.isIntersecting ?? false; }, { threshold: 0.3 });
    observer.observe(target.value);
  });
  onBeforeUnmount(() => observer?.disconnect());

  return playing;
}

/** Voor scènes binnen een <PhoneFrame>. */
export function useScenePlaying() {
  return inject(playingKey, ref(false));
}
