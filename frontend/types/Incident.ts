export type IncidentCategory =
  | 'brand'
  | 'geweld'
  | 'verkeer'
  | 'blokkade'
  | 'weer'
  | 'stoffen'
  | 'drukte'
  | 'onveilig'
  | 'dier'
  | 'anders';

export type IncidentStatus = 'gemeld' | 'bevestigd' | 'officieel' | 'opgelost' | 'verlopen';

export interface IncidentGeometry {
  type: 'Point' | 'LineString' | 'Polygon' | 'Circle';
  coordinates: unknown;
  radius_m?: number;
}

export interface Incident {
  id: number;
  title: string;
  subtype?: string;
  category: IncidentCategory;
  status: IncidentStatus;
  distance: string;
  reportedAt: string;
  description: string;
  advice: string;
  confirmations: number;
  resolvedReports?: number;
  source?: 'user' | 'official' | 'seed';
  sourceName?: string;
  severity?: number;
  confidence?: number;
  geometry?: IncidentGeometry | null;
  latitude?: number;
  longitude?: number;
  left: number;
  top: number;
}
