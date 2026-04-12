# Developer Documentation — eLeDia | exam2pdf

## Architecture Overview

Das Plugin ist ein `local_`-Plugin, das sich über Events, Hooks und eine eigene Report-Seite in den Moodle-Quiz-Ablauf einklinkt.

```
┌─────────────────────────────────────────────────────────┐
│  TEACHER-PFAD (Hauptpfad)                               │
│                                                         │
│  Quiz → Results → "PDF-Reports" (Navigation-Link)       │
│          ↓                                              │
│  report.php (eigene Seite)                              │
│  ├── Versuchs-Tabelle mit PDF-Spalte (per-TN-Button)   │
│  ├── Bulk-Download-Button                               │
│  └── Filter: "What to include"                          │
│          ↓                                              │
│  download.php / zip.php (Dateiauslieferung)             │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  STUDENT-PFAD (optional, feat09)                        │
│                                                         │
│  Quiz → Review-Seite                                    │
│          ↓                                              │
│  hook: before_footer_html_generation                    │
│  → quiz_page_callbacks::inject_download_button()        │
│  → Prüft: studentdownload aktiv? + PDF-Scope? + PDF?   │
│          ↓                                              │
│  download.php (Dateiauslieferung)                       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  PDF-ERZEUGUNG                                          │
│                                                         │
│  Auto-Modus:                                            │
│  attempt_submitted Event → observer → generator → File  │
│                                                         │
│  On-demand-Modus:                                       │
│  Button-Klick (report.php/review) → generator → File    │
└─────────────────────────────────────────────────────────┘
```

---

## File Structure

```
local/eledia_exam2pdf/
├── classes/
│   ├── helper.php                     # Config-Merge, File-URL, Scope-Check
│   ├── observer.php                   # Event-Listener: attempt_submitted
│   ├── hook/
│   │   └── quiz_page_callbacks.php    # Hooks API: Student-Download-Button
│   ├── pdf/
│   │   └── generator.php             # TCPDF-Erzeugung, Frage-Rendering
│   ├── privacy/
│   │   └── provider.php              # GDPR Privacy API
│   ├── form/
│   │   └── quizsettings.php          # Per-Quiz-Config-Formular
│   └── task/
│       └── cleanup_expired_pdfs.php  # Scheduled Task: Bereinigung
├── db/
│   ├── access.php                    # Capabilities
│   ├── events.php                    # Event-Observer-Registrierung
│   ├── hooks.php                     # Hooks API Callbacks
│   ├── install.xml                   # XMLDB-Schema (2 Tabellen)
│   └── tasks.php                     # Scheduled Task Definition
├── lang/en/
│   └── local_eledia_exam2pdf.php     # Alle Strings
├── pix/icon.svg                      # Plugin-Icon
├── tests/
│   ├── behat/
│   │   ├── behat_local_eledia_exam2pdf.php  # Custom Behat steps
│   │   ├── admin_settings.feature
│   │   └── download_button.feature
│   ├── observer_test.php
│   ├── helper_test.php
│   ├── privacy/provider_test.php
│   └── task/cleanup_expired_pdfs_test.php
├── download.php                      # File-Serve (Einzel-PDF)
├── zip.php                           # Bulk-Download (ZIP/zusammengefügt)
├── report.php                        # Teacher Report-Seite (NEU, feat07)
├── lib.php                           # pluginfile(), Navigation-Hooks
├── quizsettings.php                  # Per-Quiz-Konfigurationsformular
├── settings.php                      # Globale Admin-Settings
└── version.php                       # Plugin-Metadaten
```

---

## Database Schema

### `local_eledia_exam2pdf`
| Feld | Typ | Beschreibung |
|---|---|---|
| id | INT PK | Auto-increment |
| quizid | INT | Referenz auf `quiz.id` |
| cmid | INT | Course Module ID des Quiz |
| attemptid | INT | Referenz auf `quiz_attempts.id` |
| userid | INT | Referenz auf `user.id` |
| timecreated | INT | Unix-Timestamp: PDF erzeugt |
| timeexpires | INT | Unix-Timestamp: Ablauf (0 = nie) |
| contenthash | CHAR(40) | SHA1-Hash der gespeicherten Datei |

### `local_eledia_exam2pdf_cfg`
| Feld | Typ | Beschreibung |
|---|---|---|
| id | INT PK | Auto-increment |
| quizid | INT | Referenz auf `quiz.id` |
| name | CHAR(100) | Config-Key |
| value | TEXT | Config-Wert |

Unique Index auf `(quizid, name)`.

---

## Key Components

### `report.php` (NEU — feat07)
Teacher-Report-Seite, verlinkt aus der Quiz-Navigation:
1. Capability-Check: `downloadall` erforderlich
2. Lädt alle Versuche des Quiz (wie Grades-Tabelle)
3. Filtert nach "What to include"-Optionen
4. Rendert Tabelle mit: Name, E-Mail, Status, Started, Completed, Duration, Grade, **PDF-Button**
5. PDF-Button: Link auf `download.php?attemptid=X` (bzw. On-demand-Generierung)
6. Bulk-Button: Link auf `zip.php?quizid=X&format=zip|merged`

Navigation-Hook in `lib.php`:
```php
// extend_settings_navigation() — fügt "PDF-Reports" unter Results ein
$reportsnode->add(
    get_string('report_title', 'local_eledia_exam2pdf'),
    new moodle_url('/local/eledia_exam2pdf/report.php', ['id' => $cmid]),
    navigation_node::TYPE_SETTING
);
```

### `observer.php`
Reagiert auf `\mod_quiz\event\attempt_submitted` (nur im Auto-Modus):
1. Prüft Config: `pdfgeneration === 'auto'`
2. Prüft Scope via `helper::is_in_pdf_scope()`
3. Verhindert Duplikate via DB-Check
4. Ruft `pdf\generator::generate()` auf
5. Speichert Datei via Moodle File API
6. Schreibt DB-Eintrag
7. Bei E-Mail-Modus: `observer::send_email()`

### `pdf\generator.php`
- Verwendet `\TCPDF` aus `$CFG->libdir . '/tcpdf/tcpdf.php'`
- `generate(quiz_attempt, quiz, config)` → gibt PDF als String zurück
- Kopfblock: Pflichtfelder + konfigurierbare Optionalfelder
- Fragenblock: Iteration über `$quba->get_slots()`

### `helper.php`
- `get_effective_config(quizid)`: Merged globale Config mit per-Quiz-Overrides
- `is_in_pdf_scope(attempt, config)`: Zentrale Scope-Prüfung (bestanden/alle)
- `get_download_url(record, filename)`: Erzeugt pluginfile.php-URL
- `get_stored_file(record)`: Lookup der Datei im Moodle File System

### `hook/quiz_page_callbacks.php`
Student-Self-Service (feat09):
- Prüft `$PAGE->pagetype === 'mod-quiz-review'`
- Prüft Config: `studentdownload === '1'`
- Prüft Scope via `helper::is_in_pdf_scope()`
- Rendert Download-Button oder deaktivierten Button

### `zip.php` (feat08)
Bulk-Download-Endpoint:
- Capability-Check: `downloadall`
- Sammelt alle PDFs der gefilterten Versuche
- Im On-demand-Modus: fehlende PDFs automatisch erzeugen
- Format je nach Config: ZIP oder zusammengefügtes PDF via TCPDF

---

## Configuration System

Zweistufig: globale Plugin-Einstellungen + per-Quiz-Overrides.

```php
// Globale Config
get_config('local_eledia_exam2pdf', 'pdfgeneration') // 'auto' | 'ondemand'
get_config('local_eledia_exam2pdf', 'pdfscope')      // 'passed' | 'all'
get_config('local_eledia_exam2pdf', 'studentdownload') // '1' | '0'
get_config('local_eledia_exam2pdf', 'bulkformat')     // 'zip' | 'merged'

// Merged
$config = helper::get_effective_config($quizid);
```

---

## Event & Hook Registration

```php
// db/events.php
$observers = [[
    'eventname' => '\mod_quiz\event\attempt_submitted',
    'callback'  => '\local_eledia_exam2pdf\observer::on_attempt_submitted',
    'internal'  => false,
]];

// db/hooks.php (Moodle 4.3+)
$callbacks = [[
    'hook'     => \core\hook\output\before_footer_html_generation::class,
    'callback' => '\local_eledia_exam2pdf\hook\quiz_page_callbacks::inject_download_button',
    'priority' => 500,
]];
```

---

## Privacy API

Implementiert `\core_privacy\local\metadata\provider`, `plugin\provider`, `core_userlist_provider`.

Personenbezogene Daten: DB-Eintrag (userid, attemptid, quizid, timestamps) + PDF-Datei (Name, Quizantworten).

---

## Known Limitations & Open Items

- E-Mail-Body nicht konfigurierbar (fest codiert)
- PDF-Dateiname nicht konfigurierbar
- Keine konfigurierbaren PDF-Templates / Branding
- Bulk-Download bei sehr vielen TN (>500) ggf. Timeout → Async-Job als Backlog
