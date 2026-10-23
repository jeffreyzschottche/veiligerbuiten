# Veiligheids- en bewaarbeleid

## Publicatie

- Een externe, geverifieerde bron is `officieel` en mag publiek zichtbaar zijn binnen zijn geldigheid.
- Een eerste gebruikersmelding in `geweld`, `onveilig` of `stoffen` start in `review`; deze veroorzaakt nooit een pushmelding.
- Andere gebruikersmeldingen zijn lokaal zichtbaar als `gemeld`, maar veroorzaken nooit een push zonder onafhankelijke bevestiging.
- Een installatie kan per incident precies één stem uitbrengen: `present` of `resolved`.
- Persoonsgegevens, beschuldigingen, contactinformatie en gevaarlijke instructies mogen niet worden gepubliceerd.

## Bewaring

- Een incident is maximaal 24 uur publiek zichtbaar. Bronnen met een kortere levensduur blijven korter zichtbaar.
- Na verlopen tijd wordt een incident gearchiveerd: het verdwijnt uit alle publieke endpoints, maar observaties blijven beschikbaar voor de vastgestelde interne analyse- en licentietermijn.
- Exacte gebruikerslocatie wordt alleen gebruikt voor validatie, deduplicatie en afstand. Historische analyses gebruiken geaggregeerde gebieden, niet individuele locatiegeschiedenis.
- Ruwe externe payloads zijn intern en worden nooit door de publieke API teruggegeven.
