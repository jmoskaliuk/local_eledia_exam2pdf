<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for local_eledia_exam2pdf.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2025 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bulkformat_merged'] = 'Ein zusammengefügtes PDF';
$string['bulkformat_zip'] = 'ZIP mit einzelnen PDFs';
$string['download_button'] = 'Zertifikat herunterladen';
$string['download_button_notpassed'] = 'Zertifikat nicht verfügbar (Versuch nicht bestanden)';
$string['download_heading'] = 'PDF-Zertifikat herunterladen';
$string['download_nopermission'] = 'Sie haben keine Berechtigung, dieses Zertifikat herunterzuladen.';
$string['download_notavailable'] = 'Das PDF-Zertifikat ist nicht verfügbar. Der Versuch wurde möglicherweise nicht bestanden oder die Datei wurde gelöscht.';
$string['eledia_exam2pdf:configure'] = 'Per-Quiz-PDF-Einstellungen konfigurieren';
$string['eledia_exam2pdf:downloadall'] = 'Alle PDF-Zertifikate eines Quiz herunterladen';
$string['eledia_exam2pdf:downloadown'] = 'Eigenes Quiz-PDF-Zertifikat herunterladen';
$string['eledia_exam2pdf:generatepdf'] = 'PDF-Zertifikate erzeugen oder neu generieren';
$string['eledia_exam2pdf:manage'] = 'Alle Quiz-PDF-Zertifikate verwalten';
$string['email_body'] = 'Hallo {fullname},

anbei finden Sie das PDF-Zertifikat für Ihren bestandenen Versuch im Quiz „{quizname}".

Dieses Zertifikat wurde automatisch am {date} erstellt.';
$string['email_subject_default'] = 'Quiz-Zertifikat: {quizname}';
$string['error_attempt_not_found'] = 'Versuch nicht gefunden.';
$string['error_pdf_generation_failed'] = 'PDF-Erzeugung fehlgeschlagen. Bitte kontaktieren Sie den Administrator.';
$string['error_quiz_not_found'] = 'Quiz nicht gefunden.';
$string['manage_col_actions'] = 'Aktionen';
$string['manage_col_attempt'] = 'Versuch';
$string['manage_col_expires'] = 'Läuft ab';
$string['manage_col_learner'] = 'Teilnehmer/in';
$string['manage_col_timecreated'] = 'Erstellt';
$string['manage_expires_never'] = 'Nie';
$string['manage_heading'] = 'PDF-Zertifikate für dieses Quiz';
$string['manage_norecords'] = 'Es wurden noch keine PDF-Zertifikate erzeugt.';
$string['outputmode_both'] = 'Download und E-Mail';
$string['outputmode_download'] = 'Download';
$string['outputmode_email'] = 'E-Mail';
$string['pdf_attemptnumber'] = 'Versuchsnummer';
$string['pdf_correctanswer'] = 'Korrekte Antwort';
$string['pdf_duration'] = 'Dauer';
$string['pdf_name'] = 'Teilnehmer/in';
$string['pdf_noanswer'] = '(keine Antwort gegeben)';
$string['pdf_nocorrectanswer'] = '(keine korrekte Antwort definiert)';
$string['pdf_passed'] = 'Bestanden';
$string['pdf_passed_no'] = 'Nein';
$string['pdf_passed_yes'] = 'Ja';
$string['pdf_passgrade'] = 'Bestehensgrenze';
$string['pdf_percentage'] = 'Prozent';
$string['pdf_question'] = 'Frage';
$string['pdf_questions_heading'] = 'Fragen und Antworten';
$string['pdf_quiz'] = 'Quiz';
$string['pdf_result_correct'] = 'Richtig';
$string['pdf_result_incorrect'] = 'Falsch';
$string['pdf_result_partial'] = 'Teilweise richtig';
$string['pdf_score'] = 'Punkte';
$string['pdf_timestamp'] = 'Abgeschlossen am';
$string['pdf_title'] = 'Quiz-Abschlusszertifikat';
$string['pdf_youranswer'] = 'Ihre Antwort';
$string['pdfgeneration_auto'] = 'Bei Abgabe (automatisch)';
$string['pdfgeneration_ondemand'] = 'Auf Anfrage (bei Klick)';
$string['pdfscope_all'] = 'Alle abgeschlossenen Versuche';
$string['pdfscope_passed'] = 'Nur bestandene Versuche';
$string['pluginname'] = 'eLeDia | exam2pdf';
$string['privacy:metadata:core_files'] = 'Die PDF-Datei mit dem Namen und den Quizantworten des Teilnehmers wird im Moodle-Dateisystem gespeichert.';
$string['privacy:metadata:local_eledia_exam2pdf'] = 'Speichert einen Datensatz pro bestandenem Quizversuch und verknüpft den Teilnehmer mit dem erzeugten PDF-Zertifikat.';
$string['privacy:metadata:local_eledia_exam2pdf:attemptid'] = 'Die ID des Quizversuchs.';
$string['privacy:metadata:local_eledia_exam2pdf:quizid'] = 'Die ID des Quiz.';
$string['privacy:metadata:local_eledia_exam2pdf:timecreated'] = 'Der Zeitpunkt, an dem das PDF-Zertifikat erzeugt wurde.';
$string['privacy:metadata:local_eledia_exam2pdf:timeexpires'] = 'Der Zeitpunkt, nach dem das PDF automatisch gelöscht wird (0 = nie).';
$string['privacy:metadata:local_eledia_exam2pdf:userid'] = 'Die ID des Teilnehmers, der den Versuch abgeschlossen hat.';
$string['quizsettings'] = 'exam2pdf Settings';
$string['quizsettings_heading'] = 'exam2pdf Settings für dieses Quiz';
$string['quizsettings_info'] = 'Info zu exam2pdf Settings';
$string['quizsettings_info_help'] = 'Diese Einstellungen überschreiben die globalen exam2pdf-Standards nur für dieses Quiz. '
    . 'Mit „Globalen Standard verwenden" wird die systemweite Einstellung übernommen.';
$string['quizsettings_inherit'] = 'Globalen Standard verwenden';
$string['quizsettings_saved'] = 'Einstellungen erfolgreich gespeichert.';
$string['report_col_completed'] = 'Abgeschlossen';
$string['report_col_duration'] = 'Dauer';
$string['report_col_grade'] = 'Bewertung';
$string['report_col_started'] = 'Gestartet';
$string['report_download_button'] = 'PDFs herunterladen';
$string['report_download_merged'] = 'Alle als zusammengeführtes PDF herunterladen';
$string['report_download_one'] = 'PDF herunterladen';
$string['report_download_zip'] = 'Alle als ZIP herunterladen';
$string['report_heading'] = 'exam2pdf';
$string['report_nav_link'] = 'exam2pdf';
$string['report_section_heading'] = 'exam2pdf';
$string['report_section_intro'] = 'Erzeugte PDF-Zertifikate für dieses Quiz. Klicken Sie auf ein Symbol, um ein einzelnes Zertifikat herunterzuladen, oder nutzen Sie die Schaltfläche unten, um alle Zertifikate als ZIP-Archiv herunterzuladen.';
$string['report_zip_filename'] = 'zertifikate_{quizname}_{date}.zip';
$string['report_zip_nofiles'] = 'Keine PDF-Zertifikate zum Herunterladen verfügbar.';
$string['setting_bulkformat'] = 'Bulk-Download-Format';
$string['setting_bulkformat_desc'] = 'Welches Format wird verwendet, wenn mehrere PDF-Zertifikate auf einmal heruntergeladen werden.';
$string['setting_emailrecipients'] = 'Standard-E-Mail-Empfänger';
$string['setting_emailrecipients_desc'] = 'Kommagetrennte Liste von E-Mail-Adressen. Die Adresse des Teilnehmers wird immer automatisch hinzugefügt. Kann pro Quiz überschrieben werden.';
$string['setting_emailrecipients_help'] = 'Kommagetrennte Liste von E-Mail-Adressen, die das PDF-Zertifikat neben dem Teilnehmer erhalten. Die Adresse des Teilnehmers wird immer automatisch hinzugefügt.';
$string['setting_emailsubject'] = 'Standard-E-Mail-Betreff';
$string['setting_emailsubject_desc'] = 'Betreffzeile der Benachrichtigungs-E-Mail. Unterstützt die Platzhalter {quizname} und {username}.';
$string['setting_emailsubject_help'] = 'Betreffzeile der Benachrichtigungs-E-Mail, die mit dem PDF-Zertifikat versendet wird. Sie können die Platzhalter {quizname} und {username} verwenden.';
$string['setting_heading_generation'] = 'PDF-Erzeugung';
$string['setting_heading_generation_desc'] = 'Konfigurieren Sie, wann und für welche Versuche PDF-Zertifikate erzeugt werden.';
$string['setting_optionalfields_heading'] = 'Optionale PDF-Kopfzeilenfelder';
$string['setting_optionalfields_heading_desc'] = 'Wählen Sie, welche optionalen Daten in der PDF-Kopfzeile angezeigt werden.';
$string['setting_outputmode'] = 'Ausgabemodus';
$string['setting_outputmode_desc'] = 'Wie das PDF nach einem bestandenen Versuch bereitgestellt wird. Kann pro Quiz überschrieben werden.';
$string['setting_outputmode_help'] = 'Legt fest, wie das PDF-Zertifikat nach einem bestandenen Versuch bereitgestellt wird. „Download" macht es auf der Review-Seite verfügbar. „E-Mail" sendet es an den Teilnehmer (und weitere Empfänger). „Beides" kombiniert beide Optionen.';
$string['setting_pdfgeneration'] = 'PDF-Erzeugungsmodus';
$string['setting_pdfgeneration_desc'] = 'Wann soll das PDF-Zertifikat erzeugt werden. „Bei Abgabe" erzeugt es automatisch bei Abgabe des Quizversuchs. „Auf Anfrage" erzeugt es erst, wenn ein Trainer den Download-Button klickt.';
$string['setting_pdfscope'] = 'PDF-Umfang';
$string['setting_pdfscope_desc'] = 'Für welche Versuche ein PDF erzeugt wird. „Nur bestanden" erfordert das Erreichen der Bestehensgrenze. „Alle abgeschlossenen" erzeugt ein PDF für jeden beendeten Versuch.';
$string['setting_retentiondays'] = 'Aufbewahrungsfrist';
$string['setting_retentiondays_desc'] = 'Anzahl der Tage, die das PDF nach dem Bestehen aufbewahrt wird. 0 = unbegrenzt aufbewahren. Kann pro Quiz überschrieben werden.';
$string['setting_retentiondays_help'] = 'Anzahl der Tage, die das erzeugte PDF aufbewahrt wird. Nach Ablauf dieser Frist wird die Datei automatisch durch einen geplanten Task gelöscht. 0 = PDFs unbegrenzt aufbewahren.';
$string['setting_show_attemptnumber'] = 'Versuchsnummer im PDF anzeigen';
$string['setting_show_duration'] = 'Dauer im PDF anzeigen';
$string['setting_show_passgrade'] = 'Bestehensgrenze im PDF anzeigen';
$string['setting_show_percentage'] = 'Prozent im PDF anzeigen';
$string['setting_show_score'] = 'Punkte im PDF anzeigen';
$string['setting_show_timestamp'] = 'Zeitstempel im PDF anzeigen';
$string['setting_showcorrectanswers'] = 'Korrekte Antworten im PDF anzeigen';
$string['setting_showcorrectanswers_desc'] = 'Wenn aktiviert, wird die korrekte Antwort neben der Antwort des Teilnehmers angezeigt, sofern verfügbar.';
$string['setting_showcorrectanswers_help'] = 'Wenn aktiviert, enthält das PDF die korrekte Antwort neben jeder Frage zusammen mit der Antwort des Teilnehmers. Gilt nicht für Freitext-Fragen.';
$string['setting_studentdownload'] = 'Teilnehmer darf herunterladen';
$string['setting_studentdownload_desc'] = 'Wenn aktiviert, können Teilnehmer ihr eigenes PDF-Zertifikat auf der Quiz-Review-Seite herunterladen. Wenn deaktiviert, können nur Trainer über die Report-Seite auf PDFs zugreifen.';
$string['setting_studentdownload_help'] = 'Legt fest, ob Teilnehmer einen Download-Button auf ihrer Quiz-Review-Seite sehen. Wenn deaktiviert, können nur Trainer und Manager über die exam2pdf-Report-Seite auf die erzeugten PDFs zugreifen.';
$string['task_cleanup'] = 'Abgelaufene PDF-Zertifikate löschen';
