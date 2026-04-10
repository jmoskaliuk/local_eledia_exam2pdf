# Developer Documentation — local_eledia_exam2pdf

## Architecture Overview

Das Plugin ist ein `local_`-Plugin, das sich über Events und Hooks in den Moodle-Quiz-Ablauf einklinkt. Es hat keine eigene Aktivitätsseite, sondern ergänzt bestehende Quiz-Seiten.

```
Quiz: attempt_submitted Event
        ↓
observer::on_attempt_submitted()
        ↓
   Bestanden?  ──Nein──→ Ende
        ↓ Ja
pdf\generator::generate()
        ↓
Moodle File API (speichern)
        ↓
DB: local_eledia_exam2pdf (Eintrag)
        ↓
Outputmode?
  ├─ download: bereit zum Download (Hook zeigt Button)
  └─ email: sofort senden via email_to_user()
```

---

## File Structure

```
local/eledia_exam2pdf/
├── classes/
│   ├── helper.php              # Config-Merge, File-URL, Stored-File-Lookup
│   ├── observer.php            # Event-Listener: attempt_submitted
│   ├── hook/
│   │   └── quiz_page_callbacks.php   # Hooks API: Download-Button injizieren
│   ├── pdf/
│   │   └── generator.php       # TCPDF-PDF-Erzeugung, Frage-Rendering
│   ├── privacy/
│   │   └── provider.php        # GDPR Privacy API
│   └── task/
│       └── cleanup_expired_pdfs.php  # Scheduled Task: Bereinigung
├── db/
│   ├── access.php              # Capabilities
│   ├── events.php              # Event-Observer-Registrierung
│   ├── hooks.php               # Hooks API Callbacks (Moodle 4.3+)
│   ├── install.xml             # XMLDB-Schema (2 Tabellen)
│   └── tasks.php               # Scheduled Task Definition
├── lang/en/
│   └── local_eledia_exam2pdf.php     # Alle Strings
├── pix/icon.svg                # Plugin-Icon
├── download.php                # File-Serve mit Capability-Check
├── lib.php                     # pluginfile(), extend_settings_navigation()
├── quizsettings.php            # Per-Quiz-Konfigurationsformular
├── settings.php                # Globale Admin-Settings
└── version.php                 # Plugin-Metadaten
```

---

## Database Schema

### `local_eledia_exam2pdf`
| Feld | Typ | Beschreibung |
|------|-----|-------------|
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
|------|-----|-------------|
| id | INT PK | Auto-increment |
| quizid | INT | Referenz auf `quiz.id` |
| name | CHAR(100) | Config-Key (z.B. `outputmode`) |
| value | TEXT | Config-Wert |

Unique Index auf `(quizid, name)`.

---

## Key Components

### `observer.php`
Reagiert auf `\mod_quiz\event\attempt_submitted`:
1. Lädt Versuch + Quiz aus DB
2. Prüft Bestandsstatus: `sumgrades / sumgrades * grade >= gradepass`
3. Verhindert Duplikate via DB-Check
4. Ruft `pdf\generator::generate()` auf
5. Speichert Datei via Moodle File API (contextid = CM-Kontext)
6. Schreibt DB-Eintrag in `local_eledia_exam2pdf`
7. Bei E-Mail-Modus: `observer::send_email()` aufrufen

### `pdf\generator.php`
- Verwendet `\TCPDF` aus `$CFG->libdir . '/tcpdf/tcpdf.php'`
- `generate(quiz_attempt, quiz, config)` → gibt PDF als String zurück
- Kopfblock: Pflichtfelder + konfigurierbare Optionalfelder
- Fragenblock: Iteration über `$quba->get_slots()`
  - Antwort: `$qa->get_response_summary()`
  - Korrektheit: `$qa->get_state()->is_correct()` etc.
  - Korrekte Lösung: fragetyp-spezifisch via `get_correct_answer_text()`

**Unterstützte Fragetypen für korrekte Antwort:**
- `multichoice`: Antworten mit `fraction > 0`
- `truefalse`: Antwort mit `fraction == 1.0`
- `shortanswer`, `numerical`: beste Antwort nach `fraction`
- Andere: Fallback auf `get_correct_response()`

### `helper.php`
- `get_effective_config(quizid)`: Merged globale Moodle-Config mit per-Quiz-Overrides
- `save_quiz_config(quizid, values)`: Schreibt/löscht Einträge in `local_eledia_exam2pdf_cfg`
- `get_download_url(record, filename)`: Erzeugt pluginfile.php-URL
- `get_stored_file(record)`: Lookup der Datei im Moodle File System

### `hook/quiz_page_callbacks.php`
Registriert via `db/hooks.php` für `\core\hook\output\before_footer_html_generation`:
- Prüft `$PAGE->pagetype === 'mod-quiz-review'`
- Liest `attemptid` aus URL-Parameter
- Sucht DB-Eintrag für aktuellen User
- Rendert aktiven Download-Button oder deaktivierten Button

### `task/cleanup_expired_pdfs.php`
- Sucht alle Records mit `timeexpires > 0 AND timeexpires <= now()`
- Löscht Moodle-Datei via `$fs->delete_area_files()`
- Löscht DB-Eintrag

### `lib.php — pluginfile()`
Access-Control für `pluginfile.php`:
- Filearea: `attempt_pdf`
- Eigene Dateien: immer erlaubt
- Fremde Dateien: nur mit `local/eledia_exam2pdf:manage`

---

## Configuration System

Zweistufig: globale Moodle-Plugin-Einstellungen + per-Quiz-Overrides.

```php
// Globale Config (admin settings)
get_config('local_eledia_exam2pdf', 'outputmode')

// Per-Quiz Override (DB)
$DB->get_records_menu('local_eledia_exam2pdf_cfg', ['quizid' => $quizid], '', 'name, value')

// Merged (helper::get_effective_config)
$config = helper::get_effective_config($quizid);
// $config['outputmode'] = per-Quiz-Wert oder globaler Default
```

Config-Keys: `outputmode`, `emailrecipients`, `emailsubject`, `retentiondays`, `showcorrectanswers`, `show_score`, `show_passgrade`, `show_percentage`, `show_timestamp`, `show_duration`, `show_attemptnumber`

---

## File Storage

```
Component:  local_eledia_exam2pdf
Filearea:   attempt_pdf
Itemid:     $record->id (aus local_eledia_exam2pdf)
Context:    \core\context\module::instance($cmid)
Filepath:   /
Filename:   quiz-{slug}-attempt-{n}-{Ymd}.pdf
```

Download-URL: via `\moodle_url::make_pluginfile_url()` mit `$forcedownload = true`

---

## Event & Hook Registration

```php
// db/events.php
$observers = [[
    'eventname' => '\mod_quiz\event\attempt_submitted',
    'callback'  => '\local_eledia_exam2pdf\observer::on_attempt_submitted',
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

Personenbezogene Daten:
- DB-Eintrag: userid, attemptid, quizid, timestamps
- Datei: PDF mit Name und Quizantworten

Methoden: `get_contexts_for_userid()`, `export_user_data()`, `delete_data_for_user()`, `delete_data_for_all_users_in_context()`, `delete_data_for_users()`

---

## Known Limitations & Open Items

- Manager-Übersicht über alle PDFs eines Quiz fehlt noch (`manage.php`)
- Keine konfigurierbaren E-Mail-Body-Templates
- Keine konfigurierbaren PDF-Dateinamen
- Behat- und PHPUnit-Tests noch nicht implementiert
- Open Question: interne PDF für nicht bestandene Versuche (admin-only)?
