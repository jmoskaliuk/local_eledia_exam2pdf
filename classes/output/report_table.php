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
 * Table class for the PDF certificates report page.
 *
 * Renders a paginated, sortable, filterable table of all generated PDF
 * certificates for a given quiz — modelled after the quiz grades report.
 *
 * @package    local_eledia_exam2pdf
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_exam2pdf\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * SQL-driven table for the PDF certificates report.
 */
class report_table extends \table_sql {
    /** @var \stdClass The quiz record. */
    protected \stdClass $quiz;

    /** @var int The course module ID. */
    protected int $cmid;

    /**
     * Constructor.
     *
     * @param string    $uniqueid Unique table identifier.
     * @param \stdClass $quiz     The quiz record.
     * @param int       $cmid     The course module ID.
     * @param \moodle_url $baseurl  The base URL for the report page.
     */
    public function __construct(string $uniqueid, \stdClass $quiz, int $cmid, \moodle_url $baseurl) {
        parent::__construct($uniqueid);

        $this->quiz = $quiz;
        $this->cmid = $cmid;

        // Define columns.
        $columns = [
            'fullname',
            'email',
            'timestart',
            'timefinish',
            'duration',
            'grade',
            'timecreated',
            'actions',
        ];
        $headers = [
            get_string('fullname'),
            get_string('email'),
            get_string('report_col_started', 'local_eledia_exam2pdf'),
            get_string('report_col_completed', 'local_eledia_exam2pdf'),
            get_string('report_col_duration', 'local_eledia_exam2pdf'),
            get_string('report_col_grade', 'local_eledia_exam2pdf'),
            get_string('manage_col_timecreated', 'local_eledia_exam2pdf'),
            get_string('actions'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        // Sortable columns.
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('actions');
        $this->no_sorting('duration');

        // Collapsible columns.
        $this->collapsible(true);

        // Initials bar for first/last name filtering.
        $this->initialbars(true);

        $this->set_attribute('class', 'generaltable generalbox exam2pdf-report-table');

        $this->pageable(true);
    }

    /**
     * Sets up the SQL query for the table.
     *
     * @param int $pagesize Number of rows per page.
     * @param bool $useinitialsbar Whether to show the initials bar.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        global $DB;

        // Build the user name fields for sorting/display.
        $userfields = \core_user\fields::for_name()
            ->get_sql('u', false, '', '', false);

        // Base SQL: join PDF records with users and quiz attempts.
        $fields = "p.id AS pid,
                   u.id AS userid,
                   u.email,
                   {$userfields->selects},
                   qa.timestart,
                   qa.timefinish,
                   qa.sumgrades AS attemptsumgrades,
                   p.timecreated,
                   p.cmid";

        $from = "{local_eledia_exam2pdf} p
                 JOIN {user} u ON u.id = p.userid
                 JOIN {quiz_attempts} qa ON qa.id = p.attemptid";

        $where = "p.quizid = :quizid";
        $params = ['quizid' => $this->quiz->id];

        // Apply initials bar filters.
        if ($this->is_downloading()) {
            $useinitialsbar = false;
        }

        // Handle initials bar (first name).
        if ($useinitialsbar) {
            [$iwhere, $iparams] = $this->get_sql_where();
            if ($iwhere) {
                $where .= ' AND ' . $iwhere;
                $params = array_merge($params, $iparams);
            }
        }

        // Count total rows.
        $this->pagesize($pagesize, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from} WHERE {$where}",
            $params
        ));

        // Determine sorting.
        $sort = $this->get_sql_sort();
        if (!$sort) {
            $sort = 'p.timecreated DESC';
        }

        // Fetch data.
        $this->rawdata = $DB->get_records_sql(
            "SELECT {$fields} FROM {$from} WHERE {$where} ORDER BY {$sort}",
            $params,
            $this->get_page_start(),
            $this->get_page_size()
        );
    }

    /**
     * Renders the full name column.
     *
     * @param \stdClass $row The data row.
     * @return string HTML.
     */
    public function col_fullname(\stdClass $row): string {
        return s(fullname($row));
    }

    /**
     * Renders the email column.
     *
     * @param \stdClass $row The data row.
     * @return string HTML.
     */
    public function col_email(\stdClass $row): string {
        return s($row->email);
    }

    /**
     * Renders the attempt start time column.
     *
     * @param \stdClass $row The data row.
     * @return string HTML.
     */
    public function col_timestart(\stdClass $row): string {
        return $row->timestart ? userdate($row->timestart) : '-';
    }

    /**
     * Renders the attempt finish time column.
     *
     * @param \stdClass $row The data row.
     * @return string HTML.
     */
    public function col_timefinish(\stdClass $row): string {
        return $row->timefinish ? userdate($row->timefinish) : '-';
    }

    /**
     * Renders the duration column (computed, not sortable).
     *
     * @param \stdClass $row The data row.
     * @return string Formatted duration or '-'.
     */
    public function col_duration(\stdClass $row): string {
        if ($row->timestart && $row->timefinish) {
            $secs = $row->timefinish - $row->timestart;
            return format_time($secs);
        }
        return '-';
    }

    /**
     * Renders the grade column (rescaled to the quiz grade scale).
     *
     * @param \stdClass $row The data row.
     * @return string Formatted grade or '-'.
     */
    public function col_grade(\stdClass $row): string {
        if ($this->quiz->sumgrades > 0 && $row->attemptsumgrades !== null) {
            $grade = $row->attemptsumgrades / $this->quiz->sumgrades * $this->quiz->grade;
            return round($grade, 2) . ' / ' . round($this->quiz->grade, 2);
        }
        return '-';
    }

    /**
     * Renders the PDF generation timestamp column.
     *
     * @param \stdClass $row The data row.
     * @return string Formatted date.
     */
    public function col_timecreated(\stdClass $row): string {
        return userdate($row->timecreated);
    }

    /**
     * Renders the actions column (download button).
     *
     * @param \stdClass $row The data row.
     * @return string HTML.
     */
    public function col_actions(\stdClass $row): string {
        global $OUTPUT;

        // Build a minimal record object for the helper.
        $record = new \stdClass();
        $record->id = $row->pid;
        $record->cmid = $row->cmid;

        $file = \local_eledia_exam2pdf\helper::get_stored_file($record);
        if (!$file) {
            return '-';
        }

        $url   = \local_eledia_exam2pdf\helper::get_download_url($record, $file->get_filename());
        $label = get_string('report_download_one', 'local_eledia_exam2pdf');
        $icon  = $OUTPUT->pix_icon('i/download', $label);

        return \html_writer::link(
            $url,
            $icon,
            [
                'class'      => 'btn btn-sm btn-outline-primary',
                'aria-label' => $label,
                'title'      => $label,
            ]
        );
    }
}
