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
 * this file contains the task to suspend users from the upload folder.
 *
 * File         fromfolder.php
 * Encoding     UTF-8
 * @copyright   Sebsoft.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_usersuspension\task\suspend;
use tool_usersuspension\config;
use tool_usersuspension\processor\csv as csvprocessor;

/**
 * Description of fromfolder
 *
 * @package     tool_usersuspension
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fromfolder extends \core\task\scheduled_task {

    /**
     * Return the localised name for this task
     *
     * @return string task name
     */
    public function get_name() {
        return get_string('task:fromfolder', 'tool_usersuspension');
    }

    /**
     * Executes the task
     *
     * @return void
     */
    public function execute() {
        if (!(bool)config::get('enabled')) {
            mtrace(get_string('config:tool:disabled', 'tool_usersuspension'));
            return;
        }
        if (!(bool)config::get('enablefromfolder')) {
            mtrace(get_string('config:fromfolder:disabled', 'tool_usersuspension'));
            return;
        }
        $uploadedfile = config::get('uploadfolder') . '/' . config::get('uploadfilename');
        if (!file_exists($uploadedfile) || is_dir($uploadedfile)) {
            return;
        }

        if (!is_readable($uploadedfile)) {
            mtrace(get_string('msg:file-not-readable', 'tool_usersuspension', $uploadedfile));
            return;
        }

        // Process uploaded file.
        $proc = new csvprocessor();
        $proc->set_file($uploadedfile);
        $proc->set_delimiter(';');
        $proc->set_enclosure('"');
        $proc->set_notifycallback('mtrace');
        $proc->process();
        // Delete uploaded file.
        if (is_writable($uploadedfile)) {
            unlink($uploadedfile);
        } else {
            mtrace(get_string('msg:file-not-writeable', 'tool_usersuspension', $uploadedfile));
        }
    }

}