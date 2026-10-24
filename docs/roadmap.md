# Veiliger Buiten - Roadmap

> Overzicht van wat nog moet gebeuren.

## Prioriteit 0 - Moet voor launch

### 1. Accounts en sleutels

Alle code staat klaar (app, abonnement, push, achtergrondcontrole, AI-koppeling).
Wat nog nodig is staat in `currentmission.md`: Stripe, APNs, Firebase, winkellinks.

### 2. Testen op echte toestellen

- [ ] Achtergrondcontrole op iOS en Android (draait niet in de simulator)
- [ ] Pushes per ernst: geluid, Focus-modus, kanalen op Android
- [ ] Registreren en betalen op de site met Stripe in testmodus, daarna inloggen in de app

---

## Afgerond

### ~~3. User Dashboard~~ ✅ DONE

- [x] Profiel bewerken (naam, email)
- [x] Wachtwoord wijzigen
- [x] Mijn meldingen overzicht
- [x] Account verwijderen

**Pagina**: in de app onder Account (de oude `/dashboard` is vervallen)

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
- [ ] MapLibre-clusters bij veel stippen

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
| Nieuwe app (kaart, melden, plekken, account) | ✅ Done |
| Abonnement € 0,99 (Stripe, via de website) | ⏳ Wacht op Stripe-sleutels |
| Push (APNs direct, FCM) | ⏳ Wacht op sleutels |
| Achtergrondcontrole (lokaal) | ✅ Done, testen op toestel |
| AI-koppeling (MCP + webhook) | ✅ Done |
| NL-Alert, KNMI aardbevingen | ✅ Done |
| NS storingen | ⏳ Wacht op API-sleutel |
