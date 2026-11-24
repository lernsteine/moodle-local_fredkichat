// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Keep width and height of the floating chat button in proportion while editing a chatbot design.
 *
 * @module     local_fredkichat/size_lock
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Link the width and height fields.
 */
export const init = () => {
    const width = document.getElementById('id_launcherwidth');
    const height = document.getElementById('id_launcherheight');
    const lock = document.getElementById('id_launcherproportional');
    if (!width || !height || !lock) {
        return;
    }
    let ratio = 1;
    const remember = () => {
        const w = parseFloat(width.value);
        const h = parseFloat(height.value);
        if (w > 0 && h > 0) {
            ratio = w / h;
        }
    };
    remember();
    lock.addEventListener('change', remember);
    width.addEventListener('input', () => {
        const w = parseFloat(width.value);
        if (!lock.checked) {
            remember();
        } else if (w > 0) {
            height.value = String(Math.round(w / ratio));
        }
    });
    height.addEventListener('input', () => {
        const h = parseFloat(height.value);
        if (!lock.checked) {
            remember();
        } else if (h > 0) {
            width.value = String(Math.round(h * ratio));
        }
    });
};
