export type Locale = 'nl' | 'en';

/** Eén taalinstelling voor site, app en meldingen: NL of EN. */
export function useLocale() {
  const cookie = useCookie<Locale | null>('vb_locale', { default: () => null, sameSite: 'lax', maxAge: 60 * 60 * 24 * 365 });
  const locale = useState<Locale>('vb-locale', () => {
    if (cookie.value === 'nl' || cookie.value === 'en') return cookie.value;
    const header = import.meta.server
      ? useRequestHeaders(['accept-language'])['accept-language'] ?? ''
      : navigator.language;
    return !header || header.toLowerCase().startsWith('nl') ? 'nl' : 'en';
  });

  function setLocale(next: Locale) {
    locale.value = next;
    cookie.value = next;
  }

  return { locale, setLocale };
}
