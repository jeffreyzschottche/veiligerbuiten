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
| **P2000 via Sirenes** (`sirenes.app/rss.xml`) | Landelijke alarmeringen met straat en plaats | Publieke RSS voor abonnees. Er zijn geen hergebruikvoorwaarden gepubliceerd. robots.txt sluit alleen `/api/` uit; wij gebruiken de RSS. Contact: hi@sirenes.app. | 🟠 Onduidelijk | Bron en link staan per item. Vraag ze om toestemming. |
| **P2000 via Alarmeringen.nl** (`alarmeringen.nl/feeds/all.rss`) | Landelijke alarmeringen (straat en plaats in de tekst) | Van **DPG Media**. Publieke RSS, zonder licentie voor hergebruik. Voor commercieel gebruik verwacht een grote uitgever normaal een licentie. | 🟠/🔴 Waarschijnlijk licentie nodig | Bron en link staan per item. Neem contact op met DPG Media. |
| **P2000-monitor** (p2000zhz-rr.nl via FeedBurner) | Ruwe pagerteksten van Rotterdam-Rijnmond, Zuid-Holland Zuid, Lifeliners en KNRM. De andere 24 regiofeeds staan sinds 2012 stil. | Geen voorwaarden gepubliceerd. | 🟠 Onduidelijk | We tonen nooit de ruwe tekst, alleen een eigen zin. |
| **Politiecijfers per buurt** (CBS-tabel 47022NED, maandcijfers) | Geregistreerde straatroof, openlijk geweld, bedreiging en mishandeling per buurt, de laatste 12 maanden. Met de buurtoppervlakte uit de CBS Kerncijfers wijken en buurten. | data.overheid.nl: **CC BY 4.0**, eigenaar Politie. Commercieel gebruik toegestaan, met naamsvermelding. | 🟢 Toegestaan, met naamsvermelding | "Bron: Politie (CBS)" staat bij elke buurt in de app en in de voorwaarden. Het blijft statistiek: nooit een melding of pushbericht, en de tekst gaat over de buurt, niet over de bewoners. |
| **PDOK Locatieserver** (overheid) | Straat, postcode of buurtcode omzetten naar een plek op de kaart | Open overheidsdata, commercieel gebruik toegestaan. | 🟢 | Nooit op huisnummer; resultaten 30 dagen in cache. |
| **OpenFreeMap / OpenStreetMap** | Kaarttegels | OSM-data onder ODbL: commercieel gebruik toegestaan, mits naamsvermelding. | 🟢 | De naamsvermelding staat op de kaart (MapLibre-attributie). |
| **Google AdMob** | Advertenties | Toegestaan. AVG/ePrivacy vragen wel om toestemming. | 🟢 met toestemming | Google UMP-toestemming is ingebouwd. Stel een GDPR-bericht in de AdMob-console in. Privacyverklaring is bijgewerkt. |

## Zo gaan we om met de P2000-bronnen

Er zijn nu vier P2000-bronnen: Zwaailicht, Sirenes, Alarmeringen.nl en de monitor. Dat geeft:
- **meer dekking en zekerheid**: hetzelfde voorval uit meerdere bronnen wordt één melding met meerdere observaties;
- **minder afhankelijkheid**: valt één bron weg, dan blijft P2000 werken.

Om het netjes te houden:
- Bij elk item staan de bron en een link.
- Ophalen gebeurt met ETag/Last-Modified en hoogstens eens per 2 minuten.
- **Ambulanceritten tonen nooit een straat of huisnummer, uit geen enkele bron.** We tonen alleen de plaats, afgerond op ongeveer 300 m.
- Een melding met alleen een plaatsnaam krijgt geen eigen stip; die telt alleen als bevestiging van een melding die er al is.

Het P2000-signaal zelf is openbaar. De bronnen hebben wel rechten op hun eigen teksten en indeling. De keuze om ze te gebruiken is bewust gemaakt. Om het risico te verkleinen: vraag de vier partijen om toestemming, of neem met een eigen P2000-ontvanger de bron zelf in handen.

## Niet toegevoegd, en waarom

- **Nieuws-RSS van NOS en regionale omroepen.** Deze feeds hebben geen licentie voor commercieel hergebruik. Artikeltekst valt onder het auteursrecht, en de omroepen hebben hun eigen voorwaarden. Het juridisch veilige alternatief voor "nieuws over gevaar" zijn de **Politieberichten**: officieel, met coördinaten en vrij herbruikbaar. Die staan nu standaard aan.
- **Energiestoringen (Liander/Stedin/Enexis).** Er is geen gedocumenteerde publieke API.
- **Weer en water (MeteoAlarm, RWS Waterinfo).** Die werken technisch, maar de focus ligt op direct gevaar door mensen en incidenten.

## Eigen P2000-ontvanger (optie)

Zwaailicht schrijft zelf dat de P2000-alarmering een openbaar, onversleuteld signaal is waarop zij geen rechten claimen. Hun rechten zitten op hun eigen titels, indeling en geocodering. Met een eigen ontvanger (SDR-stick plus decoder) ben je dus niet van hun voorwaarden afhankelijk. Laat wel eerst juridisch toetsen of het herpubliceren van ontvangen pagerberichten in een betaalde app is toegestaan; dat valt buiten deze inventarisatie. Let ook op privacy: pagerteksten kunnen adressen en medische details bevatten. Ambulanceritten met straatnaam staan nu als 'goed om te weten' in de app. Ze zijn geen gevaar voor omstanders; overweeg ze helemaal over te slaan.

## Ook gezien

De landingspagina noemde eerst "meldingen uit 30 bronnen". Dat is aangepast naar het aantal dat echt draait: nu 9, met de buurtcijfers erbij.
