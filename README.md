# local_eledia_exam2pdf

**Moodle Plugin** · Type: `local` · Developed by [eLeDia GmbH](https://eledia.de)

Automatically generates a PDF document after each passed quiz attempt as a compliance record. The PDF contains mandatory evidence data (learner name, quiz, pass status) along with all questions and the learner's answers. Download and e-mail delivery are configurable. PDFs are stored securely within the quiz's module context and automatically deleted after a configurable retention period.

---

## Features

- **Automatic PDF generation** after each passed quiz attempt (TCPDF-based)
- **Questions & answers** in the PDF: open-ended, single/multiple choice with correct/incorrect marking, model answer (configurable)
- **Output modes**: download, instant e-mail delivery, or both
- **Configurable header fields**: mandatory fields + optional fields (score, percentage, pass grade, timestamp, duration, attempt number)
- **Per-quiz overrides** — all global settings can be overridden directly in the quiz edit form ("exam2pdf Settings" section)
- **Student self-service** — optional download button on the quiz review page, controllable per quiz
- **Report page** — sortable, paginated table of all generated PDFs for a quiz (initials bar, profile links)
- **"Download PDFs" button** on the standard quiz results page for quick access to the report
- **Bulk download** — download all certificates as a ZIP archive
- **Automatic cleanup** of expired PDFs via scheduled task (nightly at 02:30)
- **Custom PDF layout** — site logo in header, Moodle URL in footer, consistent table styling
- **Access control**: learners see only their own PDFs, trainers/admins see all
- **Privacy API (GDPR)**: full export and deletion support
- **Moodle 4.3+ Hooks API** for UI integration (download button on quiz review page)

---

## Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+ (recommended: 5.0 / 5.1) |
| PHP       | 8.1+ |
| Database  | MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 13+ |

---

## Installation

```bash
# Copy into Moodle's local/ directory
cp -r local_eledia_exam2pdf /path/to/moodle/local/eledia_exam2pdf

# Run the Moodle upgrade
php admin/cli/upgrade.php
```

Then visit **Site administration → Notifications** in Moodle to create the database tables.

---

## Configuration

### Global admin settings

**Site administration → Plugins → Local plugins → eLeDia | exam2pdf**

| Setting | Description | Default |
|---------|-------------|---------|
| PDF generation mode | `On submission (automatic)` or `On demand (on click)` | On submission |
| PDF scope | `Passed attempts only` or `All finished attempts` | Passed only |
| Student may download | Show download button on the quiz review page | Yes |
| Output mode | `Download`, `Email` or `Both` | Download |
| Bulk download format | `ZIP with individual PDFs` or `One merged PDF` | ZIP |
| Default e-mail recipients | Comma-separated addresses (in addition to the learner) | – |
| Default e-mail subject | Supports `{quizname}` and `{username}` placeholders | `Quiz certificate: {quizname}` |
| Retention period (days) | 0 = keep indefinitely | 365 |
| Optional PDF fields | Score, pass grade, percentage, timestamp, duration, attempt number | All enabled |
| Show correct answers | Display model answer alongside the learner's response | Enabled |

### Per-quiz settings

In the quiz's **Edit settings** form, scroll down to the **exam2pdf Settings** section. All global settings can be overridden per quiz. Fields left at "Use global default" inherit the admin setting.

---

## Database structure

| Table | Contents |
|-------|----------|
| `local_eledia_exam2pdf` | One row per generated PDF (quizid, cmid, attemptid, userid, expiry time, content hash) |
| `local_eledia_exam2pdf_cfg` | Per-quiz setting overrides (name/value pairs) |

---

## File storage

PDFs are stored in the Moodle file system:

- **Component**: `local_eledia_exam2pdf`
- **File area**: `attempt_pdf`
- **Context**: Course module context of the quiz
- **Access**: via `pluginfile.php` with capability and setting checks

---

## Capabilities

| Capability | Description | Default roles |
|-----------|-------------|---------------|
| `local/eledia_exam2pdf:downloadown` | Download own PDF certificate | Student, Teacher |
| `local/eledia_exam2pdf:downloadall` | Download all PDFs for a quiz (report page) | Editing Teacher, Manager |
| `local/eledia_exam2pdf:manage` | Manage all PDFs for a quiz | Editing Teacher, Manager |
| `local/eledia_exam2pdf:configure` | Configure per-quiz PDF settings | Editing Teacher, Manager |
| `local/eledia_exam2pdf:generatepdf` | Generate or regenerate PDF certificates | Editing Teacher, Manager |

---

## File structure

```
local/eledia_exam2pdf/
├── classes/
│   ├── form/
│   │   └── quizsettings.php           # Per-quiz settings form (standalone fallback)
│   ├── helper.php                     # Config merge, file URL generation
│   ├── hook/
│   │   └── quiz_page_callbacks.php    # Download button + report button via Hooks API
│   ├── observer.php                   # Event listener: attempt_submitted
│   ├── pdf/
│   │   └── generator.php              # TCPDF-based PDF generation with logo + footer
│   ├── privacy/
│   │   └── provider.php               # GDPR Privacy API
│   ├── table/
│   │   └── pdf_table.php              # Sortable report table (table_sql)
│   └── task/
│       └── cleanup_expired_pdfs.php   # Scheduled task
├── db/
│   ├── access.php                     # Capabilities
│   ├── events.php                     # Event observer registration
│   ├── hooks.php                      # Hooks API callbacks
│   ├── install.xml                    # XMLDB schema
│   └── tasks.php                      # Scheduled tasks
├── lang/
│   ├── de/local_eledia_exam2pdf.php   # German translation
│   └── en/local_eledia_exam2pdf.php   # English strings (base)
├── pix/icon.svg
├── download.php                       # File serve with access checks
├── lib.php                            # Moodle callbacks (pluginfile, navigation, coursemodule form)
├── quizsettings.php                   # Per-quiz config page (standalone fallback)
├── report.php                         # PDF certificates report (sortable table)
├── settings.php                       # Global admin settings
├── version.php
└── zip.php                            # Bulk ZIP download
```

---

## Development

See [dev-workflow.md](dev-workflow.md) for the full development workflow including CI/CD, deployment, and testing.

### Quick iteration loop

```bash
cd ~/Documents/Claude/Projects/local_eledia_examexport
bash bin/sync-mirror.sh
rsync -a --delete .deploy/local/eledia_exam2pdf/ ~/demo/site/moodle/public/local/eledia_exam2pdf/
docker exec -u www-data demo-webserver-1 php /var/www/site/moodle/admin/cli/upgrade.php --non-interactive
docker exec demo-webserver-1 php /var/www/site/moodle/admin/cli/purge_caches.php
```

### CI Pipeline

GitHub Actions runs on every push/PR with 4 matrix cells (Moodle 4.5/5.0/5.1 × PHP 8.1/8.3 × PostgreSQL/MariaDB). Local prechecks via `bin/precheck.sh` use the same `moodle-plugin-ci` commands.

---

## License

GNU General Public License v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html)

© 2025–2026 eLeDia GmbH
