# Bronnen en licenties

Gecontroleerd op 24 oktober 2026. Het gaat om commercieel gebruik: een betaalde app (€ 0,99 per maand) met advertenties. Dit is een inventarisatie van de voorwaarden die de bronnen zelf publiceren, **geen juridisch advies**. Laat bij twijfel een jurist meekijken, zeker vóór de lancering.

| Bron | Wat | Commercieel gebruik | Status | Actie |
|---|---|---|---|---|
| **Politie Open Data API** (`api.politie.nl/v4/nieuws`) | Politieberichten met exacte coördinaten | Het overheidsregister vermeldt voor de Politie-API's CC-0, zonder verplichte naamsvermelding. Voorwaarden: wek niet de indruk dat de politie je product steunt, gebruik geen politiehuisstijl en geen politiefoto's voor promotie. | 🟢 Toegestaan | Geen politielogo of politiefoto's in de app of in marketing. We tonen "Politie" als bron, met een link naar het bericht. |
| **NDW** (`opendata.ndw.nu`, SRTI) | Ongevallen, spookrijders, objecten op de weg | Open overheidsdata, vrij beschikbaar. | 🟢 Toegestaan | Controleer de licentie op de datasetpagina bij NDW/data.overheid.nl en noem NDW als bron (dat doen we al). |
| **KNMI** (aardbevingen via FDSN; waarschuwingen via het dataplatform) | Aardbevingen, weerwaarschuwingen | KNMI open data: CC BY 4.0. | 🟢 Toegestaan, met naamsvermelding | "KNMI" staat als bron bij elke melding. |
| **Luchtmeetnet** (staat uit) | Luchtkwaliteit | Open overheidsdata. | 🟢 | Bron noemen als je hem aanzet. |
| **NL-Alert via public-warning.app** | Officiële NL-Alerts met gebied | De **tekst** van een NL-Alert is overheidsinformatie (Auteurswet art. 11/15b: vrij herbruikbaar). De **feed** komt van een derde partij (public-warning.app). Hun voorwaarden waren niet te lezen: het certificaat van de site klopte niet. | 🟠 Onduidelijk | Vraag public-warning.app om toestemming voor commercieel gebruik, of zoek een officiële NL-Alert-feed. |
| **P2000 via Zwaailicht.nl** (`zwaailicht.nl/feed/meldingen.xml`) | Alarmeringen van brandweer, ambulance en politie | Gratis hergebruik, mits **elk item** zichtbaar "Zwaailicht.nl" noemt met een volgbare link naar hun pagina. Hun voorwaarden verbieden wel uitdrukkelijk: *"De inhoud doorverkopen of als betaalde dienst aanbieden zonder afspraak met ons."* Voor zakelijk gebruik vragen ze een aparte afspraak (`takemymoney@zwaailicht.nl`). Technisch: hoogstens 1× per minuut, met ETag en een herkenbare User-Agent (dat doen we al). | 🔴 Afspraak nodig voor een betaalde app | **Mail Zwaailicht vóór de lancering.** In de app staat de bron nu bij elk item, met link ("Via Zwaailicht.nl"). Zonder afspraak: zet deze bron uit of bouw een eigen P2000-ontvanger. |
| **OpenFreeMap / OpenStreetMap** | Kaarttegels | OSM-data onder ODbL: commercieel gebruik toegestaan, mits naamsvermelding. | 🟢 | De naamsvermelding staat op de kaart (MapLibre-attributie). |
| **Google AdMob** | Advertenties | Toegestaan. AVG/ePrivacy vragen wel om toestemming. | 🟢 met toestemming | Google UMP-toestemming is ingebouwd. Stel een GDPR-bericht in de AdMob-console in. Privacyverklaring is bijgewerkt. |

## Niet toegevoegd, en waarom

- **Nieuws-RSS van NOS en regionale omroepen.** Deze feeds hebben geen licentie voor commercieel hergebruik. Artikeltekst valt onder het auteursrecht, en de omroepen hebben hun eigen voorwaarden. Het juridisch veilige alternatief voor "nieuws over gevaar" zijn de **Politieberichten**: officieel, met coördinaten en vrij herbruikbaar. Die staan nu standaard aan.
- **Alarmeringen.nl.** Hun feed bevat geen coördinaten (dan zouden we moeten geocoderen) en heeft geen gepubliceerde hergebruikvoorwaarden.
- **Energiestoringen (Liander/Stedin/Enexis).** Er is geen gedocumenteerde publieke API.
- **Weer en water (MeteoAlarm, RWS Waterinfo).** Die werken technisch, maar de focus ligt op direct gevaar door mensen en incidenten.

## Eigen P2000-ontvanger (optie)

Zwaailicht schrijft zelf dat de P2000-alarmering een openbaar, onversleuteld signaal is waarop zij geen rechten claimen. Hun rechten zitten op hun eigen titels, indeling en geocodering. Met een eigen ontvanger (SDR-stick plus decoder) ben je dus niet van hun voorwaarden afhankelijk. Laat wel eerst juridisch toetsen of het herpubliceren van ontvangen pagerberichten in een betaalde app is toegestaan; dat valt buiten deze inventarisatie. Let ook op privacy: pagerteksten kunnen adressen en medische details bevatten. Ambulanceritten met straatnaam staan nu als 'goed om te weten' in de app. Ze zijn geen gevaar voor omstanders; overweeg ze helemaal over te slaan.

## Ook gezien

De landingspagina noemt "meldingen uit 30 bronnen". Er draaien er nu zeven, en een paar daarvan staan uit. Pas die tekst aan voordat iemand er een misleidende claim in ziet.
