# eLeDia.OS — Master

## 1. Project Meta

- **Name:** local_eledia_exam2pdf
- **Goal:** PDF-Compliance-Nachweis für bestandene Moodle-Quizversuche
- **Short Description:** Erzeugt nach jedem bestandenen Quizversuch automatisch ein PDF-Dokument mit Nachweisdaten, Fragen und Antworten des Lernenden. Download und E-Mail-Versand konfigurierbar.
- **Tech Stack:** Moodle 4.3+ (PHP 8.1+), TCPDF, Moodle File API, Moodle Hooks API, Moodle Events API, Scheduled Tasks

---

## 2. Session Start (for AI)

1. Read this document completely
2. Read `04-tasks.md`
3. Identify open tasks (taskXX)
4. Read relevant features in `01-features.md`
5. Start with the highest-priority task

---

## 3. File System

| File | Purpose |
|------|--------|
| 01-features.md | What we build and why (intended behavior) |
| 02-user-doc.md | User perspective and usage |
| 03-dev-doc.md | Technical implementation (actual state) |
| 04-tasks.md | Tasks and operational workflow |
| 05-quality.md | Bugs and test results |

---

## 4. ID System

- `feat01` → feature  
- `task01` → task  
- `bug01` → bug  
- `test01` → test  

**Example:**  
feat01 → task02 → bug01 → test03

---

## 5. 🔁 Workflow

Idea → Feature → Task → Implementation → Test → Bug → Fix → Done → Documentation Sync

---

## 6. 🔥 Core Rule (Definition of Done)

A feature is only considered **done** when all perspectives are consistent:

- Feature defined in `01-features.md`
- User documentation in `02-user-doc.md`
- Implementation documented in `03-dev-doc.md`
- All tasks (taskXX) completed
- No blocking bugs (bugXX)
- Tests (testXX) passing

---

## 7. 🚀 Recommended Daily Workflow

1. `#status` — Übersicht verschaffen
2. `#next` — Nächste Aufgabe identifizieren
3. `#implement` — Task umsetzen
4. `#test` — Verhalten prüfen
5. `#doc` — Dokumentation synchronisieren

---

## 8. 🤖 Prompt Shortcuts

| Prompt | Aktion |
|--------|--------|
| `#status` | Gesamtüberblick: Features, Tasks, Bugs, Risiken |
| `#next` | 1–3 nächste sinnvolle Tasks identifizieren |
| `#plan featXX` | Feature in Tasks aufteilen |
| `#implement taskXX` | Task schrittweise umsetzen |
| `#test featXX` | Testfälle für Feature entwerfen |
| `#bugs` | Bugs analysieren und priorisieren |
| `#doc` | Konsistenz aller Dokumente prüfen |
| `#userdoc` | Nutzerdokumentation schreiben |
| `#devdoc` | Entwicklerdokumentation schreiben |
| `#review` | Lösung evaluieren (Risiken, Komplexität) |
