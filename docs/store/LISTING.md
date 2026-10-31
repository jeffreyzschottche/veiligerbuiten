# Storeteksten en formulieren

Alles wat App Store Connect en Play Console vragen, klaar om te plakken. Tekenlimieten
staan tussen haakjes. In de app abonneer je via de App Store of Google Play (RevenueCat), op
de website via Stripe. Zet in de app en de storeteksten **geen link of oproep om op de
website te betalen**: dat is bij Apple (3.1.1/3.1.3) en Google (Payments-beleid) reden
voor afwijzing.

| Winkelproduct | ID |
| --- | --- |
| Apple-abonnement (groep "VeiligerBuiten Premium", 1 maand, € 0,99) | `nl.veiligerbuiten.app.monthly` |
| Google Play-abonnement / basisabonnement (1 maand, € 0,99, automatisch verlengen) | `veiligerbuiten_monthly` / `monthly` |
| RevenueCat-entitlement | `veiligerbuiten_premium` (offering `default`, pakket `$rc_monthly`) |

## Vaste gegevens

| Veld | Waarde |
| --- | --- |
| Bundle-ID / package | `nl.veiligerbuiten.app` |
| Naam | VeiligerBuiten |
| Primaire taal | Nederlands (nl-NL); Engels (en-GB) als tweede taal |
| Categorie | Apple: Navigatie (secundair: Nieuws) · Google: Kaarten en navigatie |
| Prijs van de download | Gratis, met een abonnement in de app van € 0,99 per maand |
| Advertenties | Ja (AdMob-banner, af en toe, nooit bij gevaar) |
| Support-URL | https://veiligerbuiten.nl/support |
| Marketing-URL | https://veiligerbuiten.nl |
| Privacy-URL | https://veiligerbuiten.nl/privacy |
| Account verwijderen (Google) | https://veiligerbuiten.nl/account-verwijderen |
| Contact-e-mail | hallo@veiligerbuiten.nl |
| Copyright (Apple) | 2026 VeiligerBuiten |

## Nederlands

**Naam (30):** VeiligerBuiten

**Ondertitel, Apple (30):** Weet wat er om je heen gebeurt

**Korte beschrijving, Google (80):** Live meldingen over gevaar in je buurt. Uit openbare bronnen en van buren.

**Promotietekst, Apple (170):** Nieuw: meld intimidatie op straat in een paar tikken en zie waar het vaker gebeurt. Zo kies je zelf hoe je naar huis loopt.

**Trefwoorden, Apple (100 bytes, komma's zonder spatie):**
`veiligheid,buurt,meldingen,p2000,brand,politie,nl-alert,intimidatie,alarm,waarschuwing,straat,112`

**Beschrijving (4000):**

```
Een gerust gevoel begint bij weten. VeiligerBuiten laat op één rustige kaart zien wat er nu om je heen gebeurt: van brand en verkeer tot onrust op straat. Alleen een seintje als het ertoe doet.

GEEN OVERHEIDSAPP
VeiligerBuiten is een onafhankelijke app van Zschot Media. We zijn geen overheidsinstantie en vertegenwoordigen geen overheid, politie, hulpdienst, NL-Alert, NDW, KNMI of CBS. We werken niet in hun opdracht en zijn niet door hen goedgekeurd. Bij elke melding staat de bron. De officiële bronnen staan onderaan deze tekst.

VEEL SIGNALEN, ÉÉN PLEK
We brengen openbare meldingen samen: alarmeringen van hulpdiensten (P2000), politieberichten, NL-Alert, verkeersmeldingen van NDW en aardbevingen van het KNMI. Komt hetzelfde uit meerdere bronnen, dan wordt het één melding die zekerder is. De politiecijfers per buurt laten zien waar het afgelopen jaar opvallend vaak geweld of straatroof was.

JOUW BUURT VULT HET AAN
Zie je zelf iets? Meld het in drie tikken. Anderen in de buurt bevestigen of melden dat het voorbij is. Hoe meer mensen een melding bevestigen, hoe zekerder hij wordt. Je meldt anoniem: anderen zien niet wie het meldde.

MET EEN GERUST GEVOEL DE STRAAT OP
Nagefloten, gevolgd of lastiggevallen worden gebeurt vaak op dezelfde plekken. Meld het in een paar tikken en zie waar je extra kunt opletten. Kom je 's avonds in de buurt van een recente melding, dan krijg je een seintje.

EEN SEINTJE, GEEN SPAM
De ernst bepaalt hoe je het hoort. Gevaar komt direct door, met geluid. Opletten krijg je alleen als het dichtbij is. Goed om te weten komt stil en gebundeld. Je krijgt nooit twee keer hetzelfde.

THUIS, WERK, SCHOOL
Sla je plekken op en kies waarvoor je een melding wilt. Stel stille uren in en bepaal zelf hoe vaak je telefoon controleert.

OOK ZONDER BEREIK
De app bewaart de meldingen in je omgeving op je telefoon. Verplaats je je zonder internet, dan kan je telefoon je nog steeds waarschuwen bij een eerder opgeslagen situatie.

LOCATIE VOOR MELDINGEN IN JE BUURT
De app gebruikt je locatie om situaties in je omgeving te tonen. Voor waarschuwingen wordt een globale locatie gebruikt. Als je zelf een melding plaatst, controleert de server je actuele locatie en slaat de locatie van het incident op. We bewaren geen doorlopende locatiegeschiedenis.

WERKT MET JE AI-ASSISTENT
Koppel je account aan ChatGPT, Claude of een andere AI-assistent en vraag gewoon of het rustig is rond je bestemming.

BRONNEN
De app toont openbare informatie van deze bronnen. Kijk voor de officiële en volledige informatie altijd bij de bron zelf:
• Politieberichten (Politie): https://www.politie.nl/nieuws
• NL-Alert: https://www.nl-alert.nl
• Verkeersmeldingen (Nationaal Dataportaal Wegverkeer): https://www.ndw.nu
• Aardbevingen (KNMI): https://www.knmi.nl
• Politiecijfers per buurt (Politie en CBS): https://data.politie.nl
• Alarmeringen van hulpdiensten (P2000), via Sirenes: https://sirenes.app, Alarmeringen.nl: https://www.alarmeringen.nl en de P2000-monitor: https://www.p2000zhz-rr.nl
• Kaart: © OpenStreetMap-bijdragers, https://www.openstreetmap.org/copyright

Voor de kaart en meldingen heb je een abonnement nodig van € 0,99 per maand, zonder proefperiode. Het verlengt automatisch en je zegt het op wanneer je wilt in je App Store- of Google Play-instellingen.

VeiligerBuiten is een informatiedienst, geen hulpdienst. Bij direct gevaar bel je altijd 112.
```

**Wat is er nieuw (versie 1.0):** De eerste versie van VeiligerBuiten. Live kaart, meldingen voor je buurt en je plekken, en melden in drie tikken.

## English

**Name (30):** VeiligerBuiten

**Subtitle, Apple (30):** Know what is happening nearby

**Short description, Google (80):** Live alerts about danger near you. From public sources and your neighbours.

**Promotional text, Apple (170):** New: report street harassment in a few taps and see where it happens more often. So you choose how you walk home.

**Keywords, Apple (100 bytes):**
`safety,neighbourhood,alerts,p2000,fire,police,nl-alert,harassment,alarm,warning,street,112`

**Description (4000):**

```
Peace of mind starts with knowing. VeiligerBuiten shows what is happening around you right now on one calm map: from fires and traffic to trouble on the street. You only hear from us when it matters.

NOT A GOVERNMENT APP
VeiligerBuiten is an independent app by Zschot Media. We are not a government entity and do not represent any government, the police, emergency services, NL-Alert, NDW, KNMI or CBS. We do not work on their behalf and are not endorsed by them. Every report shows its source. The official sources are listed at the end of this text.

MANY SIGNALS, ONE PLACE
We bring together public reports: emergency service dispatches (P2000), police news, NL-Alert, traffic reports from NDW and earthquakes from the KNMI. When several sources report the same thing, it becomes one report you can be more sure of. Police figures per neighbourhood show where violence or street robbery happened unusually often over the past year.

YOUR NEIGHBOURHOOD FILLS IN THE GAPS
See something yourself? Report it in three taps. People nearby confirm it or mark it as over. The more people confirm a report, the more certain it becomes. You report anonymously: others cannot see who reported it.

WALK THE STREETS WITH PEACE OF MIND
Being catcalled, followed or harassed often happens in the same places. Report it in a few taps and see where to take extra care. Walking home at night near a recent report? You get a heads-up.

A HEADS-UP, NOT SPAM
Severity decides how you hear about it. Danger comes through right away, with sound. Heads-ups only when they are close. Good-to-know arrives silently and bundled. You never get the same alert twice.

HOME, WORK, SCHOOL
Save your places and choose what you want alerts for. Set quiet hours and decide how often your phone checks.

EVEN WITHOUT SIGNAL
The app keeps the reports around you on your phone. Moving without internet? Your phone can still warn you about a situation it saved earlier.

LOCATION FOR ALERTS NEAR YOU
The app uses your location to show situations around you. Alerts use an approximate location. When you place a report yourself, the server checks your current location and stores the location of the incident. We keep no ongoing location history.

WORKS WITH YOUR AI ASSISTANT
Connect your account to ChatGPT, Claude or another AI assistant and simply ask whether it is calm around where you are going.

SOURCES
The app shows public information from these sources. For the official and complete information, always check the source itself:
• Police news (Politie): https://www.politie.nl/nieuws
• NL-Alert: https://www.nl-alert.nl
• Traffic reports (National Road Traffic Data Portal, NDW): https://www.ndw.nu
• Earthquakes (KNMI): https://www.knmi.nl
• Police figures per neighbourhood (Politie and CBS): https://data.politie.nl
• Emergency service dispatches (P2000), via Sirenes: https://sirenes.app, Alarmeringen.nl: https://www.alarmeringen.nl and the P2000 monitor: https://www.p2000zhz-rr.nl
• Map: © OpenStreetMap contributors, https://www.openstreetmap.org/copyright

The map and alerts need a subscription of €0.99 per month, with no trial. It renews automatically and you can cancel at any time in your App Store or Google Play settings.

VeiligerBuiten is an information service, not an emergency service. In immediate danger, always call 112.
```

**What's new (version 1.0):** The first version of VeiligerBuiten. Live map, alerts for your neighbourhood and your places, and reporting in three taps.

## Review (Apple) en App-toegang (Google)

Geef de reviewer een testaccount met toegang (zonder dat die hoeft te kopen); kopen
testen ze zelf met een sandbox-account. Maak het account op productie aan en geef het toegang:

```sh
php artisan veiligbuiten:gift review@veiligerbuiten.nl --days=365
```

**Reviewnotities (Engels):**

```
VeiligerBuiten shows safety-related situations near the user (fires, traffic, severe weather, street harassment) from official Dutch sources and user reports.

Sign-in is required. Use the demo account below; it already has full access.
To test the subscription, create a new account in the app ("Get started"): the paywall offers the monthly auto-renewing subscription (nl.veiligerbuiten.app.monthly, EUR 0.99, no trial) through In-App Purchase, with Restore purchases. Subscriptions bought on our website also work in the app, but the app contains no links to that website purchase.

Location: used while the app is open to show nearby situations and to check that a report is placed within 1 km of the user. A periodic background check compares the user's position with situations already stored on the device, so a local notification can warn them; the precise position is not sent for this.
Push notifications: alerts about danger near the user's saved places.
Account deletion: Account tab → Delete account.

The app is not an emergency service and says so on every report screen.
```

## Apple: App Privacy

| Gegevenstype | Verzameld | Gekoppeld aan identiteit | Tracking | Doel |
| --- | --- | --- | --- | --- |
Zo ingevuld in App Store Connect op 26-10-2026 (de AdMob-SDK verzamelt zelf ook gegevens):

| Gegevenstype | Gekoppeld aan identiteit | Tracking | Doel |
| --- | --- | --- | --- |
| Naam | Ja | Nee | App-functionaliteit |
| E-mailadres | Ja | Nee | App-functionaliteit |
| Precieze locatie | Ja | Nee | App-functionaliteit |
| Globale locatie (vak van ~5 km, en AdMob) | Ja | Nee | App-functionaliteit, advertenties van derden |
| Andere door gebruiker gemaakte content (meldingen, toelichting tot 180 tekens) | Ja | Nee | App-functionaliteit |
| Gebruikers-ID (account-ID) | Ja | Nee | App-functionaliteit |
| Apparaat-ID (installatie-ID, pushtoken, IDFA na ATT-toestemming) | Ja | Ja | App-functionaliteit, advertenties van derden |
| Productinteractie (bevestigen/opgelost, AdMob) | Ja | Ja | App-functionaliteit, advertenties van derden |
| Reclamegegevens (AdMob) | Nee | Ja | Advertenties van derden |
| Crashgegevens, prestatiegegevens, overige diagnostiek (AdMob) | Nee | Nee | Analyse |
| Aankoopgeschiedenis (abonnement in de app, via RevenueCat) | Ja | Nee | App-functionaliteit |

Leeftijdsclassificatie: berekend 4+, handmatig verhoogd naar **13+** (meldingen over geweld
en intimidatie). Beschikbaarheid: alleen Nederland. Prijs: gratis.

## Google Play: Data safety

- Verzamelt of deelt de app gebruikersgegevens? **Ja**
- Versleuteld tijdens overdracht? **Ja** (HTTPS)
- Kunnen gebruikers verwijdering aanvragen? **Ja**, in de app en via https://veiligerbuiten.nl/account-verwijderen

| Categorie | Type | Verzameld | Gedeeld | Optioneel | Doel |
| --- | --- | --- | --- | --- | --- |
| Locatie | Precieze locatie | Ja | Nee | Ja (app werkt zonder, met kaartmidden) | App-functionaliteit |
| Persoonlijke info | Naam, e-mailadres | Ja | Nee | Nee | Accountbeheer |
| Berichten / Content | Andere door gebruiker gemaakte content | Ja | Nee | Ja | App-functionaliteit |
| App-activiteit | App-interacties (bevestigen/opgelost) | Ja | Nee | Ja | App-functionaliteit |
| Apparaat-ID's | Installatie-ID, pushtoken, advertentie-ID | Ja | Ja (advertentie-ID met Google AdMob) | Nee | App-functionaliteit, advertenties |
| Financiële info | Aankoopgeschiedenis (abonnement via Google Play, RevenueCat) | Ja | Nee | Nee | App-functionaliteit |

## Leeftijd en contentrating

- **Apple:** geen geweld, seks, gokken, drugs of grof taalgebruik in de app zelf. Wel:
  *door gebruikers gemaakte content* (korte toelichting bij meldingen, gemodereerd en gefilterd)
  en *nieuws/informatie over incidenten* (brand, politie). Verwachte uitkomst: **12+**.
- **Google (IARC):** categorie *Referentie, nieuws of educatief*. Gebruikersinteractie: ja
  (gebruikers kunnen korte meldingen plaatsen, niet met elkaar chatten). Deelt locatie met
  andere gebruikers: nee (alleen de plek van een melding, niet die van de melder).
- **Doelgroep (Google):** 18+. Niet bedoeld voor kinderen.

## Google: overige verklaringen

- **Advertenties:** bevat advertenties.
- **Overheids-app:** nee. De beschrijving moet een bronnenlijst met links en de disclaimer "geen overheidsapp" houden (Misleading Claims-afwijzing van 31-10-2026). **Financiële functies:** geen. **Gezondheid:** nee.
- **Nieuws-app:** nee (het is een informatiedienst, geen uitgever).
- **Locatie op de achtergrond:** de app vraagt geen `ACCESS_BACKGROUND_LOCATION` aan.
- **Melding-permissie:** voor waarschuwingen over gevaar bij je plekken.
