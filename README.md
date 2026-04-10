# local_eledia_exam2pdf

**Moodle Plugin** · Typ: `local` · Entwickelt von [eLeDia GmbH](https://eledia.de)

Erzeugt nach jedem bestandenen Quizversuch automatisch ein PDF-Dokument als Compliance-Nachweis. Das PDF enthält verbindliche Nachweisdaten (Name, Quiz, Bestanden) sowie alle Fragen und Antworten des Lernenden. Download und E-Mail-Versand sind konfigurierbar. PDFs werden geschützt im Kontext des jeweiligen Quiz gespeichert und nach einer konfigurierbaren Aufbewahrungsfrist automatisch gelöscht.

---

## Features

- **PDF-Erzeugung** nach jedem bestandenen Quizversuch (TCPDF-basiert)
- **Fragen & Antworten** im PDF: offene Fragen, Single/Multiple Choice mit Richtig/Falsch-Markierung, korrekte Lösung (konfigurierbar)
- **Ausgabemodi**: Download, sofortiger E-Mail-Versand oder beides parallel
- **Konfigurierbare Kopfzeilen**: Pflichtfelder + optionale Felder (Punkte, Prozent, Zeitstempel, Dauer, Versuchsnummer)
- **Per-Quiz-Overrides** für alle Admin-Settings
- **Automatische Bereinigung** abgelaufener PDFs via Scheduled Task (nächtlich 02:30 Uhr)
- **Zugriffskontrolle**: Lernende sehen nur eigene PDFs nach bestandenem Versuch, Trainer/Admins sehen alle
- **Privacy API (GDPR)**: vollständige Export- und Löschfunktion
- **Moodle 4.3+ Hooks API** für UI-Integration (Download-Button auf Quiz-Review-Seite)

---

## Anforderungen

| Komponente | Version |
|-----------|---------|
| Moodle | 4.3+ (empfohlen: 5.0/5.1) |
| PHP | 8.1+ |
| Datenbank | MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 13+ |

---

## Installation

```bash
# In den Moodle-Ordner local/ kopieren
cp -r local_eledia_exam2pdf /path/to/moodle/local/eledia_exam2pdf

# Moodle-Upgrade ausführen
php admin/cli/upgrade.php
```

Anschließend in Moodle: **Website-Administration → Benachrichtigungen** aufrufen, um die Datenbanktabellen anzulegen.

---

## Konfiguration

### Globale Admin-Settings

**Website-Administration → Plugins → Lokale Plugins → Quiz PDF Certificate (eLeDia)**

| Einstellung | Beschreibung | Standard |
|-------------|-------------|---------|
| Ausgabemodus | `Download`, `E-Mail` oder `Beides` | Download |
| E-Mail-Empfänger | Kommagetrennte Adressen (zusätzlich zum Lernenden) | – |
| E-Mail-Betreff | Unterstützt `{quizname}` und `{username}` | `Quiz certificate: {quizname}` |
| Aufbewahrungsfrist (Tage) | 0 = unbegrenzt | 365 |
| Optionale PDF-Felder | Punkte, Bestehensgrenze, Prozent, Zeitstempel, Dauer, Versuchsnummer | alle aktiv |
| Korrekte Antworten zeigen | Zeigt die Musterlösung im PDF | aktiv |

### Per-Quiz-Einstellungen

Im Quiz → **Einstellungen → PDF Certificate settings**: Alle globalen Einstellungen können pro Quiz überschrieben werden. Leere Felder erben den globalen Standard.

---

## Datenbankstruktur

| Tabelle | Inhalt |
|---------|--------|
| `local_eledia_exam2pdf` | Ein Satz pro generiertem PDF (quizid, attemptid, userid, Ablaufzeit) |
| `local_eledia_exam2pdf_cfg` | Per-Quiz-Overrides (name/value-Paare) |

---

## Dateispeicherung

PDFs werden im Moodle File System gespeichert:

- **Component**: `local_eledia_exam2pdf`
- **Filearea**: `attempt_pdf`
- **Context**: Course Module Context des jeweiligen Quiz
- **Zugriff**: via `pluginfile.php` mit Capability-Prüfung

---

## Berechtigungen (Capabilities)

| Capability | Beschreibung | Standard |
|-----------|-------------|---------|
| `local/eledia_exam2pdf:downloadown` | Eigenes PDF herunterladen | Student, Teacher |
| `local/eledia_exam2pdf:manage` | Alle PDFs eines Quiz verwalten | Editing Teacher, Manager |
| `local/eledia_exam2pdf:configure` | Per-Quiz-Einstellungen ändern | Editing Teacher, Manager |

---

## Entwicklung

```
local/eledia_exam2pdf/
├── classes/
│   ├── helper.php              # Config-Merge, File-URL-Generierung
│   ├── observer.php            # Event-Listener: attempt_submitted
│   ├── hook/
│   │   └── quiz_page_callbacks.php   # Download-Button via Hooks API
│   ├── pdf/
│   │   └── generator.php       # TCPDF-basierte PDF-Erzeugung
│   ├── privacy/
│   │   └── provider.php        # GDPR Privacy API
│   └── task/
│       └── cleanup_expired_pdfs.php  # Scheduled Task
├── db/
│   ├── access.php              # Capabilities
│   ├── events.php              # Event-Observer-Registrierung
│   ├── hooks.php               # Hooks API Callbacks
│   ├── install.xml             # XMLDB-Schema
│   └── tasks.php               # Scheduled Tasks
├── lang/en/
│   └── local_eledia_exam2pdf.php
├── pix/icon.svg
├── download.php                # File-Serve mit Zugriffsprüfung
├── lib.php                     # Moodle-Callbacks (pluginfile, navigation)
├── quizsettings.php            # Per-Quiz-Konfigurationsformular
├── settings.php                # Admin-Settings
└── version.php
```

---

## Offene Punkte (Backlog)

- [ ] Admin/Trainer-Übersicht über alle PDFs eines Quiz (`manage.php`)
- [ ] Konfigurierbare E-Mail-Text-Templates
- [ ] Konfigurierbare PDF-Dateinamen
- [ ] Optionale interne PDF-Erzeugung auch für nicht bestandene Versuche
- [ ] Behat-Tests für Happy Path und Edge Cases
- [ ] PHPUnit-Tests für `generator.php` und `helper.php`

---

## Lizenz

GNU General Public License v3 oder höher — siehe [COPYING](https://www.gnu.org/licenses/gpl-3.0.html)

© 2025 eLeDia GmbH
