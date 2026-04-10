# Quality — local_eledia_exam2pdf

## Meta

Dieses Dokument verfolgt Bugs und Testergebnisse.

Nur reproduzierbare Probleme und Testergebnisse gehören hierher — keine Ideen oder Tasks.

---

## 🐞 Bugs

_(noch keine bekannten Bugs — Plugin im MVP-Scaffold-Stadium)_

---

## 🧪 Tests

### test01 Smoketest: PDF-Erzeugung nach bestandenem Versuch

Feature: feat01
Status: pending (wartet auf lokales Deployment)

**Schritte**
1. Plugin in lokalem Moodle installieren (DB-Upgrade prüfen)
2. Test-Quiz mit Bestehensgrenze (z.B. 50%) anlegen
3. Als Lernender einloggen, Quiz starten und **bestehen**
4. Quiz-Review-Seite öffnen

**Erwartetes Ergebnis**
- Download-Button sichtbar und aktiv auf Review-Seite
- Klick auf Button → PDF wird heruntergeladen
- PDF enthält: Name, Quiz-Name, Bestanden: Ja
- DB-Eintrag in `local_eledia_exam2pdf` vorhanden
- Datei im Moodle File System unter Quiz-CM-Kontext gespeichert

**Tatsächliches Ergebnis**
_(noch nicht durchgeführt)_

---

### test02 Negativtest: Nicht bestandener Versuch

Feature: feat01, feat06
Status: pending

**Schritte**
1. Als Lernender Quiz starten und **nicht bestehen**
2. Quiz-Review-Seite öffnen

**Erwartetes Ergebnis**
- Download-Button deaktiviert (grau) mit Text „Zertifikat nicht verfügbar (Versuch nicht bestanden)"
- Kein DB-Eintrag in `local_eledia_exam2pdf`
- Kein PDF im File System

---

### test03 E-Mail-Modus

Feature: feat02
Status: pending

**Schritte**
1. Outputmode auf „E-Mail" oder „Beides" stellen
2. Quiz bestehen

**Erwartetes Ergebnis**
- E-Mail mit PDF-Anhang im Postfach des Lernenden
- Betreff entspricht konfiguriertem Template
- PDF-Datei als Anhang korrekt

---

### test04 Mehrere bestandene Versuche

Feature: feat01
Status: pending

**Schritte**
1. Quiz dreimal bestehen (mit mehreren Versuchen erlauben)

**Erwartetes Ergebnis**
- 3 separate DB-Einträge in `local_eledia_exam2pdf`
- 3 separate PDF-Dateien im File System
- Für jeden Versuch eigener Download-Button auf der jeweiligen Review-Seite

---

### test05 Cleanup Task

Feature: feat05
Status: pending

**Schritte**
1. Aufbewahrungsfrist auf 1 Tag setzen
2. Quiz bestehen → PDF erzeugt
3. DB: `timeexpires` auf Vergangenheit setzen (manuell für Test)
4. Scheduled Task manuell ausführen: `php admin/cli/scheduled_task.php --execute='\local_eledia_exam2pdf\task\cleanup_expired_pdfs'`

**Erwartetes Ergebnis**
- DB-Eintrag gelöscht
- Datei im File System gelöscht
- Download-Button zeigt danach deaktivierten Zustand

---

### test06 Per-Quiz-Config Override

Feature: feat04
Status: pending

**Schritte**
1. Globaler Outputmode: „Download"
2. Für spezifisches Quiz: Outputmode auf „E-Mail" stellen
3. In diesem Quiz einen Versuch bestehen

**Erwartetes Ergebnis**
- Nur E-Mail wird versendet, kein Download-Button aktiv
- Anderes Quiz: Download-Button erscheint wie global konfiguriert

---

## 📋 Qualitätsziele Sprint 2

- [ ] Alle 6 Tests oben durchgeführt und bestanden
- [ ] PHPCS: 0 Fehler mit Moodle-Coding-Standards
- [ ] PHPDoc: alle public methods dokumentiert
- [ ] Behat: Happy Path (feat01, feat06) automatisiert
- [ ] PHPUnit: generator.php und helper.php abgedeckt
