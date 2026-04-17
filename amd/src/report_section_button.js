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
 * Places the ZIP/merged bulk-download section next to the "Regrade attempts"
 * button on the quiz report overview. Falls back to a below-table placement
 * when the regrade control is not rendered (e.g. user lacks regrade cap).
 *
 * @module     local_eledia_exam2pdf/report_section_button
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the module.
 *
 * @param {Object} args
 * @param {string} args.sectionId DOM id of the wrapper <section>.
 */
export const init = (args) => {
    const section = document.getElementById(args.sectionId);
    if (!section) {
        return;
    }

    const moveNextTo = document.getElementById('regradeattempts')
        || document.querySelector('input[name="regradeattempts"], button[name="regradeattempts"]');

    if (moveNextTo) {
        section.style.display = 'inline-block';
        section.style.margin = '0 0 0 .5rem';
        section.style.verticalAlign = 'middle';
        moveNextTo.insertAdjacentElement('afterend', section);
        return;
    }

    // Fallback when "Regrade attempts" is unavailable.
    section.style.margin = '1rem 0 0 0';
};
