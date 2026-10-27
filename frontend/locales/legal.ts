import type { Locale } from '~/composables/useLocale';

/**
 * Privacyverklaring en voorwaarden. Beschrijft wat de app werkelijk doet;
 * pas dit aan als de verwerking verandert en laat het juridisch nalezen.
 */
type Section = { title: string; body: string[] };
type Doc = { title: string; updated: string; intro: string; sections: Section[] };

export const legal: Record<'privacy' | 'terms' | 'support', Record<Locale, Doc>> = {
  privacy: {
    nl: {
      title: 'Privacy',
      updated: 'Laatst bijgewerkt: 24 oktober 2026',
      intro: 'VeiligerBuiten laat zien wat er in je buurt gebeurt. Daarvoor is zo min mogelijk over jou nodig. Hieronder staat precies wat we verwerken en waarom.',
      sections: [
        { title: 'Je locatie', body: [
          'Je actuele locatie blijft op je telefoon. De app vraagt ongeveer eens per uur, of als je ver bent verplaatst, de situaties binnen 30 km op. Daarvoor stuurt de app een afgerond middelpunt (ongeveer 100 meter nauwkeurig) mee. Dat middelpunt koppelen we niet aan je account en bewaren we niet in onze database.',
          'Op de achtergrond vergelijkt de app de opgehaalde situaties met je locatie op de telefoon zelf. Je locatie wordt daarbij niet naar ons verstuurd.',
          'Als je een melding plaatst of een situatie bevestigt, slaan we de plek van de melding op en je locatie op dat moment. Zo controleren we dat je in de buurt was.',
        ] },
        { title: 'Je account', body: [
          'We bewaren je naam, e-mailadres, een versleuteld wachtwoord, je taal en je instellingen voor meldingen.',
          'Plekken die je opslaat (bijvoorbeeld Thuis) bewaren we met naam, coördinaten en straal, zodat we je kunnen waarschuwen als daar iets speelt.',
          'Per toestel bewaren we een pushtoken, zodat we meldingen kunnen sturen.',
        ] },
        { title: 'Abonnement', body: [
          'Op onze website betaal je via Stripe; in de app via de App Store of Google Play. Zij verwerken je betaalgegevens; wij zien die niet. Aankopen in de app lopen via RevenueCat, dat ons laat weten of je abonnement loopt en tot wanneer. Wij krijgen alleen dat te zien, gekoppeld aan je account. Verwijder je je account, dan verwijderen we je ook als klant bij Stripe en RevenueCat. Zij bewaren betalingen zelf zo lang de wet dat vraagt.',
        ] },
        { title: 'AI-koppeling en webhooks', body: [
          'Als je zelf een AI-assistent of webhook koppelt, kan die via jouw persoonlijke koppeling de situaties rond je plekken opvragen. Wat de assistent met die gegevens doet, valt onder de voorwaarden van die aanbieder. Je kunt de koppeling altijd verbreken.',
        ] },
        { title: 'Delen met anderen', body: [
          'We verkopen geen gegevens. Meldingen op de kaart zijn anoniem: anderen zien niet wie iets meldde.',
          'We gebruiken verwerkers voor hosting, pushberichten (Apple en Google) en betalingen (Stripe, Apple, Google en RevenueCat). Kaarttegels komen van OpenFreeMap; dat ziet welk kaartgebied je bekijkt, net als bij elke online kaart.',
        ] },
        { title: 'Advertenties', body: [
          'In de app zie je af en toe een kleine advertentie onderin, geleverd door Google AdMob. Nooit als er gevaar in de buurt is en nooit tijdens het melden of bekijken van een situatie.',
          'Bij de eerste advertentie vragen we via Google om je toestemming. Google kan daarbij een advertentie-ID en apparaatgegevens gebruiken; zonder toestemming tonen we alleen niet-gepersonaliseerde advertenties of geen. Je locatie, plekken en meldingen delen we nooit met adverteerders. Je kunt je keuze altijd wijzigen via Account › Advertentie-instellingen.',
        ] },
        { title: 'Bewaren en verwijderen', body: [
          'Situaties verlopen vanzelf en worden daarna opgeruimd. Verwijder je je account in de app of op veiligerbuiten.nl/account-verwijderen, dan verwijderen we je account, je plekken en je instellingen.',
          'Je hebt recht op inzage, correctie en verwijdering. Mail ons op hallo@veiligerbuiten.nl. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens.',
        ] },
      ],
    },
    en: {
      title: 'Privacy',
      updated: 'Last updated: 24 October 2026',
      intro: 'VeiligerBuiten shows what is happening near you. That requires as little about you as possible. Here is exactly what we process and why.',
      sections: [
        { title: 'Your location', body: [
          'Your current location stays on your phone. About once an hour, or after you have moved far, the app fetches the situations within 30 km. To do so it sends a rounded centre point (accurate to about 100 metres). We do not link that point to your account or keep it in our database.',
          'In the background, the app compares the fetched situations with your location on the phone itself. Your location is not sent to us for this.',
          'When you post a report or confirm a situation, we store the location of the report and your location at that moment, to check that you were nearby.',
        ] },
        { title: 'Your account', body: [
          'We store your name, email address, a hashed password, your language and your alert settings.',
          'Places you save (for example Home) are stored with name, coordinates and radius, so we can alert you when something happens there.',
          'For each device we store a push token, so we can send notifications.',
        ] },
        { title: 'Subscription', body: [
          'On our website you pay through Stripe; in the app through the App Store or Google Play. They process your payment details; we never see them. Purchases in the app run through RevenueCat, which tells us whether your subscription is running and until when. That is all we see, linked to your account. If you delete your account, we also delete you as a customer at Stripe and RevenueCat. They keep payments themselves as long as the law requires.',
        ] },
        { title: 'AI connection and webhooks', body: [
          'If you connect an AI assistant or webhook yourself, it can request the situations around your places through your personal connection. What the assistant does with that data falls under that provider’s terms. You can disconnect at any time.',
        ] },
        { title: 'Sharing', body: [
          'We do not sell data. Reports on the map are anonymous: others cannot see who reported something.',
          'We use processors for hosting, push notifications (Apple and Google) and payments (Stripe, Apple, Google and RevenueCat). Map tiles come from OpenFreeMap, which sees which map area you view, as with any online map.',
        ] },
        { title: 'Advertising', body: [
          'The app occasionally shows a small ad at the bottom, served by Google AdMob. Never when there is danger nearby, and never while you report or view a situation.',
          'Before the first ad, Google asks for your consent. Google may use an advertising ID and device data; without consent we show only non-personalised ads or none. We never share your location, places or reports with advertisers. You can change your choice at any time in Account › Ad settings.',
        ] },
        { title: 'Retention and deletion', body: [
          'Situations expire automatically and are then cleaned up. If you delete your account in the app or at veiligerbuiten.nl/account-verwijderen, we delete your account, your places and your settings.',
          'You have the right to access, correct and delete your data. Email us at hallo@veiligerbuiten.nl. You can also file a complaint with the Dutch Data Protection Authority (Autoriteit Persoonsgegevens).',
        ] },
      ],
    },
  },
  terms: {
    nl: {
      title: 'Voorwaarden',
      updated: 'Laatst bijgewerkt: 24 oktober 2026',
      intro: 'Door VeiligerBuiten te gebruiken ga je akkoord met deze voorwaarden.',
      sections: [
        { title: 'Geen hulpdienst', body: [
          'VeiligerBuiten is een informatiedienst, geen hulpdienst. Bij direct gevaar bel je altijd 112.',
          'Informatie komt van openbare bronnen en van gebruikers. Die kan onvolledig, vertraagd of onjuist zijn. Dat er niets op de kaart staat, betekent niet dat het veilig is.',
          'Buurtcijfers zijn geregistreerde misdrijven van de afgelopen twaalf maanden (bron: Politie, via CBS, CC BY 4.0). Ze zeggen iets over een buurt als geheel, niet over wat er nu gebeurt of over de mensen die er wonen.',
        ] },
        { title: 'Melden', body: [
          'Meld alleen wat je zelf ziet, op de plek waar het is. Je kunt drie meldingen per dag plaatsen.',
          'Geen persoonsgegevens, beschuldigingen, reclame of scheldwoorden. Valse of misleidende meldingen kunnen leiden tot blokkering van je account.',
        ] },
        { title: 'Abonnement', body: [
          'VeiligerBuiten kost € 0,99 per maand (inclusief btw; in de app kan de prijs per land iets verschillen), zonder proefperiode. Je sluit het af in de app via de App Store of Google Play, of op veiligerbuiten.nl via Stripe. Het abonnement verlengt elke maand automatisch.',
          'Opzeggen kan altijd: via de website op veiligerbuiten.nl/account, via de App Store of Google Play in de abonnementsinstellingen van je telefoon (uiterlijk 24 uur voor de verlenging). Je houdt toegang tot het einde van de maand waarvoor je betaald hebt.',
          'Je hebt 14 dagen bedenktijd. Zeg je binnen 14 dagen na je eerste betaling op en mail je ons, dan krijg je die betaling terug. Voor een aankoop in de app vraag je terugbetaling aan bij Apple of Google.',
        ] },
        { title: 'Aansprakelijkheid', body: [
          'We doen ons best om de dienst betrouwbaar te houden, maar kunnen niet garanderen dat die altijd werkt of dat informatie klopt. We zijn niet aansprakelijk voor schade door het gebruik van, of vertrouwen op, de informatie in de app, behalve bij opzet of grove schuld.',
        ] },
        { title: 'Contact', body: ['Vragen? Mail hallo@veiligerbuiten.nl. Op deze voorwaarden is Nederlands recht van toepassing.'] },
      ],
    },
    en: {
      title: 'Terms',
      updated: 'Last updated: 24 October 2026',
      intro: 'By using VeiligerBuiten you agree to these terms.',
      sections: [
        { title: 'Not an emergency service', body: [
          'VeiligerBuiten is an information service, not an emergency service. In immediate danger, always call 112.',
          'Information comes from public sources and from users. It can be incomplete, delayed or wrong. An empty map does not mean it is safe.',
          'Neighbourhood statistics are crimes recorded over the past twelve months (source: Police, via CBS, CC BY 4.0). They describe a neighbourhood as a whole, not what is happening now or the people who live there.',
        ] },
        { title: 'Reporting', body: [
          'Only report what you see yourself, where it is. You can post three reports per day.',
          'No personal data, accusations, advertising or abuse. False or misleading reports can lead to your account being blocked.',
        ] },
        { title: 'Subscription', body: [
          'VeiligerBuiten costs €0.99 per month (VAT included; in the app the price may differ slightly per country), with no trial. You subscribe in the app through the App Store or Google Play, or at veiligerbuiten.nl through Stripe. The subscription renews automatically every month.',
          'You can cancel at any time: for the website at veiligerbuiten.nl/account, for the App Store or Google Play in your phone\'s subscription settings (at least 24 hours before renewal). You keep access until the end of the month you paid for.',
          'You have a 14-day cooling-off period. Cancel within 14 days of your first payment and email us, and we refund that payment. For a purchase in the app, request a refund from Apple or Google.',
        ] },
        { title: 'Liability', body: [
          'We do our best to keep the service reliable, but cannot guarantee that it always works or that information is correct. We are not liable for damage caused by using or relying on the information in the app, except in case of intent or gross negligence.',
        ] },
        { title: 'Contact', body: ['Questions? Email hallo@veiligerbuiten.nl. These terms are governed by Dutch law.'] },
      ],
    },
  },
  support: {
    nl: {
      title: 'Hulp',
      updated: 'Laatst bijgewerkt: 26 oktober 2026',
      intro: 'Kom je ergens niet uit? Hieronder staan de meest gestelde vragen. Staat je vraag er niet tussen, mail dan naar hallo@veiligerbuiten.nl. We antwoorden binnen twee werkdagen.',
      sections: [
        { title: 'Direct gevaar', body: [
          'VeiligerBuiten is geen hulpdienst. Bij direct gevaar bel je altijd 112.',
        ] },
        { title: 'Account en abonnement', body: [
          'Je maakt je account en abonnement aan op veiligerbuiten.nl/aanmelden. Daarna log je in de app in met hetzelfde e-mailadres en wachtwoord.',
          'Opzeggen, je betaalmethode wijzigen of facturen bekijken doe je op veiligerbuiten.nl/account. Je abonnement loopt door tot het einde van de betaalde maand.',
        ] },
        { title: 'Wachtwoord vergeten', body: [
          'Tik in de app op Wachtwoord vergeten? of ga naar veiligerbuiten.nl/forgot-password. Je krijgt een link om een nieuw wachtwoord in te stellen.',
        ] },
        { title: 'Ik zie geen meldingen', body: [
          'De app toont wat er binnen ongeveer 30 km van je locatie speelt. Sta locatie toe in de instellingen van je telefoon. Zonder locatie laat de app zien wat er rond het midden van de kaart speelt.',
          'Krijg je geen meldingen op je telefoon? Controleer of meldingen voor VeiligerBuiten aan staan, en kijk in de app onder Account welke soorten meldingen je ontvangt en wanneer je stille uren hebt.',
        ] },
        { title: 'Account verwijderen', body: [
          'In de app: Account → Account verwijderen. Zonder de app: veiligerbuiten.nl/account-verwijderen. Je account, plekken en instellingen worden verwijderd en een lopend abonnement stopt direct.',
        ] },
        { title: 'Een melding klopt niet', body: [
          'Tik op de melding en kies Is voorbij als de situatie voorbij is. Zie je een melding die niet hoort, mail dan naar hallo@veiligerbuiten.nl met de plaats en het tijdstip.',
        ] },
      ],
    },
    en: {
      title: 'Help',
      updated: 'Last updated: 26 October 2026',
      intro: 'Stuck? These are the most common questions. If yours is not here, email hallo@veiligerbuiten.nl. We reply within two working days.',
      sections: [
        { title: 'Immediate danger', body: [
          'VeiligerBuiten is not an emergency service. In immediate danger, always call 112.',
        ] },
        { title: 'Account and subscription', body: [
          'You create your account and subscription at veiligerbuiten.nl/aanmelden. Then log in to the app with the same email address and password.',
          'To cancel, change your payment method or view invoices, go to veiligerbuiten.nl/account. Your subscription runs until the end of the paid month.',
        ] },
        { title: 'Forgot password', body: [
          'Tap Forgot password? in the app or go to veiligerbuiten.nl/forgot-password. You will get a link to set a new password.',
        ] },
        { title: 'I do not see any reports', body: [
          'The app shows what is happening within about 30 km of your location. Allow location access in your phone settings. Without location, the app shows what is happening around the centre of the map.',
          'Not getting notifications? Check that notifications for VeiligerBuiten are on, and see under Account in the app which kinds of alerts you receive and when your quiet hours are.',
        ] },
        { title: 'Delete account', body: [
          'In the app: Account → Delete account. Without the app: veiligerbuiten.nl/account-verwijderen. Your account, places and settings are deleted and a running subscription stops immediately.',
        ] },
        { title: 'A report is wrong', body: [
          'Tap the report and choose It’s over if the situation has ended. If you see a report that does not belong there, email hallo@veiligerbuiten.nl with the place and time.',
        ] },
      ],
    },
  },
};
