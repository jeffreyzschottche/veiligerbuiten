import type { Locale } from '~/composables/useLocale';

/** Bronnen met een werkende koppeling in de backend (zie config/services.php, source_health). */
export const sourceNames = [
  'P2000',
  'NL-Alert',
  'Politie.nl',
  'KNMI',
  'KNMI Aardbevingen',
  'NDW',
  'Rijkswaterstaat',
  'Luchtmeetnet',
] as const;

const nl = {
  meta: {
    title: 'VeiligBuiten — De Flitsmeister voor gevaar',
    description: 'Weet wat er om je heen gebeurt. Live meldingen over brand, geweld, bommeldingen en noodweer uit officiële bronnen en van mensen in je buurt. € 0,99 per maand.',
  },
  nav: { how: 'Hoe het werkt', alerts: 'Meldingen', ai: 'AI', price: 'Prijs', download: 'Download' },
  stores: { appleTop: 'Download in de', googleTop: 'Ontdek het op' },
  hero: {
    eyebrow: 'De Flitsmeister voor gevaar',
    title: 'Be safe.',
    lead: 'Brand, geweld, een bommelding of noodweer: VeiligBuiten laat je zien wat er om je heen gebeurt. Live uit officiële bronnen en van mensen in je buurt. Je krijgt alleen een seintje als het ertoe doet.',
    price: '€ 0,99 per maand · geen reclame',
    chipConfirmed: '12× bevestigd',
    chipSource: 'Politie · 2 min geleden',
  },
  sources: { label: 'Live gekoppeld', users: '+ meldingen van mensen in de buurt', more: 'En we koppelen er steeds meer.' },
  how: {
    eyebrow: 'Hoe het werkt',
    title: 'Zien. Melden. Bevestigen.',
    steps: [
      { title: 'Zie wat er speelt', text: 'Eén rustige kaart met alles wat er nu gebeurt binnen 30 km. Rood is gevaar, oranje is opletten en blauw is goed om te weten.' },
      { title: 'Meld in drie tikken', text: 'Kies wat je ziet en waar. Je kunt drie meldingen per dag plaatsen, zodat de kaart betrouwbaar blijft.' },
      { title: 'Samen wordt het zeker', text: 'Hoe meer mensen een melding bevestigen, hoe groter de stip. Mensen iets verder weg krijgen dan ook een seintje.' },
    ],
  },
  alerts: {
    eyebrow: 'Meldingen',
    title: 'Een seintje.\nGeen spam.',
    lead: 'De ernst bepaalt hoe je het hoort. Losse berichten bundelen we, en je krijgt nooit twee keer hetzelfde.',
    points: [
      { title: 'Gevaar', text: 'Direct, met geluid. Ook door je focusmodus heen.' },
      { title: 'Opletten', text: 'Een gewone melding, alleen als het dichtbij is.' },
      { title: 'Goed om te weten', text: 'Stil, gebundeld in één bericht.' },
    ],
  },
  places: {
    eyebrow: 'Jouw plekken',
    title: 'Thuis, werk, school.\nJij kiest.',
    lead: 'Sla je plekken op, kies waarvoor je een melding wilt en hoe vaak je telefoon zelf controleert. In het Nederlands of in het Engels.',
    points: ['Meldingen voor je eigen plekken', 'Filters per type situatie', 'Zelf instellen hoe vaak er wordt gecontroleerd', 'Nederlands of English'],
  },
  ai: {
    eyebrow: 'Werkt met je AI',
    title: 'Vraag het gewoon\naan je assistent.',
    lead: 'Koppel je account met één endpoint aan ChatGPT, Claude of een andere assistent die MCP ondersteunt. Je assistent kent dan je plekken en weet wat er speelt. Gebruik je een eigen agent, dan krijgt die via een webhook een seintje.',
    endpoint: 'MCP-endpoint',
    webhook: 'Webhook',
    webhookValue: 'https://jouw-agent.nl/hooks/veiligbuiten',
    chat: {
      assistant: 'Je assistent',
      question: 'Is het rustig rond Amsterdam Centraal? Ik ben er over 20 minuten.',
      tool: 'veiligbuiten · situaties in de buurt',
      answer: 'Niet helemaal. 12 minuten geleden was er een bommelding bij de oostelijke ingang (Politie, 12× bevestigd). Dat deel is afgezet. Neem de ingang aan de westkant, daar is het rustig.',
    },
  },
  trust: [
    { title: 'Je locatie blijft bij jou', text: 'De app haalt ieder uur de situatie in een straal van 30 km op en controleert die op je telefoon. Je locatie stuur je niet live mee.' },
    { title: 'Zuinig', text: 'Weinig verzoeken, weinig batterij. Jij bepaalt hoe vaak er op de achtergrond wordt gecontroleerd.' },
    { title: 'Bel 112 bij direct gevaar', text: 'VeiligBuiten helpt je vooruit te kijken. Bij direct gevaar bel je altijd 112.' },
  ],
  price: {
    eyebrow: 'Prijs',
    amount: '€ 0,99',
    per: 'per maand',
    title: 'Alles. Voor minder dan een euro.',
    items: ['Live kaart en meldingen', 'Al je plekken en filters', 'Koppeling met je AI-assistent', 'Geen reclame', 'Maandelijks opzegbaar'],
    note: 'Geen proefperiode, geen kleine lettertjes.',
  },
  footer: { motto: 'Be safe.', emergency: 'Bij direct gevaar: bel altijd 112.', privacy: 'Privacy', terms: 'Voorwaarden', contact: 'Contact' },
  scene: {
    here: 'Amsterdam Centrum',
    nearby: 'In de buurt',
    tabs: ['Kaart', 'Plekken', 'Account'],
    items: [
      { title: 'Bommelding', meta: '350 m · 2 min · 12×', tone: 'danger' },
      { title: 'Vechtpartij', meta: '1,2 km · 18 min · 4×', tone: 'warn' },
      { title: 'Wegafsluiting', meta: '600 m · NDW', tone: 'info' },
    ],
    notifTitle: 'Bommelding · Amsterdam Centraal',
    notifBody: '350 m van je · Politie · 12× bevestigd',
    now: 'nu',
    report: { title: 'Wat zie je?', cats: ['Brand', 'Geweld', 'Verdacht', 'Verkeer', 'Weer', 'Anders'], sub: 'Verdacht', subs: ['Verdacht object', 'Verdachte situatie', 'Achtervolging'], done: 'Geplaatst', doneText: 'Mensen in de buurt kunnen nu bevestigen.', left: '2 van 3 vandaag' },
    confirm: { label: 'bevestigd', reach: 'Seintje tot' },
    lock: { time: '21:47', date: 'donderdag 23 oktober', n1: 'Wegafsluiting Prins Hendrikkade', n1b: '600 m · NDW', n2: '3 meldingen in je buurt', n2b: 'Gaslucht, oliespoor en 1 andere', n3: 'Bommelding · Amsterdam Centraal', n3b: '350 m · Politie · 12× bevestigd' },
    placesScene: { title: 'Plekken', list: [{ name: 'Thuis', area: 'Jordaan', r: '1 km' }, { name: 'Werk', area: 'Zuidas', r: '2 km' }, { name: 'School', area: 'Oost', r: '500 m' }], filters: 'Meldingen voor', chips: ['Gevaar', 'Geweld', 'Brand', 'Verkeer', 'Weer', 'Info'], check: 'Controleren', every: ['15 min', '30 min', '1 uur'], rows: [{ label: 'Taal', value: 'Nederlands' }, { label: 'AI-koppeling', value: 'Verbonden' }] },
  },
};

const en: typeof nl = {
  meta: {
    title: 'VeiligBuiten — Live safety alerts around you',
    description: 'Know what is happening around you. Live alerts on fires, violence, bomb threats and severe weather from official sources and people nearby. € 0.99 per month.',
  },
  nav: { how: 'How it works', alerts: 'Alerts', ai: 'AI', price: 'Pricing', download: 'Download' },
  stores: { appleTop: 'Download on the', googleTop: 'Get it on' },
  hero: {
    eyebrow: 'Live safety alerts around you',
    title: 'Be safe.',
    lead: 'Fires, violence, bomb threats, severe weather: VeiligBuiten shows you what is happening around you, live from official sources and from people nearby. You only get an alert when it matters.',
    price: '€ 0.99 per month · no ads',
    chipConfirmed: 'Confirmed 12×',
    chipSource: 'Police · 2 min ago',
  },
  sources: { label: 'Live connected', users: '+ reports from people nearby', more: 'And we keep adding more.' },
  how: {
    eyebrow: 'How it works',
    title: 'See. Report. Confirm.',
    steps: [
      { title: 'See what is going on', text: 'One calm map with everything happening right now within 30 km. Red means danger, orange means watch out and blue is good to know.' },
      { title: 'Report in three taps', text: 'Pick what you see and where. You can post three reports a day, so the map stays reliable.' },
      { title: 'Confirmed together', text: 'The more people confirm a report, the bigger the dot. People a little further away then get an alert too.' },
    ],
  },
  alerts: {
    eyebrow: 'Alerts',
    title: 'A heads-up.\nNot spam.',
    lead: 'Severity decides how you hear about it. We bundle minor updates, and you never get the same alert twice.',
    points: [
      { title: 'Danger', text: 'Instant, with sound. Even through Focus mode.' },
      { title: 'Watch out', text: 'A normal notification, only when it is close.' },
      { title: 'Good to know', text: 'Silent, bundled into one message.' },
    ],
  },
  places: {
    eyebrow: 'Your places',
    title: 'Home, work, school.\nYour call.',
    lead: 'Save your places, choose what you want alerts for and how often your phone checks by itself. In English or Dutch.',
    points: ['Alerts for your own places', 'Filters per type of situation', 'Choose how often it checks', 'English or Nederlands'],
  },
  ai: {
    eyebrow: 'Works with your AI',
    title: 'Just ask\nyour assistant.',
    lead: 'Connect your account with one endpoint to ChatGPT, Claude or any other assistant that supports MCP. Your assistant then knows your places and what is going on. If you run your own agent, it gets a heads-up through a webhook.',
    endpoint: 'MCP endpoint',
    webhook: 'Webhook',
    webhookValue: 'https://your-agent.app/hooks/veiligbuiten',
    chat: {
      assistant: 'Your assistant',
      question: 'Is it calm around Amsterdam Centraal? I will be there in 20 minutes.',
      tool: 'veiligbuiten · situations nearby',
      answer: 'Not entirely. 12 minutes ago there was a bomb threat at the east entrance (Police, confirmed 12×). That part is cordoned off. Use the west entrance instead, it is calm there.',
    },
  },
  trust: [
    { title: 'Your location stays with you', text: 'Every hour the app fetches the situation within 30 km and checks it on your phone. Your location is never sent live.' },
    { title: 'Light on battery', text: 'Few requests, little battery. You decide how often it checks in the background.' },
    { title: 'Call 112 in an emergency', text: 'VeiligBuiten helps you look ahead. In immediate danger, always call 112.' },
  ],
  price: {
    eyebrow: 'Pricing',
    amount: '€ 0.99',
    per: 'per month',
    title: 'Everything. For less than a euro.',
    items: ['Live map and alerts', 'All your places and filters', 'Connect your AI assistant', 'No ads', 'Cancel any month'],
    note: 'No trial, no fine print.',
  },
  footer: { motto: 'Be safe.', emergency: 'In immediate danger: always call 112.', privacy: 'Privacy', terms: 'Terms', contact: 'Contact' },
  scene: {
    here: 'Amsterdam Centre',
    nearby: 'Nearby',
    tabs: ['Map', 'Places', 'Account'],
    items: [
      { title: 'Bomb threat', meta: '350 m · 2 min · 12×', tone: 'danger' },
      { title: 'Fight', meta: '1.2 km · 18 min · 4×', tone: 'warn' },
      { title: 'Road closed', meta: '600 m · NDW', tone: 'info' },
    ],
    notifTitle: 'Bomb threat · Amsterdam Centraal',
    notifBody: '350 m from you · Police · confirmed 12×',
    now: 'now',
    report: { title: 'What do you see?', cats: ['Fire', 'Violence', 'Suspicious', 'Traffic', 'Weather', 'Other'], sub: 'Suspicious', subs: ['Suspicious object', 'Suspicious situation', 'Pursuit'], done: 'Posted', doneText: 'People nearby can now confirm it.', left: '2 of 3 today' },
    confirm: { label: 'confirmed', reach: 'Alerts up to' },
    lock: { time: '21:47', date: 'Thursday 23 October', n1: 'Road closed Prins Hendrikkade', n1b: '600 m · NDW', n2: '3 updates near you', n2b: 'Gas smell, oil spill and 1 more', n3: 'Bomb threat · Amsterdam Centraal', n3b: '350 m · Police · confirmed 12×' },
    placesScene: { title: 'Places', list: [{ name: 'Home', area: 'Jordaan', r: '1 km' }, { name: 'Work', area: 'Zuidas', r: '2 km' }, { name: 'School', area: 'Oost', r: '500 m' }], filters: 'Alerts for', chips: ['Danger', 'Violence', 'Fire', 'Traffic', 'Weather', 'Info'], check: 'Check every', every: ['15 min', '30 min', '1 hour'], rows: [{ label: 'Language', value: 'English' }, { label: 'AI connection', value: 'Connected' }] },
  },
};

export const siteCopy: Record<Locale, typeof nl> = { nl, en };
export type SiteCopy = typeof nl;
