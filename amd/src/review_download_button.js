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
 * Moves the quiz review download button next to the "Finish review" action
 * (or into the page header when "Finish review" is not present).
 *
 * @module     local_eledia_exam2pdf/review_download_button
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the module.
 *
 * @param {Object} args
 * @param {string} args.holderId DOM id of the footer download-button wrapper.
 */
export const init = (args) => {
    const holder = document.getElementById(args.holderId);
    if (!holder) {
        return;
    }

    const finish = document.querySelector('button[name="finishreview"], input[name="finishreview"]');
    if (finish) {
        holder.style.display = 'inline-block';
        holder.style.margin = '0 0 0 .5rem';
        holder.style.textAlign = 'left';
        finish.insertAdjacentElement('afterend', holder);
        return;
    }

    const header = document.querySelector('#page-header .page-header-headings')
        || document.querySelector('#page-header .page-header-content')
        || document.querySelector('#page-header');
    if (header) {
        holder.style.display = 'block';
        holder.style.margin = '0 0 1rem 0';
        holder.style.textAlign = 'left';
        header.insertAdjacentElement('beforeend', holder);
    }
};
