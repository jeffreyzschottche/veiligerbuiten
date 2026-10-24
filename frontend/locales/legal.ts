import type { Locale } from '~/composables/useLocale';

/**
 * Privacyverklaring en voorwaarden. Beschrijft wat de app werkelijk doet;
 * pas dit aan als de verwerking verandert en laat het juridisch nalezen.
 */
type Section = { title: string; body: string[] };
type Doc = { title: string; updated: string; intro: string; sections: Section[] };

export const legal: Record<'privacy' | 'terms', Record<Locale, Doc>> = {
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
          'Betalen gaat via de App Store of Google Play. Wij zien geen betaalgegevens. RevenueCat verwerkt voor ons de status van je abonnement, gekoppeld aan je account-id.',
        ] },
        { title: 'AI-koppeling en webhooks', body: [
          'Als je zelf een AI-assistent of webhook koppelt, kan die via jouw persoonlijke koppeling de situaties rond je plekken opvragen. Wat de assistent met die gegevens doet, valt onder de voorwaarden van die aanbieder. Je kunt de koppeling altijd verbreken.',
        ] },
        { title: 'Delen met anderen', body: [
          'We verkopen geen gegevens. Meldingen op de kaart zijn anoniem: anderen zien niet wie iets meldde.',
          'We gebruiken verwerkers voor hosting, pushberichten (Apple en Google) en abonnementen (RevenueCat). Kaarttegels komen van OpenFreeMap; dat ziet welk kaartgebied je bekijkt, net als bij elke online kaart.',
        ] },
        { title: 'Advertenties', body: [
          'In de app zie je af en toe een kleine advertentie onderin, geleverd door Google AdMob. Nooit als er gevaar in de buurt is en nooit tijdens het melden of bekijken van een situatie.',
          'Bij de eerste advertentie vragen we via Google om je toestemming. Google kan daarbij een advertentie-ID en apparaatgegevens gebruiken; zonder toestemming tonen we alleen niet-gepersonaliseerde advertenties of geen. Je locatie, plekken en meldingen delen we nooit met adverteerders. Je kunt je keuze altijd wijzigen via Account › Advertentie-instellingen.',
        ] },
        { title: 'Bewaren en verwijderen', body: [
          'Situaties verlopen vanzelf en worden daarna opgeruimd. Verwijder je je account in de app, dan verwijderen we je account, je plekken en je instellingen.',
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
          'Payment goes through the App Store or Google Play. We never see payment details. RevenueCat processes your subscription status for us, linked to your account id.',
        ] },
        { title: 'AI connection and webhooks', body: [
          'If you connect an AI assistant or webhook yourself, it can request the situations around your places through your personal connection. What the assistant does with that data falls under that provider’s terms. You can disconnect at any time.',
        ] },
        { title: 'Sharing', body: [
          'We do not sell data. Reports on the map are anonymous: others cannot see who reported something.',
          'We use processors for hosting, push notifications (Apple and Google) and subscriptions (RevenueCat). Map tiles come from OpenFreeMap, which sees which map area you view, as with any online map.',
        ] },
        { title: 'Advertising', body: [
          'The app occasionally shows a small ad at the bottom, served by Google AdMob. Never when there is danger nearby, and never while you report or view a situation.',
          'Before the first ad, Google asks for your consent. Google may use an advertising ID and device data; without consent we show only non-personalised ads or none. We never share your location, places or reports with advertisers. You can change your choice at any time in Account › Ad settings.',
        ] },
        { title: 'Retention and deletion', body: [
          'Situations expire automatically and are then cleaned up. If you delete your account in the app, we delete your account, your places and your settings.',
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
          'VeiligerBuiten kost € 0,99 per maand, zonder proefperiode. Het abonnement verlengt automatisch en is maandelijks op te zeggen via de App Store of Google Play.',
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
          'VeiligerBuiten costs €0.99 per month, with no trial. The subscription renews automatically and can be cancelled monthly in the App Store or Google Play.',
        ] },
        { title: 'Liability', body: [
          'We do our best to keep the service reliable, but cannot guarantee that it always works or that information is correct. We are not liable for damage caused by using or relying on the information in the app, except in case of intent or gross negligence.',
        ] },
        { title: 'Contact', body: ['Questions? Email hallo@veiligerbuiten.nl. These terms are governed by Dutch law.'] },
      ],
    },
  },
};
