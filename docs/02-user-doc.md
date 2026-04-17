# User Documentation — eLeDia | exam2pdf

## Zielgruppe

Dieses Dokument beschreibt exam2pdf aus Sicht von:
- Trainer/innen und Manager/innen
- Lernenden
- Administrator/innen

---

## Trainer/innen und Manager/innen

### Wo finde ich exam2pdf?

exam2pdf ist in die Standard-Quizseiten integriert:
- **Quiz → Ergebnisse → Bewertung (Overview)**: zusätzliche Spalte **Actions**
- **Quiz-Overview-Aktionen**: Button für den **Bulk-Download** aller Auswertungen
- **Quiz → exam2pdf Settings**: Quiz-spezifische Overrides

Es gibt **keine separate report.php-Seite** mehr.

### Einzelne Auswertung pro Versuch

In der Übersichtstabelle gibt es pro Versuch in der Spalte **Actions**:
- **Download**: vorhandene Auswertung als PDF laden
- **Regenerieren**: Auswertung neu erzeugen (falls Berechtigung vorhanden)

Im On-demand-Modus wird ein fehlendes PDF beim Klick erzeugt.

### Bulk-Download

Der Bulk-Button lädt alle Auswertungen eines Quiz herunter.
Je nach Einstellung wird ausgegeben:
- ZIP mit einzelnen PDFs
- ein zusammengefügtes PDF

### Quiz-spezifische Einstellungen

Über **exam2pdf Settings** im Quiz können globale Einstellungen überschrieben werden, z. B.:
- Output mode
- Student may download
- Student erhält Auswertung per E-Mail
- PDF-Sprache
- PDF-Fußzeile
- Aufbewahrungsfrist in Tagen

Leere/"global"-Werte übernehmen den Admin-Standard.

---

## Lernende

### Wann sehe ich den Download-Button?

Auf der Quiz-Review-Seite erscheint der Button **Download Auswertung**, wenn:
- `Student darf Auswertung herunterladen` aktiv ist
- der Versuch im konfigurierten Scope liegt
- Sie den Versuch sehen dürfen (eigener Versuch)

### Bekomme ich die Auswertung per E-Mail?

Wenn der Ausgabemodus E-Mail enthält oder die Option "Teilnehmer erhält Auswertung per E-Mail" aktiv ist, wird das PDF per Moodle-Mail versendet.

### Was steht im PDF?

Das PDF enthält:
- Kopfbereich (Teilnehmer/in, Quiz, bestanden, ggf. zusätzliche Felder)
- Test-Navigation/Überblick (Status je Frage)
- Fragen, Antworten, Korrektheit und Punkte je Frage
- optional Korrektantwort und optional Bewertungskommentar

---

## Administrator/innen

### Globale Konfiguration

Pfad: **Website-Administration → Plugins → Lokale Plugins → eLeDia | exam2pdf**

Wichtige Einstellungen:
- PDF generation mode (`auto` / `ondemand`)
- PDF scope (`passed` / `all`)
- Output mode (`download` / `email` / `both`)
- Bulk format (`zip` / `merged`)
- Student darf herunterladen
- Teilnehmer erhält Auswertung per E-Mail
- Aufbewahrungsfrist in Tagen
- optionale PDF-Felder
- PDF-Sprache und PDF-Fußzeile

### Berechtigungen

| Capability | Zweck | Standardrollen |
|---|---|---|
| `local/eledia_exam2pdf:downloadown` | Eigene Auswertung laden | Student, Teacher |
| `local/eledia_exam2pdf:downloadall` | Alle Auswertungen eines Quiz laden | Editing Teacher, Manager |
| `local/eledia_exam2pdf:generatepdf` | Auswertung erzeugen/regenerieren | Editing Teacher, Manager |
| `local/eledia_exam2pdf:configure` | Quiz-spezifische Settings ändern | Editing Teacher, Manager |

### Cleanup-Task

Abgelaufene PDFs werden per Scheduled Task gelöscht.

