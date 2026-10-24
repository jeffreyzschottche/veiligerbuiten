# Sync, levenscyclus en gevaarlijke plekken

## Zuinige sync (`GET /api/v1/incidents/sync`)

1. **Raster.** De server legt het middelpunt op een raster van 0,05° (ongeveer 5 km). App en runner doen hetzelfde. Iedereen in dezelfde buurt vraagt dus precies hetzelfde gebied op, en de server ziet nooit een exacte positie.
2. **ETag.** De staat van een gebied is één goedkope query (`max(updated_at)` en het aantal), plus taal en bekende plekken. Stuurt de app `If-None-Match` met de vorige ETag mee en is er niets veranderd, dan volgt een **304 zonder body en zonder verdere queries**.
3. **Delta.** Met `since=<cursor>` (uit het vorige antwoord) krijgt de app alleen `changes` (nieuw of gewijzigd) en `removed` (id's die verdwenen). Een volledige lijst volgt als de cursor ouder is dan 6 uur, of als er zoveel veranderde dat een volledige lijst kleiner is.
4. **Gedeelde cache.** Een volledig antwoord wordt 10 minuten bewaard per gebied, taal en staat. De tweede telefoon in dezelfde buurt kost bijna niets.
5. **Compact en gzip.** Lege velden en velden die de app niet gebruikt gaan er niet in. Bij `Accept-Encoding: gzip` comprimeert de server zelf (vijf tot tien keer kleiner). Comprimeert nginx al, dan doet de middleware niets.
6. **Afremmen.** `min_sync_after` is een ondergrens die de server meegeeft. Zet `VB_SYNC_MIN_INTERVAL` hoger bij drukte, en alle telefoons syncen minder vaak. Op de voorgrond wijkt elke sessie ±10% af, zodat niet iedereen op hetzelfde moment komt.
7. **Veroudering zonder polling.** Elke 2 minuten legt `veiligbuiten:risk-clusters` per melding het niveau en het (afgeronde) bereik vast. Alleen als dat verandert, verandert `updated_at`. Zo ziet de delta dat een melding is verouderd of zwaarder is geworden.

## Levenscyclus (`IncidentLifecycle`, config `veiligbuiten.lifecycle`)

| Categorie | Begint met | Elke bevestiging | Verloopt na zoveel minuten zonder bevestiging | Hoogstens | Versheid halveert per |
|---|---|---|---|---|---|
| geweld | 90 min | +45 min | 90 | 6 uur | 45 min |
| onveilig | 120 | +60 | 120 | 8 uur | 60 |
| brand | 180 | +90 | 180 | 12 uur | 90 |
| blokkade | 360 | +180 | 480 | 48 uur | 240 |

(Zie de config voor alle categorieën.)

- Een **bevestiging** zet de verlooptijd op "nu + extra". Het verlengen telt niet eindeloos op en gaat nooit voorbij de bovengrens.
- Een **"is voorbij"-stem** halveert de resterende tijd. Drie van zulke stemmen maken een gebruikersmelding direct opgelost.
- **Officiële bronnen** met een eindtijd (NL-Alert, NDW, KNMI) volgen die eindtijd, met een vangnet van 24 uur. P2000 en politieberichten volgen het ritme van hun categorie. Een nieuw bericht over dezelfde plek houdt ze in leven.
- **P2000** wordt niet meer "opgelost" als een melding uit de feed verdwijnt. Die feed is een rollend venster van de laatste 50 meldingen (ongeveer 13 minuten), geen momentopname.
- **Opruimen.** Verlopen meldingen worden eerst gearchiveerd, zodat telefoons ze in de delta als `removed` zien. Na 45 dagen worden ze echt verwijderd, samen met observaties, stemmen en meldingen. Logregels van bronnen verdwijnen na 14 dagen, pushregistraties na 30 dagen.

## Gevaar en bereik (`DangerScale`)

- **Niveau** volgt de ernst en wordt dan bijgesteld:
  - één losse, onbevestigde melding van een gebruiker wordt nooit meteen "gevaar";
  - minstens zoveel "weg"-stemmen als bevestigingen: één niveau lager;
  - verouderd (versheid onder 0,35): één niveau lager;
  - minstens twee andere ernstige signalen binnen 500 m (van verschillende bronnen): één niveau hoger.
- Een gebruikersmelding met een **officieel signaal vlakbij** telt als bevestigd.
- **Bereik** hangt af van de categorie (gevaarlijke stoffen 2,5 km, vechtpartij 600 m), groeit logaritmisch met bevestigingen (hoogstens ×3) en krimpt tot 60% als een melding veroudert. Een gebiedswaarschuwing reikt altijd tot haar rand, ook als het middelpunt verder dan 30 km weg ligt.
- **Zekerheid** (0–1) en **versheid** gaan mee naar de app. De app toont ze als "Zeer waarschijnlijk / Waarschijnlijk / Nog onzeker" en tekent oudere stippen lichter.

## Gevaarlijke plekken (`HotspotDetector`, elke nacht)

Een plek telt als gevaarlijk als er in 30 dagen minstens 3 ernstige meldingen waren, verspreid over minstens 3 verschillende dagen, binnen ongeveer 250 tot 375 m van elkaar. Het gaat om verkeersongevallen, geweld en ernstige onveilige situaties (zoals bommeldingen en verdachte objecten). Alleen officiële meldingen of meldingen die door meerdere mensen zijn bevestigd tellen mee, zodat één drukke avond of één fanatieke melder geen plek maakt.

De app tekent zo'n plek als een gestippelde zone en toont hem onder "In je buurt". De achtergrondcontrole geeft er hoogstens één keer per week per plek een stil seintje over ("goed om te weten"), en nooit tijdens stille uren.
