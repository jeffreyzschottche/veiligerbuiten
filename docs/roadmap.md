# Veilig Buiten - Roadmap

> Overzicht van wat nog moet gebeuren.

## Prioriteit 0 - Moet voor launch

### 1. Push Notifications afronden

**Status**: Backend klaar, frontend/mobile incompleet

**Wat werkt**:
- `AlertEngine` queue't notificaties
- `PushDelivery` service bestaat
- `Installation` model slaat push tokens op

**Wat jij moet doen** (zie `currentmission.md`):
- [ ] Firebase project aanmaken
- [ ] `google-services.json` en `GoogleService-Info.plist` toevoegen
- [ ] APNs key aanmaken (voor iOS)
- [ ] Credentials in `.env` zetten

**Wat de agent dan doet**:
- [ ] `usePushNotifications.ts` uitbreiden met permission flow
- [ ] Capacitor push plugin configureren
- [ ] Deep link naar incident bij notification click

---

### 2. Ads integratie

**Status**: Niet geïmplementeerd

**Wat jij moet doen** (zie `currentmission.md`):
- [ ] AdMob account aanmaken
- [ ] App registreren (Android + iOS)
- [ ] Ad units maken (banner)
- [ ] IDs noteren

**Wat de agent dan doet**:
- [ ] `@capacitor-community/admob` installeren
- [ ] Banner component bouwen
- [ ] GDPR consent popup

---

## Afgerond

### ~~3. User Dashboard~~ ✅ DONE

- [x] Profiel bewerken (naam, email)
- [x] Wachtwoord wijzigen
- [x] Mijn meldingen overzicht
- [x] Account verwijderen

**Pagina**: `/dashboard`

---

### ~~4. Admin Panel~~ ✅ DONE

- [x] Admin middleware (backend + frontend)
- [x] Dashboard met statistieken (`/admin`)
- [x] Incidenten beheren (`/admin/incidents`)
- [x] Gebruikers beheren (`/admin/users`)
- [x] Meldingen reviewen (`/admin/reports`)

**Hoe admin maken**:
```sql
UPDATE users SET is_admin = true WHERE email = 'jouw@email.nl';
```

---

## Technische schuld - Afgerond

### ~~Rate limiting~~ ✅ DONE
### ~~Model relationships~~ ✅ DONE
### ~~Distance calculation~~ ✅ DONE
### ~~Category config~~ ✅ DONE
### ~~Auth routes~~ ✅ DONE

---

## Prioriteit 2 - Nice to have

### 5. Offline support
- [ ] Service worker voor PWA
- [ ] Offline incident cache

### 6. Real-time updates
- [ ] WebSocket/SSE voor live updates

### 7. Incident clustering
- [ ] Leaflet.markercluster

### 8. Search & filters
- [ ] Tekst zoeken
- [ ] Filter op tijd

---

## Migraties uitvoeren

Er zijn nieuwe migraties. Run:
```bash
cd backend
php artisan migrate
```

Dit voegt toe:
- `user_id` aan `incident_reports` (voor "mijn meldingen")
- `is_admin` aan `users` (voor admin panel)

---

## Quick reference

| Item | Status |
|------|--------|
| P2000 integratie | ✅ Done |
| Rate limiting | ✅ Done |
| GeoUtils/useGeo | ✅ Done |
| CategoryConfig + API | ✅ Done |
| Auth routes | ✅ Done |
| User dashboard | ✅ Done |
| Admin panel | ✅ Done |
| Push notifications | ⏳ Wacht op Firebase credentials |
| Ads | ⏳ Wacht op AdMob account |
