# User Documentation — local_eledia_exam2pdf

## Für wen ist dieses Dokument?

Dieses Dokument beschreibt das Plugin aus Nutzersicht — für Lernende, Trainer/innen und Administrator/innen.

---

## Lernende

### Wann erhalte ich ein PDF-Zertifikat?

Ein PDF wird automatisch erzeugt, wenn du einen Quiz-Versuch **bestanden** hast. Du musst nichts tun — das Plugin arbeitet im Hintergrund.

Bei nicht bestandenen Versuchen wird kein PDF erzeugt oder freigegeben.

### Wie komme ich an mein PDF?

Je nach Konfiguration durch deine/n Administrator/in:

**Download:**
Nach Abschluss des Quiz siehst du auf der Ergebnisseite einen blauen Button **„PDF-Zertifikat herunterladen"**. Klicke darauf, um das PDF direkt herunterzuladen.

Ist der Versuch nicht bestanden, ist der Button grau und deaktiviert mit dem Hinweis „Zertifikat nicht verfügbar (Versuch nicht bestanden)".

**E-Mail:**
Du erhältst das PDF als Anhang an deine Moodle-E-Mail-Adresse — direkt nach Abschluss des Quiz.

### Was steht im PDF?

Das PDF enthält immer:
- Deinen Namen
- Den Namen des Quiz
- „Bestanden: Ja"

Je nach Konfiguration zusätzlich:
- Deine erreichten Punkte und die Gesamtpunktzahl
- Die Bestehensgrenze
- Die Prozentzahl
- Datum und Uhrzeit des Versuchs
- Die Dauer des Versuchs
- Die Versuchsnummer

Außerdem alle Fragen des Quiz mit deinen Antworten — und (falls aktiviert) die korrekte Lösung.

### Kann ich mehrere PDFs haben?

Ja. Für jeden **bestandenen** Versuch wird eine eigene PDF erzeugt. Hast du ein Quiz dreimal bestanden, hast du drei PDFs.

---

## Trainer/innen

### Wie aktiviere ich das Plugin für ein Quiz?

Das Plugin ist global aktiv. Über **Quiz → Einstellungen → PDF Certificate settings** kannst du das Verhalten für ein einzelnes Quiz anpassen:

- Ausgabemodus: Download / E-Mail / Beides
- E-Mail-Empfänger (zusätzlich zum Lernenden)
- E-Mail-Betreff
- Aufbewahrungsfrist in Tagen
- Optionale PDF-Felder
- Korrekte Antworten anzeigen: Ja / Nein

Leere Felder übernehmen den globalen Standard.

### Wer sieht welche PDFs?

- Lernende sehen nur ihre eigenen PDFs (nur nach bestandenem Versuch)
- Trainer/innen mit der Berechtigung „Manage" können alle PDFs eines Quiz sehen (Verwaltungsseite in Planung)

### Wie lange werden PDFs aufbewahrt?

Je nach Konfiguration (global oder pro Quiz). Standard: 365 Tage ab dem Datum des bestandenen Versuchs. Nach Ablauf wird die Datei automatisch gelöscht.

---

## Administrator/innen

### Globale Konfiguration

**Website-Administration → Plugins → Lokale Plugins → Quiz PDF Certificate (eLeDia)**

| Einstellung | Beschreibung |
|------------|-------------|
| Ausgabemodus | Download, E-Mail oder Beides |
| E-Mail-Empfänger | Kommagetrennte Adressen (zusätzlich zum Lernenden) |
| E-Mail-Betreff | Unterstützt `{quizname}` und `{username}` |
| Aufbewahrungsfrist | Tage ab bestandenem Versuch (0 = unbegrenzt) |
| Optionale Felder | Welche Zusatzfelder im PDF erscheinen |
| Korrekte Antworten | Im PDF anzeigen oder nicht |

### Installation

```bash
# Plugin-Ordner nach local/eledia_exam2pdf kopieren
# Dann Moodle-Upgrade ausführen:
php admin/cli/upgrade.php
```

Anschließend **Website-Administration → Benachrichtigungen** aufrufen.

### Scheduled Task

Der Cleanup-Task läuft täglich um 02:30 Uhr und löscht alle PDFs, deren Aufbewahrungsfrist abgelaufen ist. Er kann auch manuell ausgeführt werden:

```bash
php admin/cli/scheduled_task.php --execute='\local_eledia_exam2pdf\task\cleanup_expired_pdfs'
```

### Berechtigungen

| Capability | Beschreibung | Standard |
|-----------|-------------|---------|
| `local/eledia_exam2pdf:downloadown` | Eigenes PDF herunterladen | Student, Teacher |
| `local/eledia_exam2pdf:manage` | Alle PDFs verwalten | Editing Teacher, Manager |
| `local/eledia_exam2pdf:configure` | Per-Quiz-Settings ändern | Editing Teacher, Manager |
