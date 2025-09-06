# DynDNS Manager (Hetzner DNS · PHP · MySQL)

Ein selbst gehosteter DynDNS-Dienst mit **User- und Admin-Login**, **FRITZ!Box-kompatiblem Update-Endpoint**, **Hetzner DNS API-Integration**, **E-Mail-Verifizierung**, **jährlicher Bestätigung (Reminder, Deaktivierung, Cleanup & Löschung)**, **Branding/HTML-Mails** sowie optionaler **Google AdSense**-Einbindung.

## Inhalt
- [Features](#features)
- [Systemvoraussetzungen](#systemvoraussetzungen)
- [Architektur](#architektur)
- [Schnellstart](#schnellstart)
- [Konfiguration (.env)](#konfiguration-env)
- [Datenbank](#datenbank)
- [Hetzner DNS](#hetzner-dns)
- [DynDNS-Update-Endpoint](#dyndns-update-endpoint)
- [FRITZ!Box – Einrichtung](#fritzbox--einrichtung)
- [E-Mail-Flows](#e-mail-flows)
- [Jährliche Bestätigung (Cron)](#jährliche-bestätigung-cron)
- [Branding & HTML-Mails](#branding--html-mails)
- [Google AdSense (optional)](#google-adsense-optional)
- [Admin-Funktionen](#admin-funktionen)
- [Sicherheitshinweise](#sicherheitshinweise)
- [Deployment-Hinweise](#deployment-hinweise)
- [Roadmap / Ideen](#roadmap--ideen)
- [Lizenz](#lizenz)

---

## Features
- **User/Registrierung/Login**
  - E-Mail-Verifizierung (Konto bis dahin inaktiv)
  - Benutzer kann mehrere Subdomains anlegen
- **Admin**
  - Domains (Zonen) verwalten (Hetzner Zone-ID)
  - Benutzer löschen (inkl. Hetzner-DNS-Cleanup)
  - Jahrespflicht pro User deaktivieren
- **DynDNS**
  - FRITZ!Box-kompatibler Update-Endpoint (`/dyndns/update.php`)
  - A/AAAA Records bei Hetzner anlegen/aktualisieren
  - Globale **TTL** via `.env` (für Nutzer versteckt)
  - **Subdomain-Blacklist** via `.env`
- **Automatisierung**
  - Jährliche Bestätigung: T-14 Reminder, T0 Reminder + Deaktivierung, T+1 Cleanup & Account-Löschung
  - Testmodus per CLI-Flags (dry-run, gezielte Phasen)
- **Mails**
  - HTML + Plaintext (multipart/alternative)
  - Branding (Logo, Farben, Impressum/Datenschutz/AGB-Links)
- **UI**
  - Schlankes HTML5/CSS-Design, Header/Footer-Partials
  - Optional **Google AdSense**

---

## Systemvoraussetzungen
- PHP **8.1+** (mit `curl`, `mbstring`, `openssl`)
- MySQL/MariaDB (UTF-8mb4)
- Webserver (Apache/Nginx) – DocumentRoot → `public/`
- Internetzugang zum **Hetzner DNS API** Endpoint
- (Optional) funktionierendes Mail-Setup (`mail()`), alternativ SMTP-Versand (erweiterbar)

---

## Architektur
```

/assets/               # CSS etc.
/lib/
Auth.php            # Registrierung, Session-Login-Hilfen
HetznerClient.php   # API-Client (create/update/delete records)
Util.php            # Helpers: .env, CSRF, Mail (HTML), Branding, ...
/public/
index.php           # Landing / Erklärung
register.php        # Registrierung + Initial-DNS
login.php           # Login + "Link erneut senden"
verify.php          # E-Mail-Verifikation
confirm.php         # Jährliche Bestätigung per Token
dashboard.php       # Benutzer-Dashboard
subdomains.php      # Subdomain-Verwaltung (User)
admin.php           # Admin-Panel
logout.php
/dyndns/update.php  # FRITZ!Box-kompatibler Update-Endpoint
/partials/          # brand.php, footer.php, ads\_head.php, ad\_slot.php
/cron/daily.php       # T-14/T0/T+1 E-Mails, Deaktivierung, Cleanup
/sql/migrations.sql   # Tabellen & Indizes
.env.example
.htaccess             # Schutz sensibler Dateien, Security-Header
bootstrap.php         # .env + PDO + Includes

````

---

## Schnellstart
1. **Code deployen** und Webserver auf `public/` zeigen lassen.  
2. `.env` aus `.env.example` kopieren und **ausfüllen** (siehe unten).  
3. **Datenbank migrieren**:
   ```bash
   mysql -u <user> -p <db> < sql/migrations.sql
````

4. **Admin** anlegen (Passworthash erzeugen):

   ```bash
   php -r 'echo password_hash("SICHERES_PASSWORT", PASSWORD_ARGON2ID), PHP_EOL;'
   ```

   ```sql
   INSERT INTO users (email, username, password_hash, role, is_active, ddns_token, annual_confirm_due, created_at, updated_at)
   VALUES ('admin@example.com','admin','<HASH>','admin',1,HEX(RANDOM_BYTES(32)), CURDATE() + INTERVAL 1 YEAR, NOW(), NOW());
   ```
5. Im **Admin-Panel** Hauptdomain(en) samt **Hetzner Zone-ID** anlegen.
6. **Cron** einrichten (z. B. 02:30 täglich):

   ```cron
   30 2 * * * /usr/bin/php /path/to/app/cron/daily.php >/dev/null 2>&1
   ```
7. **Testen**: Registrierung, E-Mail-Verifikation, Subdomains, FRITZ!Box-Update.

---

## Konfiguration (.env)

Beispielauszug:

```ini
APP_ENV=prod
APP_TIMEZONE=Europe/Luxembourg
BASE_URL=https://dyndns.example.com

DB_HOST=localhost
DB_PORT=3306
DB_NAME=dyndns
DB_USER=dyndns
DB_PASS=secret

HETZNER_DNS_API=https://dns.hetzner.com/api/v1
HETZNER_DNS_TOKEN=REPLACE_ME

# Global TTL (60..86400), nur Admin – Nutzer sehen/ändern TTL nicht.
TTL_DEFAULT=300

# Gesperrte Subdomains (Komma/Leerzeichen/Semikolon getrennt)
SUBDOMAIN_BLACKLIST=www,mail,ftp,admin

# Branding / Mails
BRAND_NAME=DynDNS
BRAND_LOGO_URL=https://example.com/logo.png
BRAND_IMPRINT_URL=https://example.com/impressum
BRAND_PRIVACY_URL=https://example.com/datenschutz
BRAND_TERMS_URL=https://example.com/agb
FOOTER_COPYRIGHT=© 2025 DynDNS · Hetzner DNS API · PHP · MySQL
EMAIL_PRIMARY_COLOR=#4e7be6

MAIL_FROM=noreply@example.com
MAIL_FROM_NAME=DynDNS Service
SUPPORT_EMAIL=support@example.com

# AdSense (optional)
ADSENSE_CLIENT=ca-pub-xxxxxxxxxxxxxxxx
ADSENSE_SLOT_INDEX=
ADSENSE_SLOT_DASHBOARD=

# Nur für Tests: 1 = DNS/DB-Löschungen im Cron überspringen
DRY_RUN_CLEANUP=0
```

---

## Datenbank

**Tabellen**: `users`, `domains`, `records` (FK-CASCADE für Records).
Wichtige Spalten:

* `users.is_active` (0 bis E-Mail verifiziert / bei Fälligkeit deaktiviert)
* `users.email_verification_token` / `email_verified_at`
* `users.annual_confirm_due`, `annual_confirm_token`
* `records.*hetzner_record_id_*`, `last_ipv4`, `last_ipv6`, `ttl`

**ER-Skizze**:

```
users (1) ──< records >── (1) domains
```

---

## Hetzner DNS

* Hole die **Zone-ID** pro Domain im Hetzner DNS Panel.
* Trage die Domain + Zone-ID im **Admin-Panel** ein.
* API-Zugriff via `HETZNER_DNS_TOKEN`.

---

## DynDNS-Update-Endpoint

**Pfad:** `/dyndns/update.php`
**Auth:** `username` + `password` (= `ddns_token`) via Query oder HTTP Basic.

**Parameter (Query):**

* `hostname` (FQDN, z. B. `home.example.com` oder Root `example.com`)
* `myip` (IPv4), `myipv6` (IPv6)
* `username`, `password` (DynDNS-Token)

**Antworten:**

* `good <ip>` – Eintrag aktualisiert/angelegt
* `nochg <ip>` – bereits aktuell / keine IP übergeben
* `badauth` – ungültige Zugangsdaten
* `nohost` – Host nicht gefunden (User/FQDN passt nicht)
* `notfqdn` – `hostname` fehlt/ungültig

---

## FRITZ!Box – Einrichtung

**Internet → Zugangsart → DynDNS → Anbieter: „Benutzerdefiniert“**

* **Update-URL**

  ```
  https://<dein-host>/dyndns/update.php?hostname=<domain>&myip=<ipaddr>&myipv6=<ip6addr>&username=<username>&password=<pass>
  ```

  > Die Platzhalter `<domain>`, `<ipaddr>`, `<ip6addr>`, `<username>`, `<pass>` ersetzt die FRITZ!Box automatisch.
* **Domainname**: FQDN deiner Subdomain (z. B. `home.example.com`)
* **Benutzername**: Dein DynDNS-Benutzer (`users.username`)
* **Kennwort**: Dein **DynDNS-Token** (`users.ddns_token`)

---

## E-Mail-Flows

* **Registrierung**

  * Verifikationsmail (Konto bis dahin inaktiv)
  * Zugangsdaten-Mail (Hinweis: erst nach Verifikation aktiv)
* **Login**

  * Falls inaktiv: Hinweis + „Bestätigungslink erneut senden“
* **Jährliche Bestätigung**

  * T-14 / T0 Reminder
  * T+1 Lösch-Mail

> Versand per PHP `mail()` (multipart/alternative: Text + HTML). SMTP kann bei Bedarf leicht ergänzt werden.

---

## Jährliche Bestätigung (Cron)

**Ablauf (täglich):**

* **T-14**: E-Mail-Erinnerung mit Bestätigungslink (`/confirm.php?t=...`)
* **T0**: E-Mail-Erinnerung + `is_active=0`
* **T+1**: Hetzner-DNS-Cleanup (A/AAAA) → E-Mail → **User löschen** (FK-CASCADE entfernt Records)

**Crontab:**

```cron
30 2 * * * /usr/bin/php /path/to/app/cron/daily.php >/dev/null 2>&1
```

**Testmodus (ohne echte Löschungen/Versand):**

```bash
# T-14 Reminder für speziellen Benutzer (ID) – nur anzeigen
php cron/daily.php --user=123 --phase=T-14 --dry-run=1

# Optional: auch --username=<name> möglich, wenn aktiviert
php cron/daily.php --username=testuser --phase=T0 --dry-run=1
```

> `--dry-run=1` → nur Ausgabe.
> `DRY_RUN_CLEANUP=1` (in `.env`) → nur **Löschungen** aussetzen; Mails gehen im Normalbetrieb dann trotzdem raus.

---

## Branding & HTML-Mails

* **Branding** (.env): `BRAND_NAME`, `BRAND_LOGO_URL`, `EMAIL_PRIMARY_COLOR`, `BRAND_IMPRINT_URL`, `BRAND_PRIVACY_URL`, `BRAND_TERMS_URL`, `FOOTER_COPYRIGHT`
* **HTML-Mails**: einheitliches Template mit Logo, Button/CTA, Footer inkl. Support/Links
* **UI-Branding**: Header/Brand + Footer über Partials (`public/partials/brand.php`, `public/partials/footer.php`)

---

## Google AdSense (optional)

* `.env`: `ADSENSE_CLIENT`, `ADSENSE_SLOT_INDEX`, `ADSENSE_SLOT_DASHBOARD`
* Skript wird nur eingebunden, wenn `ADSENSE_CLIENT` gesetzt ist.
* Debug-Seite: `/ads-debug.php` (zeigt gesetzte Werte, lädt Slots testweise)

> Hinweise: AdSense benötigt Domain-Freigabe/Review; neue Seiten zeigen Anzeigen ggf. erst verzögert.

---

## Admin-Funktionen

* **Domains** hinzufügen (Name + Hetzner Zone-ID)
* **Benutzer löschen** → löscht vorab Hetzner-Records, dann den User
* **Jahrespflicht deaktivieren** für einzelne User (setzt Fälligkeit weit in die Zukunft)
* Übersicht aller **Subdomains mit Benutzerzuordnung**

---

## Sicherheitshinweise

* **Passwörter**: `PASSWORD_ARGON2ID`
* **Tokens**: kryptographisch stark (`random_bytes`)
* **CSRF-Schutz** bei Formularen
* **.env/.sql** werden via `.htaccess` geblockt (Apache). Für Nginx separat schützen.
* **HTTPS** erzwingen (HSTS empfohlen)
* **Rate-Limit**/Brute-Force-Schutz für Login & Update-Endpoint je nach Umgebung ergänzen
* **SPF/DKIM/DMARC** für Mail-Zustellung korrekt setzen
* **Least Privilege** für DB-User & Hetzner-Token

---

## Deployment-Hinweise

* **DocumentRoot** → `/public`
* PHP-OPcache aktivieren
* Logrotation & Monitoring (Cron-Job, Fehlerlogs)
* Backups: DB + ggf. Konfiguration
* Firewall: nur nötige ausgehende Verbindungen (Hetzner DNS API, Mail)

---

## Roadmap / Ideen

* SMTP-Versand (PHPMailer) inkl. `.env`-Variablen
* 2FA für Admin/Benutzer
* Rate-Limiting (z. B. Redis) für Update & Login
* Webhooks/Audit-Log
* UI-Mehrsprachigkeit
* API-Keys pro Subdomain (statt globalem Token)

---

## Lizenz

Wähle eine passende Lizenz (z. B. **MIT**) oder markiere das Repository als **proprietär**.
Beispiel MIT: `LICENSE` mit MIT-Text hinzufügen.

---