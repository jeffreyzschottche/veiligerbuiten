# Current Mission - Handmatige Taken

> Dit zijn dingen die JIJ (de developer) handmatig moet doen, niet de coding agent.

## 1. Firebase Project (voor Push Notifications)

**Stappen**:
1. Ga naar [Firebase Console](https://console.firebase.google.com/)
2. Maak nieuw project "Veilig Buiten"
3. Voeg Android app toe:
   - Package name: `nl.veiligbuiten.app` (check `capacitor.config.json`)
   - Download `google-services.json` → zet in `frontend/android/app/`
4. Voeg iOS app toe:
   - Bundle ID: `nl.veiligbuiten.app`
   - Download `GoogleService-Info.plist` → zet in `frontend/ios/App/App/`
5. Ga naar Project Settings → Cloud Messaging
6. Kopieer Server Key en Sender ID

**In `.env` zetten**:
```env
FIREBASE_PROJECT_ID=veilig-buiten-xxxxx
FIREBASE_SERVER_KEY=AAAAxxxxxxx
FIREBASE_SENDER_ID=123456789
```

---

## 2. Apple Developer (voor iOS Push)

**Stappen**:
1. Ga naar [Apple Developer](https://developer.apple.com/)
2. Certificates, Identifiers & Profiles → Keys
3. Maak nieuwe Key met "Apple Push Notifications service (APNs)"
4. Download `.p8` file
5. Noteer Key ID en Team ID

**In `.env` zetten**:
```env
APNS_KEY_ID=XXXXXXXXXX
APNS_TEAM_ID=XXXXXXXXXX
APNS_KEY_PATH=/path/to/AuthKey_XXXXXX.p8
APNS_BUNDLE_ID=nl.veiligbuiten.app
```

---

## 3. AdMob Account (voor Ads)

**Stappen**:
1. Ga naar [AdMob](https://admob.google.com/)
2. Maak account aan (of gebruik bestaand Google account)
3. Maak nieuwe app aan:
   - Platform: Android + iOS
   - App name: Veilig Buiten
4. Maak Ad Units aan:
   - Banner ad (voor in de lijst)
   - Interstitial ad (optioneel, na X acties)
5. Noteer App ID en Ad Unit IDs

**In `.env` zetten**:
```env
ADMOB_APP_ID_ANDROID=ca-app-pub-xxxxxxxxxxxxxxxx~xxxxxxxxxx
ADMOB_APP_ID_IOS=ca-app-pub-xxxxxxxxxxxxxxxx~xxxxxxxxxx
ADMOB_BANNER_ID_ANDROID=ca-app-pub-xxxxxxxxxxxxxxxx/xxxxxxxxxx
ADMOB_BANNER_ID_IOS=ca-app-pub-xxxxxxxxxxxxxxxx/xxxxxxxxxx
```

**In `capacitor.config.json`**:
```json
{
  "plugins": {
    "AdMob": {
      "appId": "ca-app-pub-xxx"
    }
  }
}
```

---

## 4. App Store / Play Store voorbereiding

### Google Play Console
- [ ] Developer account ($25 eenmalig)
- [ ] App aanmaken
- [ ] Privacy policy URL
- [ ] Screenshots maken

### Apple App Store Connect
- [ ] Developer account ($99/jaar)
- [ ] App aanmaken
- [ ] Privacy policy URL
- [ ] Screenshots maken

---

## Checklist

| Taak | Status |
|------|--------|
| Firebase project aanmaken | ⬜ |
| Android `google-services.json` | ⬜ |
| iOS `GoogleService-Info.plist` | ⬜ |
| Firebase credentials in `.env` | ⬜ |
| Apple Developer APNs key | ⬜ |
| APNs credentials in `.env` | ⬜ |
| AdMob account aanmaken | ⬜ |
| AdMob app registreren | ⬜ |
| AdMob ad units maken | ⬜ |
| AdMob IDs in `.env` | ⬜ |
| Play Store account | ⬜ |
| App Store account | ⬜ |

---

**Als dit klaar is**, kan de coding agent:
- Push notifications implementeren in frontend
- Ads component bouwen
- App builden voor stores
