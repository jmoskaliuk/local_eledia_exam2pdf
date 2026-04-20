# eLeDia.OS — Master (exam2pdf)

## 1. Project Meta

- **Name:** `local_eledia_exam2pdf`
- **Display Name:** `eLeDia | exam2pdf`
- **Goal:** Moodle-Quiz-Auswertungen als PDF erzeugen, verwalten und sicher ausliefern.
- **Primary UX:** Integration in bestehende Quiz-Seiten (Overview + Review), keine separate Report-App.
- **Tech Stack:** Moodle 4.5+, PHP 8.1+, mPDF, Moodle File API, Events API, Hooks API.

---

## 2. Dokumentenablage

Alle DevFlow-Dokumente liegen unter `docs/` (dieses Verzeichnis).

| Datei | Zweck |
|---|---|
| `01-features.md` | Soll-Verhalten / Produktfunktionen |
| `02-user-doc.md` | Nutzerperspektive |
| `03-dev-doc.md` | Technische Implementierung |
| `04-tasks.md` | Operative Aufgabensteuerung |
| `05-quality.md` | Qualität, Bugs, Teststatus |
| `dev-workflow.md` | Entwicklungs- und Deploy-Ablauf |

---

## 3. Session Start (für AI)

1. `00-master.md` lesen
2. `04-tasks.md` lesen
3. offene Aufgaben identifizieren
4. relevante Features in `01-features.md` prüfen
5. Änderungen implementieren + Docs synchron halten

---

## 4. ID-System

- `featXX` → Feature
- `taskXX` → Task
- `bugXX` → Bug
- `testXX` → Test

---

## 5. Definition of Done

Ein Feature ist erst fertig, wenn:
- es in `01-features.md` korrekt beschrieben ist,
- die Nutzerdoku (`02-user-doc.md`) passt,
- die technische Doku (`03-dev-doc.md`) den Ist-Stand trifft,
- die Aufgabenlage (`04-tasks.md`) aktualisiert ist,
- und `05-quality.md` keinen blockierenden offenen Bug enthält.
