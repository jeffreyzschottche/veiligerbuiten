import { appCopy, fill } from '~/locales/app';

/** De app-teksten in de taal van het account, plus kleine weergavehulpjes. */
export function useAppCopy() {
  const { locale } = useLocale();
  const t = computed(() => appCopy[locale.value]);

  function distance(meters: number) {
    if (meters < 1000) return `${Math.max(10, Math.round(meters / 10) * 10)} m`;
    const km = meters < 10_000 ? (meters / 1000).toFixed(1) : String(Math.round(meters / 1000));
    return `${locale.value === 'nl' ? km.replace('.', ',') : km} km`;
  }

  function ago(iso: string | null) {
    if (!iso) return '';
    const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60_000));
    if (minutes < 2) return t.value.time.now;
    if (minutes < 60) return fill(t.value.time.minutes, { n: minutes });
    if (minutes < 60 * 24) return fill(t.value.time.hours, { n: Math.round(minutes / 60) });
    return fill(t.value.time.days, { n: Math.round(minutes / 1440) });
  }

  return { t, locale, fill, distance, ago };
}
