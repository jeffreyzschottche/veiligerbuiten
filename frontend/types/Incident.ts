export type IncidentCategory =
  | 'brand'
  | 'geweld'
  | 'intimidatie'
  | 'verkeer'
  | 'blokkade'
  | 'weer'
  | 'stoffen'
  | 'drukte'
  | 'onveilig'
  | 'dier'
  | 'anders';

export type IncidentStatus = 'gemeld' | 'bevestigd' | 'officieel' | 'opgelost' | 'verlopen';

/** Hoe zwaar iets weegt: bepaalt kleur, geluid en bereik van een melding. */
export type DangerLevel = 'info' | 'warn' | 'danger';

export interface IncidentGeometry {
  type: 'Point' | 'LineString' | 'Polygon' | 'Circle';
  coordinates: unknown;
  radius_m?: number;
}

/** Zoals App\Support\IncidentPresenter het teruggeeft. */
export interface Incident {
  id: number;
  title: string;
  category: IncidentCategory;
  status: IncidentStatus;
  level: DangerLevel;
  severity: number;
  confirmations: number;
  resolvedReports?: number;
  weight?: number;
  /** 1 tot 2: de stip groeit met bevestigingen, maar bescheiden. */
  dotScale: number;
  /** Meters: tot waar mensen een melding krijgen. */
  alertRadius: number;
  /** 0–1: hoe zeker het klopt (bevestigingen, twijfel, bron). */
  confidence?: number;
  /** 0–1: 1 = net gezien; halveert per halfwaardetijd van de categorie. */
  freshness?: number;
  /** Aantal andere ernstige signalen vlakbij. */
  cluster?: number;
  official: boolean;
  source: string;
  sourceName?: string | null;
  /** Link naar het oorspronkelijke bericht; de bronvermelding is verplicht (o.a. Zwaailicht). */
  sourceUrl?: string | null;
  description?: string | null;
  geometry?: IncidentGeometry | null;
  latitude: number;
  longitude: number;
  distance?: number | null;
  createdAt?: string | null;
  updatedAt?: string | null;
  expiresAt?: string | null;
}

/** Plek waar de afgelopen weken herhaaldelijk iets ernstigs gebeurde. */
export interface Hotspot {
  key: string;
  category: IncidentCategory;
  latitude: number;
  longitude: number;
  radius: number;
  count: number;
  days: number;
  lastAt: string;
}

export interface Place {
  id: number;
  name: string;
  label: string | null;
  latitude: number;
  longitude: number;
  radius_m: number;
  alerts_enabled: boolean;
}

export interface ReportQuota {
  limit: number;
  used: number;
  remaining: number;
  resets_at: string;
}
