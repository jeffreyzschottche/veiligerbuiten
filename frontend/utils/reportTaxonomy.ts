import type { IncidentCategory } from '~/types/Incident';

export const reportCategories = [
  { value: 'brand', label: 'Brand / rook', icon: 'fire' },
  { value: 'geweld', label: 'Geweld / agressie', icon: 'shield-alert' },
  { value: 'onveilig', label: 'Onveilige situatie', icon: 'alert-triangle' },
  { value: 'verkeer', label: 'Verkeer / ongeval', icon: 'car' },
  { value: 'blokkade', label: 'Blokkade / schade', icon: 'construction' },
  { value: 'drukte', label: 'Demonstratie / drukte', icon: 'users' },
  { value: 'weer', label: 'Weer / water', icon: 'cloud-rain' },
  { value: 'stoffen', label: 'Stoffen / lucht', icon: 'flask' },
  { value: 'dier', label: 'Dier', icon: 'paw' },
  { value: 'anders', label: 'Anders', icon: 'circle-help' },
] as const;

export const reportSubtypes: Record<IncidentCategory, string[]> = {
  brand: ['Brand', 'Rookontwikkeling', 'Explosie', 'Gaslucht / mogelijk gaslek', 'Natuurbrand', 'Brandend voertuig', 'Brandend afval / container', 'Anders: brand / rook'],
  geweld: ['Vechtpartij', 'Agressief persoon', 'Agressieve groep', 'Bedreiging', 'Beroving / straatroof', 'Mogelijk wapen', 'Schoten / mogelijk vuurwapen', 'Steekincident', 'Rellen / ernstige onrust', 'Anders: geweld / agressie'],
  onveilig: ['Zorgwekkend / onvoorspelbaar gedrag', 'Intimidatie', 'Straatintimidatie', 'Verdachte situatie', 'Verdacht object', 'Iemand wordt lastiggevallen', 'Achtervolging', 'Gevaarlijke situatie op straat', 'Anders: onveilige situatie'],
  verkeer: ['Verkeersongeval', 'Fiets- / scooterongeval', 'Voetganger betrokken bij ongeval', 'Meerdere voertuigen betrokken', 'Gevaarlijk rijgedrag', 'Voertuig op gevaarlijke plek', 'Spookrijder', 'Object op de rijbaan', 'Omgevallen voertuig', 'Verkeerslicht defect / gevaarlijke verkeerssituatie', 'Anders: verkeer / ongeval'],
  blokkade: ['Weg geblokkeerd', 'Fietspad geblokkeerd', 'Voetpad geblokkeerd', 'Politieafzetting', 'Hulpdiensten blokkeren gebied', 'Boom op de weg', 'Groot object blokkeert doorgang', 'Gat / verzakking', 'Instortingsgevaar', 'Vallend / loshangend materiaal', 'Kapotte of gevaarlijke infrastructuur', 'Anders: blokkade / schade'],
  drukte: ['Demonstratie', 'Protestmars', 'Grote menigte', 'Extreme drukte', 'Supportersgroep', 'Confrontatie tussen groepen', 'Rellen', 'Route geblokkeerd door menigte', 'Anders: demonstratie / drukte'],
  weer: ['Wateroverlast', 'Straat onder water', 'Overstroming / hoogwater', 'Zwaar onweer', 'Storm / extreme wind', 'Hagel / extreme regen', 'Sneeuw / ijzel', 'Zeer glad', 'Omgevallen boom', 'Loshangende takken', 'Ander extreem weer / water'],
  stoffen: ['Gaslucht', 'Chemische lucht / geur', 'Gevaarlijke stof', 'Grote rookwolk', 'Slechte lucht door incident', 'Mogelijke lekkage', 'Oliespoor / gevaarlijke vloeistof', 'Anders: stoffen / lucht'],
  dier: ['Agressief dier', 'Loslopend gevaarlijk dier', 'Groot dier op de weg', 'Ontsnapt vee', 'Gewond dier dat verkeersgevaar veroorzaakt', 'Anders: dier'],
  anders: ['Andere gevaarlijke situatie'],
};
