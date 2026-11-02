# Bronnen en licenties

Gecontroleerd op 24 oktober 2026; de P2000-bronnen opnieuw op 2 november 2026. Het gaat om commercieel gebruik: een betaalde app (€ 0,99 per maand) met advertenties. Dit is een inventarisatie van de voorwaarden die de bronnen zelf publiceren, **geen juridisch advies**. Laat bij twijfel een jurist meekijken, zeker vóór de lancering.

| Bron | Wat | Commercieel gebruik | Status | Actie |
|---|---|---|---|---|
| **Politie Open Data API** (`api.politie.nl/v4/nieuws`) | Politieberichten met exacte coördinaten | Het overheidsregister vermeldt voor de Politie-API's CC-0, zonder verplichte naamsvermelding. Voorwaarden: wek niet de indruk dat de politie je product steunt, gebruik geen politiehuisstijl en geen politiefoto's voor promotie. | 🟢 Toegestaan | Geen politielogo of politiefoto's in de app of in marketing. We tonen "Politie" als bron, met een link naar het bericht. |
| **NDW** (`opendata.ndw.nu`, SRTI) | Ongevallen, spookrijders, objecten op de weg | Open overheidsdata, vrij beschikbaar. | 🟢 Toegestaan | Controleer de licentie op de datasetpagina bij NDW/data.overheid.nl en noem NDW als bron (dat doen we al). |
| **KNMI** (aardbevingen via FDSN; waarschuwingen via het dataplatform) | Aardbevingen, weerwaarschuwingen | KNMI open data: CC BY 4.0. | 🟢 Toegestaan, met naamsvermelding | "KNMI" staat als bron bij elke melding. |
| **Luchtmeetnet** (staat uit) | Luchtkwaliteit | Open overheidsdata. | 🟢 | Bron noemen als je hem aanzet. |
| **NL-Alert via public-warning.app** | Officiële NL-Alerts met gebied | De **tekst** van een NL-Alert is overheidsinformatie (Auteurswet art. 11/15b: vrij herbruikbaar). De **feed** komt van een derde partij (public-warning.app). Hun voorwaarden waren niet te lezen: het certificaat van de site klopte niet. Op 2 nov. nog steeds zo (public-warning.app geeft een certificaatfout; de API op api.public-warning.app werkt wel). | 🟠 Onduidelijk, nog niet gevraagd | Vraag public-warning.app om toestemming voor commercieel gebruik, of zoek een officiële NL-Alert-feed. Uitzetten kan met `NL_ALERT_ENABLED=false`. Technisch werkt het: op 2 nov. getest met een echte alert (Farmsum) die als 'gevaar' met het hele gebied binnenkwam. De laatste NL-Alert in de feed was van 25 okt., vóór productie live ging; daarom staan er nog geen NL-Alerts in de database. |
| **P2000 via Zwaailicht.nl** (`zwaailicht.nl/feed/meldingen.xml`) | Alarmeringen van brandweer, ambulance en politie | Hun voorwaarden (zwaailicht.nl/voorwaarden) sluiten commercieel hergebruik zonder afspraak uit. **De eigenaar gaf in november 2026 per mail toestemming** voor VeiligerBuiten, op voorwaarde van een **zichtbare link naar zwaailicht.nl** (niet alleen de naam) en waar het kan **per melding een link naar de bijbehorende pagina** op Zwaailicht. Hij vroeg ook om conditionele verzoeken (If-None-Match/If-Modified-Since). Technisch: hoogstens 1× per minuut, met een herkenbare User-Agent. | 🟢 Toegestaan, per mail afgesproken | Bewaar de mail. Per melding staat "Via Zwaailicht.nl" met link naar de pagina van die melding (`source_url`). Zwaailicht.nl staat met link op de homepage en in de bronnenlijst van de storetekst. We halen op met ETag/Last-Modified en hoogstens 1× per 6 minuten. Uitzetten kan met `ZWAAILICHT_ENABLED=false`. |
| **P2000 via Sirenes** (`sirenes.app/rss.xml`) | Landelijke alarmeringen met straat en plaats | Geen gebruiks- of hergebruikvoorwaarden gepubliceerd; de feed bevat geen licentie. Ze bieden hun RSS-feeds openlijk aan (sirenes.app/rss) en vragen op sirenes.app/voor-media om bronvermelding: *"Bron: Sirenes (sirenes.app), geraadpleegd op [datum], [directe URL]"*. Over commercieel gebruik zeggen ze niets. robots.txt sluit alleen `/api/` en `/admin` uit. Een project van orca.fo; contact: hi@sirenes.app. | 🟠 Waarschijnlijk akkoord, nog niet gevraagd | Bron en link staan per item. **Mail hi@sirenes.app** (concept hieronder) en bewaar het antwoord. Zegt Sirenes nee: `SIRENES_ENABLED=false`. |
| ~~**P2000 via Alarmeringen.nl**~~ (`alarmeringen.nl/feeds/all.rss`) | Landelijke alarmeringen | Van **DPG Media**; voor commercieel hergebruik is waarschijnlijk een licentie nodig. | ⚪ **Verwijderd op 2 nov. 2026** | Niet gevraagd: de verwachting was dat DPG geen toestemming geeft. Zwaailicht en Sirenes dekken heel Nederland. |
| ~~**P2000-monitor**~~ (p2000zhz-rr.nl, via FeedBurner) | Ruwe pagerteksten van Rotterdam-Rijnmond, Zuid-Holland Zuid, Lifeliners en KNRM | Hobbysite zonder voorwaarden of licentie. | ⚪ **Verwijderd op 2 nov. 2026** | Voegde weinig toe: een paar meldingen per uur uit regio's die Zwaailicht en Sirenes al dekken, ruwe pagerteksten en geen link per melding. Eén toestemming minder nodig. |
| **Politiecijfers per buurt** (CBS-tabel 47022NED, maandcijfers) | Geregistreerde straatroof, openlijk geweld, bedreiging en mishandeling per buurt, de laatste 12 maanden. Met de buurtoppervlakte uit de CBS Kerncijfers wijken en buurten. | data.overheid.nl: **CC BY 4.0**, eigenaar Politie. Commercieel gebruik toegestaan, met naamsvermelding. | 🟢 Toegestaan, met naamsvermelding | "Bron: Politie (CBS)" staat bij elke buurt in de app en in de voorwaarden. Het blijft statistiek: nooit een melding of pushbericht, en de tekst gaat over de buurt, niet over de bewoners. |
| **PDOK Locatieserver** (overheid) | Straat, postcode of buurtcode omzetten naar een plek op de kaart | Open overheidsdata, commercieel gebruik toegestaan. | 🟢 | Nooit op huisnummer; resultaten 30 dagen in cache. |
| **OpenFreeMap / OpenStreetMap** | Kaarttegels | OSM-data onder ODbL: commercieel gebruik toegestaan, mits naamsvermelding. | 🟢 | De naamsvermelding staat op de kaart (MapLibre-attributie). |
| **Google AdMob** | Advertenties | Toegestaan. AVG/ePrivacy vragen wel om toestemming. | 🟢 met toestemming | Google UMP-toestemming is ingebouwd. Stel een GDPR-bericht in de AdMob-console in. Privacyverklaring is bijgewerkt. |

## Uitknoppen

Elke bron die we niet als open data gebruiken (op basis van toestemming, of met een nog onduidelijke licentie) krijgt een uitknop in `config/veiligbuiten.php` → `source_switches`, nu Zwaailicht, Sirenes en NL-Alert. Op `false` in de .env is de bron helemaal uit: niet meer ophalen, bestaande meldingen binnen een minuut gearchiveerd (ook van telefoons en uit de pushwachtrij), en 'uitgeschakeld' in de bronnenlijst van de app. Open overheidsdata (Politie, NDW, KNMI, CBS) heeft geen uitknop nodig. **Een nieuwe bron zonder open licentie krijgt er altijd een.**

## Toestemming per bron (stand 2 november 2026)

| Bron | Status | Wat nog moet |
|---|---|---|
| Zwaailicht.nl | ✅ Toestemming per mail van de eigenaar | Mail bewaren. Zichtbare link naar zwaailicht.nl en per melding een link naar de pagina; hoogstens 1× per 6 minuten. |
| Sirenes | 🟠 Gemaild op 2 nov., wacht op antwoord | Bij nee: `SIRENES_ENABLED=false`. |

Hoe je erachter komt of je een bron mag gebruiken, altijd in deze volgorde:
1. **Voorwaarden van de site lezen**: zoek naar hergebruik, commercieel gebruik of een licentie.
2. **In de feed zelf kijken** naar een licentie- of copyrightregel, en in robots.txt.
3. **Staat er niets duidelijk: per mail vragen en het antwoord bewaren.** Dat is de enige zekerheid.

### Concept: Sirenes (hi@sirenes.app)

> Hoi,
>
> Ik ben Jeffrey van VeiligerBuiten (veiligerbuiten.nl), een app die openbare meldingen over veiligheid in de buurt op één kaart zet. De app kost € 0,99 per maand.
>
> We gebruiken jullie RSS-feed ([URL van de feed]) als een van onze P2000-bronnen. We halen hem hoogstens elke 2 minuten op, met een herkenbare User-Agent. Bij elke melding staat "Via [naam]" met een link naar jullie pagina, en [naam] staat in onze bronnenlijst in de app en op de website.
>
> Is dat gebruik akkoord voor jullie? Hebben jullie wensen voor de bronvermelding of het ophaaltempo, dan pas ik het graag aan.
>
> Groet,
> Jeffrey

## Zo gaan we om met de P2000-bronnen

Er zijn nu twee P2000-bronnen: Zwaailicht en Sirenes. Dat geeft:
- **meer dekking en zekerheid**: hetzelfde voorval uit meerdere bronnen wordt één melding met meerdere observaties;
- **minder afhankelijkheid**: valt één bron weg, dan blijft P2000 werken.

Om het netjes te houden:
- Bij elk item staan de bron en een link.
- Ophalen gebeurt met ETag/Last-Modified en hoogstens eens per 2 minuten.
- **Ambulanceritten tonen nooit een straat of huisnummer, uit geen enkele bron.** We tonen alleen de plaats, afgerond op ongeveer 300 m.
- Een melding met alleen een plaatsnaam krijgt geen eigen stip; die telt alleen als bevestiging van een melding die er al is.

Het P2000-signaal zelf is openbaar. De bronnen hebben wel rechten op hun eigen teksten en indeling. De keuze om ze te gebruiken is bewust gemaakt. Om het risico te verkleinen: vraag beide partijen om toestemming, of neem met een eigen P2000-ontvanger de bron zelf in handen.

## Niet toegevoegd, en waarom

- **Nieuws-RSS van NOS en regionale omroepen.** Deze feeds hebben geen licentie voor commercieel hergebruik. Artikeltekst valt onder het auteursrecht, en de omroepen hebben hun eigen voorwaarden. Het juridisch veilige alternatief voor "nieuws over gevaar" zijn de **Politieberichten**: officieel, met coördinaten en vrij herbruikbaar. Die staan nu standaard aan.
- **Energiestoringen (Liander/Stedin/Enexis).** Er is geen gedocumenteerde publieke API.
- **Weer en water (MeteoAlarm, RWS Waterinfo).** Die werken technisch, maar de focus ligt op direct gevaar door mensen en incidenten.

## Eigen P2000-ontvanger (optie)

Zwaailicht schrijft zelf dat de P2000-alarmering een openbaar, onversleuteld signaal is waarop zij geen rechten claimen. Hun rechten zitten op hun eigen titels, indeling en geocodering. Met een eigen ontvanger (SDR-stick plus decoder) ben je dus niet van hun voorwaarden afhankelijk. Laat wel eerst juridisch toetsen of het herpubliceren van ontvangen pagerberichten in een betaalde app is toegestaan; dat valt buiten deze inventarisatie. Let ook op privacy: pagerteksten kunnen adressen en medische details bevatten. Ambulanceritten met straatnaam staan nu als 'goed om te weten' in de app. Ze zijn geen gevaar voor omstanders; overweeg ze helemaal over te slaan.

## Ook gezien

De landingspagina noemde eerst "meldingen uit 30 bronnen". Dat is aangepast naar het aantal dat echt draait: nu 7, met de buurtcijfers erbij (na het verwijderen van de P2000-monitor en Alarmeringen.nl op 2 nov. 2026).
