import type { IncidentCategory } from '~/types/Incident';
import type { Locale } from '~/composables/useLocale';
import type { IconName } from '~/utils/icons';

/**
 * Categorieën en subtypes voor melden en filteren. De Nederlandse naam is de
 * waarde die de API opslaat; de Engelse naam is alleen weergave.
 */
export const categories: Array<{ value: IncidentCategory; icon: IconName; nl: string; en: string }> = [
  { value: 'brand', icon: 'fire', nl: 'Brand of rook', en: 'Fire or smoke' },
  { value: 'geweld', icon: 'shield-alert', nl: 'Geweld', en: 'Violence' },
  // Hoog in de lijst: snel te vinden als iemand je lastigvalt.
  { value: 'intimidatie', icon: 'hand', nl: 'Intimidatie', en: 'Harassment' },
  { value: 'onveilig', icon: 'alert-triangle', nl: 'Onveilig', en: 'Unsafe' },
  { value: 'verkeer', icon: 'car', nl: 'Verkeer', en: 'Traffic' },
  { value: 'blokkade', icon: 'construction', nl: 'Afsluiting', en: 'Blocked' },
  { value: 'drukte', icon: 'users', nl: 'Drukte', en: 'Crowds' },
  { value: 'weer', icon: 'cloud-rain', nl: 'Weer en water', en: 'Weather' },
  { value: 'stoffen', icon: 'flask', nl: 'Gas of stoffen', en: 'Gas or fumes' },
  { value: 'dier', icon: 'paw', nl: 'Dier', en: 'Animal' },
  { value: 'anders', icon: 'circle-help', nl: 'Anders', en: 'Other' },
];

/** [Nederlands (opgeslagen waarde), Engels]. De eerste vijf zijn de meest gemelde. */
export const subtypes: Record<IncidentCategory, Array<[string, string]>> = {
  brand: [['Brand', 'Fire'], ['Rookontwikkeling', 'Smoke'], ['Brandend voertuig', 'Vehicle on fire'], ['Brandend afval / container', 'Waste or container fire'], ['Gaslucht / mogelijk gaslek', 'Gas smell / possible leak'], ['Explosie', 'Explosion'], ['Natuurbrand', 'Wildfire']],
  geweld: [['Vechtpartij', 'Fight'], ['Agressief persoon', 'Aggressive person'], ['Bedreiging', 'Threat'], ['Beroving / straatroof', 'Robbery / mugging'], ['Steekincident', 'Stabbing'], ['Mogelijk wapen', 'Possible weapon'], ['Schoten / mogelijk vuurwapen', 'Shots / possible firearm'], ['Agressieve groep', 'Aggressive group'], ['Rellen / ernstige onrust', 'Riots / serious unrest']],
  intimidatie: [['Handtastelijke persoon', 'Groping / unwanted touching'], ['Nafluiten / naroepen', 'Catcalling'], ['Seksuele opmerkingen / verbale intimidatie', 'Sexual comments / verbal harassment'], ['Iemand volgt mij', 'Someone is following me'], ['Iemand wordt lastiggevallen', 'Someone else is being harassed'], ['Blijft opdringerig aanspreken', 'Keeps approaching me'], ['Potloodventer / exhibitionist', 'Indecent exposure'], ['Ongewenst gefilmd of gefotografeerd', 'Filmed or photographed without consent'], ['Intimiderende groep', 'Intimidating group'], ['Voelt onveilig: donker of verlaten', 'Feels unsafe: dark or deserted'], ['Anders: intimidatie', 'Other: harassment']],
  // Lastigvallen staat nu onder 'Intimidatie'; hier niet dubbel (minder keuzes, sneller melden).
  onveilig: [['Verdachte situatie', 'Suspicious situation'], ['Verdacht object', 'Suspicious object'], ['Zorgwekkend / onvoorspelbaar gedrag', 'Worrying / unpredictable behaviour'], ['Achtervolging', 'Pursuit'], ['Gevaarlijke situatie op straat', 'Dangerous situation in the street']],
  verkeer: [['Verkeersongeval', 'Traffic accident'], ['Fiets- / scooterongeval', 'Bicycle / scooter accident'], ['Gevaarlijk rijgedrag', 'Dangerous driving'], ['Object op de rijbaan', 'Object on the road'], ['Spookrijder', 'Wrong-way driver'], ['Voetganger betrokken bij ongeval', 'Pedestrian involved in accident'], ['Meerdere voertuigen betrokken', 'Multiple vehicles involved'], ['Verkeerslicht defect / gevaarlijke verkeerssituatie', 'Broken traffic light']],
  blokkade: [['Weg geblokkeerd', 'Road blocked'], ['Fietspad geblokkeerd', 'Cycle path blocked'], ['Politieafzetting', 'Police cordon'], ['Boom op de weg', 'Tree on the road'], ['Gat / verzakking', 'Hole / subsidence'], ['Vallend / loshangend materiaal', 'Falling / loose material'], ['Instortingsgevaar', 'Risk of collapse'], ['Kapotte of gevaarlijke infrastructuur', 'Broken or dangerous infrastructure']],
  drukte: [['Grote menigte', 'Large crowd'], ['Demonstratie', 'Demonstration'], ['Extreme drukte', 'Extreme crowding'], ['Confrontatie tussen groepen', 'Clash between groups'], ['Supportersgroep', 'Group of fans'], ['Route geblokkeerd door menigte', 'Route blocked by crowd'], ['Rellen', 'Riots']],
  weer: [['Wateroverlast', 'Flooding'], ['Omgevallen boom', 'Fallen tree'], ['Zeer glad', 'Very slippery'], ['Storm / extreme wind', 'Storm / extreme wind'], ['Straat onder water', 'Street flooded'], ['Zwaar onweer', 'Severe thunderstorm'], ['Hagel / extreme regen', 'Hail / extreme rain'], ['Loshangende takken', 'Loose branches']],
  stoffen: [['Gaslucht', 'Gas smell'], ['Chemische lucht / geur', 'Chemical smell'], ['Grote rookwolk', 'Large smoke cloud'], ['Oliespoor / gevaarlijke vloeistof', 'Oil spill / hazardous liquid'], ['Gevaarlijke stof', 'Hazardous substance'], ['Mogelijke lekkage', 'Possible leak']],
  dier: [['Loslopend gevaarlijk dier', 'Dangerous animal on the loose'], ['Agressief dier', 'Aggressive animal'], ['Groot dier op de weg', 'Large animal on the road'], ['Ontsnapt vee', 'Escaped livestock']],
  anders: [['Andere gevaarlijke situatie', 'Other dangerous situation']],
};

export function categoryOf(value: IncidentCategory) {
  return categories.find((category) => category.value === value) ?? categories.find((category) => category.value === 'anders')!;
}

export function categoryLabel(value: IncidentCategory, locale: Locale) {
  return categoryOf(value)[locale];
}
