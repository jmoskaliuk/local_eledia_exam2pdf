# Changelog

Alle nennenswerten Änderungen an `local_eledia_exam2pdf` werden in diesem
Dokument festgehalten. Format: [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added
- CI/CD-Pipeline aus `moodle-cicd`-Skill-Baseline: GitHub Actions Matrix
  (`MOODLE_405_STABLE` × pgsql, `MOODLE_500_STABLE` × pgsql, `MOODLE_501_STABLE`
  × pgsql, `MOODLE_501_STABLE` × mariadb)
- Lokaler Precheck `bin/precheck.sh` via `docker exec` gegen moodle-docker-Container
- Release-Pipeline `bin/release.sh` + `.github/workflows/release.yml` für
  Tag-getriggerte GitHub-Releases
- `.gitattributes` mit `export-ignore`-Liste für saubere Release-ZIPs

### Changed
- Minimale Moodle-Version von 4.3 auf 4.5 angehoben (`$plugin->requires = 2024100700`)

## [0.1.0] - 2026-04-09

### Added
- Erste Plugin-Version `local_eledia_exam2pdf`
- PDF-Erzeugung nach bestandenen Quiz-Versuchen (TCPDF-basiert)
- Ausgabemodi: Download, E-Mail-Versand oder beides parallel
- Konfigurierbare Kopfzeilen mit Pflicht- und optionalen Feldern
- Per-Quiz-Overrides für alle Admin-Settings
- Scheduled Task `cleanup_expired_pdfs` (nächtlich 02:30 Uhr)
- Zugriffskontrolle über Capabilities
  (`downloadown`, `manage`, `configure`)
- Privacy API (GDPR) — vollständige Export- und Löschfunktion
- Moodle 4.3+ Hooks API Integration für Download-Button auf Quiz-Review-Seite
- Englisches Language Pack (`lang/en`)
