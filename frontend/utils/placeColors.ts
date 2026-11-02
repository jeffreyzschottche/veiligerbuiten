/**
 * Kleuren voor je plekken op de kaart. Moet gelijk lopen met config veiligbuiten.places.colors.
 * Geen rood, oranje of blauw: die betekenen op de kaart gevaar, opletten en jij.
 */
export const PLACE_COLORS = {
  ink: '#0b0d12',
  violet: '#7048e8',
  pink: '#d6336c',
  teal: '#0c8599',
  green: '#2b8a3e',
  brown: '#8d5b2f',
} as const;

export type PlaceColor = keyof typeof PLACE_COLORS;

export function placeColor(color: string | null | undefined): string {
  return PLACE_COLORS[(color ?? 'ink') as PlaceColor] ?? PLACE_COLORS.ink;
}
