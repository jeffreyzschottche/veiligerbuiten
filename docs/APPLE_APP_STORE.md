# Apple App Store release

## Release-identiteit

De iOS-app heeft bundle-id `nl.veiligbuiten.app`, displaynaam **Veilig Buiten**,
minimum iOS 15 en gebruikt uitsluitend locatie tijdens gebruik. De verplichte
locatietekst staat in `frontend/ios/App/App/Info.plist`.

1. Registreer `nl.veiligbuiten.app` in het Apple Developer-account.
2. Maak de app aan in App Store Connect met exact dezelfde bundle-id.
3. Open `frontend/ios` in Xcode via `npm run cap:ios`, kies je Apple Team en laat
   Xcode automatisch signing beheren.
4. Zet in het target **App** de versie en buildnummer. Houd die gelijk aan
   `MARKETING_VERSION` en `CURRENT_PROJECT_VERSION` in de projectinstellingen.
5. Maak een archive via **Product → Archive** en upload eerst naar TestFlight.

## Verplicht vóór review

- Voeg een privacy policy en support-url toe in App Store Connect.
- Vul App Privacy in: de app verwerkt precieze locatie uitsluitend voor lokale
  incidentweergave en validatie van meldingen; geen foto's, contacten,
  microfoon of achtergrondlocatie.
- Vul de leeftijdsclassificatie en de content-beschrijving feitelijk in:
  veiligheidsinformatie, geen door gebruikers geüploade media of openbare
  profielen.
- Test op een fysiek iPhone-toestel: locatie toestaan/weigeren, kaart laden,
  incidentdetails, melding plaatsen, deeplinks en offline/error states.
- Maak screenshots voor iPhone en, als iPad wordt ondersteund, iPad.

## Pushmeldingen

De native app registreert push-tokens en opent na een tik het juiste incident.
Voor daadwerkelijke iOS-bezorging moet je nog de capability **Push
Notifications** toevoegen aan het App ID en target, een APNs-authentication
`.p8`-key aanmaken en Key ID, Team ID en Bundle ID uitsluitend als
deployment-secrets configureren. Activeer geen background-location capability:
die is niet nodig voor de huidige productbelofte.

## CLI-alternatief

Na `npm run cap:build` kan een CI-machine met Xcode een archive maken:

```sh
xcodebuild -project ios/App/App.xcodeproj -scheme App -configuration Release \\
  -archivePath build/VeiligBuiten.xcarchive archive
```

Gebruik voor export en upload een provisioning-profiel en App Store Connect API-key
uit de beveiligde CI-secretstore; zet deze nooit in de repository.
