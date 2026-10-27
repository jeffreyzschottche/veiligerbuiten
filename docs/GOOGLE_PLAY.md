# Google Play release

1. Maak in Play Console de app met package-id `nl.veiligerbuiten.app`.
2. Genereer een upload-keystore en kopieer `android/release.properties.example` naar `android/release.properties`; vul uitsluitend lokaal of via CI-secrets in.
3. Gradle leest `release.properties` vanzelf; bouw een AAB met `./gradlew bundleRelease` vanuit `frontend/android`.
4. Upload eerst naar Internal testing. Test locatie, notificatiepermissie, offline gedrag en meldingflow op een fysiek toestel.
5. Vul in Play Console Data safety, privacy policy, supportadres, content rating en de verklaring voor locatie- en notificatiegebruik in.

De app gebruikt locatie uitsluitend om situaties in de directe omgeving te tonen en een melding binnen 1 km te valideren. Zij gebruikt geen foto-, contact-, microfoon- of achtergrondlocatiepermissie.
