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
 * Adds an "Actions" column to the quiz overview report table and fills it
 * with download / regenerate buttons per attempt row. Also normalises the
 * alignment of summary rows and question-state cells so they stay aligned
 * with the added column.
 *
 * The column is injected directly after the "Grade" column. Summary rows
 * with colspan cells are extended by one; per-attempt rows get a new cell
 * containing the action buttons.
 *
 * This module is deliberately decoupled from FontAwesome — icon markup is
 * passed in from the PHP side (rendered via $OUTPUT->pix_icon()) so the
 * module works identically in Moodle 4.5 (FA4) and 5.x (FA6).
 *
 * @module     local_eledia_exam2pdf/quiz_overview_actions
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Insert `cell` directly after the cell at `index` in `row`.
     *
     * @param {HTMLElement} row
     * @param {Number} index
     * @param {HTMLElement} cell
     */
    function insertAfter(row, index, cell) {
        var ref = row.children[index];
        if (!ref) {
            row.appendChild(cell);
            return;
        }
        if (ref.nextSibling) {
            row.insertBefore(cell, ref.nextSibling);
        } else {
            row.appendChild(cell);
        }
    }

    /**
     * Given a logical column index (ignoring colspans), return the DOM cell
     * index in `row` that covers it, or -1 if not found.
     *
     * @param {HTMLElement} row
     * @param {Number} logicalIndex
     * @return {Number}
     */
    function findCellIndexForLogicalColumn(row, logicalIndex) {
        var cells = row.children;
        var cursor = 0;
        for (var i = 0; i < cells.length; i++) {
            var span = parseInt(cells[i].getAttribute('colspan') || '1', 10);
            if (!span || span < 1) {
                span = 1;
            }
            var end = cursor + span - 1;
            if (logicalIndex >= cursor && logicalIndex <= end) {
                return i;
            }
            cursor += span;
        }
        return -1;
    }

    /**
     * Force visual parity of cells and question-state spans with the added
     * actions column.
     *
     * @param {HTMLElement} row
     */
    function normaliseRowCellAlignment(row) {
        var cells = row.children;
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.remove('align-top');
            cells[i].classList.add('align-middle');
            cells[i].style.setProperty('vertical-align', 'middle', 'important');
        }

        var questions = row.querySelectorAll('td span.que');
        for (var qi = 0; qi < questions.length; qi++) {
            var qcell = questions[qi].closest('td');
            if (qcell) {
                qcell.style.textAlign = 'center';
                qcell.style.setProperty('vertical-align', 'middle', 'important');
            }

            var qlink = questions[qi].closest('a');
            if (qlink) {
                qlink.style.display = 'inline-flex';
                qlink.style.alignItems = 'center';
                qlink.style.justifyContent = 'center';
                qlink.style.width = '100%';
                qlink.style.minHeight = '1.75rem';
            }

            questions[qi].style.display = 'inline-flex';
            questions[qi].style.alignItems = 'center';
            questions[qi].style.justifyContent = 'center';
            questions[qi].style.gap = '0.25rem';
            questions[qi].style.flexWrap = 'nowrap';
            questions[qi].style.whiteSpace = 'nowrap';
            questions[qi].style.lineHeight = '1.2';
            questions[qi].style.setProperty('vertical-align', 'middle', 'important');
        }

        var qicons = row.querySelectorAll('td span.que img.icon, td span.que i.icon, td span.que .questionflag');
        for (var ii = 0; ii < qicons.length; ii++) {
            qicons[ii].style.setProperty('vertical-align', 'middle', 'important');
        }
    }

    /**
     * Extend a summary row at the grade position by +1 colspan, or append a
     * placeholder cell if the grade column is a plain single-span cell.
     *
     * @param {HTMLElement} row
     * @param {Number} gradeIndex
     * @return {Boolean} true if a change was made.
     */
    function expandRowAtGradePosition(row, gradeIndex) {
        var anchorIdx = findCellIndexForLogicalColumn(row, gradeIndex);
        if (anchorIdx < 0) {
            return false;
        }

        var anchorCell = row.children[anchorIdx];
        var span = parseInt(anchorCell.getAttribute('colspan') || '1', 10);
        if (!span || span < 1) {
            span = 1;
        }

        if (span > 1) {
            anchorCell.setAttribute('colspan', String(span + 1));
            return true;
        }

        var td = document.createElement('td');
        td.className = 'cell local-eledia-exam2pdf-cell-actions';
        td.style.setProperty('vertical-align', 'middle', 'important');
        td.innerHTML = '&nbsp;';
        insertAfter(row, anchorIdx, td);
        return true;
    }

    /**
     * Best-effort extraction of the attempt ID from an overview row.
     *
     * @param {HTMLElement} row
     * @return {Number} attempt ID, or 0 if not found.
     */
    function extractAttemptId(row) {
        var checkbox = row.querySelector('input[type="checkbox"][name="attemptid[]"]');
        if (checkbox && checkbox.value) {
            var id = parseInt(checkbox.value, 10);
            if (id > 0) {
                return id;
            }
        }

        var links = row.querySelectorAll('a[href*="/mod/quiz/review.php?"]');
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            var match = href.match(/[?&]attempt=(\d+)/);
            if (match && match[1]) {
                var aid = parseInt(match[1], 10);
                if (aid > 0) {
                    return aid;
                }
            }
        }
        return 0;
    }

    /**
     * Locate the quiz overview report table.
     *
     * @return {HTMLElement|null}
     */
    function findOverviewTable() {
        return document.querySelector('table.generaltable.grades');
    }

    /**
     * Inject a single <style> tag with alignment rules. Safe to call
     * multiple times.
     */
    function injectAlignmentStyles() {
        var marker = 'data-local-eledia-exam2pdf-actions-style';
        if (document.head.querySelector('style[' + marker + ']')) {
            return;
        }
        var style = document.createElement('style');
        style.setAttribute(marker, '1');
        style.textContent =
            'table.generaltable.grades > thead > tr > th,\n'
            + 'table.generaltable.grades > tbody > tr > td,\n'
            + 'table.generaltable.grades > tfoot > tr > td {\n'
            + '    vertical-align: middle !important;\n'
            + '}\n'
            + 'table.generaltable.grades .local-eledia-exam2pdf-cell-actions .btn {\n'
            + '    display: inline-flex;\n'
            + '    align-items: center;\n'
            + '    justify-content: center;\n'
            + '    padding: 0.25rem 0.4rem;\n'
            + '    line-height: 1;\n'
            + '}\n'
            + 'table.generaltable.grades .local-eledia-exam2pdf-cell-actions .btn .icon {\n'
            + '    margin: 0 !important;\n'
            + '    padding: 0 !important;\n'
            + '    width: 1rem;\n'
            + '    height: 1rem;\n'
            + '}\n'
            + 'table.generaltable.grades td span.que,\n'
            + 'table.generaltable.grades td span.que a {\n'
            + '    vertical-align: middle !important;\n'
            + '}\n';
        document.head.appendChild(style);
    }

    return {
        /**
         * Initialise the module. Safe to call multiple times — the header
         * check ensures the column is only added once per table.
         *
         * @param {Object} args
         * @param {String} args.actionsLabel
         * @param {String} args.downloadLabel
         * @param {String} args.regenerateLabel
         * @param {String} args.gradeLabel
         * @param {String} args.downloadBaseUrl Base URL with attemptid=0 placeholder.
         * @param {String} args.regenerateBaseUrl Base URL with attemptid=0 placeholder.
         * @param {Boolean} args.canRegenerate
         * @param {String} args.downloadIcon Rendered pix_icon HTML.
         * @param {String} args.regenerateIcon Rendered pix_icon HTML.
         */
        init: function(args) {
            var table = findOverviewTable();
            if (!table) {
                return;
            }

            var headrow = table.querySelector('thead tr');
            if (!headrow) {
                return;
            }

            // Always inject the style rules — even if the column is already
            // added, the stylesheet may have been wiped by a subsequent redraw.
            injectAlignmentStyles();

            if (headrow.querySelector('.local-eledia-exam2pdf-col-actions')) {
                return;
            }

            var gradeLabel = args.gradeLabel;
            var headers = Array.prototype.slice.call(headrow.children);
            var gradeIndex = -1;
            for (var i = 0; i < headers.length; i++) {
                var htext = (headers[i].textContent || '').trim().toLowerCase();
                if (!htext) {
                    continue;
                }
                if (htext.indexOf(gradeLabel) === 0 || htext.indexOf(gradeLabel + '/') === 0) {
                    gradeIndex = i;
                    break;
                }
            }
            if (gradeIndex < 0) {
                return;
            }

            var btnBaseClass = 'btn btn-sm d-inline-flex align-items-center justify-content-center';

            /**
             * Build the action buttons HTML for a single attempt row.
             *
             * @param {Number} attemptid
             * @return {String}
             */
            function buildActionsHtml(attemptid) {
                if (!attemptid) {
                    return '-';
                }

                var downloadUrl = args.downloadBaseUrl.replace(/([?&]attemptid=)0(?!\d)/, '$1' + attemptid);
                var html = '<a href="' + downloadUrl + '" class="' + btnBaseClass + ' btn-outline-primary"'
                    + ' aria-label="' + args.downloadLabel + '" title="' + args.downloadLabel + '">'
                    + args.downloadIcon + '</a>';

                if (args.canRegenerate) {
                    var regenerateUrl = args.regenerateBaseUrl.replace(/([?&]attemptid=)0(?!\d)/, '$1' + attemptid);
                    html += ' <a href="' + regenerateUrl + '" class="' + btnBaseClass + ' btn-outline-secondary"'
                        + ' aria-label="' + args.regenerateLabel + '" title="' + args.regenerateLabel + '">'
                        + args.regenerateIcon + '</a>';
                }

                return html;
            }

            // Insert the header cell.
            var th = document.createElement('th');
            th.className = 'header local-eledia-exam2pdf-col-actions text-center';
            th.style.whiteSpace = 'nowrap';
            th.style.width = '5.5rem';
            th.textContent = args.actionsLabel;
            insertAfter(headrow, gradeIndex, th);

            // Per-attempt body rows.
            var bodyrows = table.querySelectorAll('tbody tr');
            for (var bi = 0; bi < bodyrows.length; bi++) {
                var row = bodyrows[bi];
                normaliseRowCellAlignment(row);
                if (row.querySelector('.local-eledia-exam2pdf-cell-actions')) {
                    continue;
                }
                var attemptid = extractAttemptId(row);
                if (!attemptid) {
                    // Colspan-based summary rows — keep alignment with the new column.
                    expandRowAtGradePosition(row, gradeIndex);
                    normaliseRowCellAlignment(row);
                    continue;
                }
                var anchorIdx = findCellIndexForLogicalColumn(row, gradeIndex);
                if (anchorIdx < 0) {
                    continue;
                }
                var td = document.createElement('td');
                td.className = 'cell text-center local-eledia-exam2pdf-cell-actions';
                td.style.whiteSpace = 'nowrap';
                td.style.setProperty('vertical-align', 'middle', 'important');
                td.innerHTML = buildActionsHtml(attemptid);
                insertAfter(row, anchorIdx, td);
                normaliseRowCellAlignment(row);
            }

            // Footer summary rows.
            var footrows = table.querySelectorAll('tfoot tr');
            for (var fi = 0; fi < footrows.length; fi++) {
                var frow = footrows[fi];
                normaliseRowCellAlignment(frow);
                if (frow.querySelector('.local-eledia-exam2pdf-cell-actions')) {
                    continue;
                }
                expandRowAtGradePosition(frow, gradeIndex);
                normaliseRowCellAlignment(frow);
            }

            // Some report scripts or redraws may reapply top-aligned utility classes.
            window.setTimeout(function() {
                var rerows = table.querySelectorAll('tbody tr, tfoot tr');
                for (var ri = 0; ri < rerows.length; ri++) {
                    normaliseRowCellAlignment(rerows[ri]);
                }
            }, 120);
        }
    };
});
