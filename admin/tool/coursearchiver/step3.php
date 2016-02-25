<?php
// This file is part of
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
 * Step 3(Selected users).
 *
 * @package    tool_coursearchiver
 * @copyright  2015 Matthew Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolcoursearchiver');

$formdata   = isset($_SESSION['formdata']) ? $_SESSION['formdata'] : optional_param('formdata', false, PARAM_RAW);
$mode       = isset($_SESSION['mode']) ? $_SESSION['mode'] : optional_param('mode', false, PARAM_INT);
$error      = isset($_SESSION['error']) ? $_SESSION['error'] : optional_param('error', false, PARAM_RAW);
$selected   = optional_param_array('user_selected', array(), PARAM_RAW);
$submitted  = optional_param('submit_button', false, PARAM_RAW);

unset($_SESSION['formdata']);
unset($_SESSION['error']);
unset($_SESSION['mode']);

if (!empty($submitted)) { // FORM 3 SUBMITTED.

    if ($submitted == get_string('back', 'tool_coursearchiver')) { // Button to start over has been pressed.
        unset($_SESSION['formdata']);
        unset($_SESSION['mode']);
        unset($_SESSION['error']);
        unset($_SESSION['selected']);
        $returnurl = new moodle_url('/admin/tool/coursearchiver/index.php');
        redirect($returnurl);
    }

    // Clean selected course array.
    $users = array();
    foreach ($selected as $c) {
        if (!empty($c)) {
            $users[] = $c;
        }
    }

    // Fully develope array.
    $owners = array();
    foreach ($users as $s) {
        $t = explode("_", $s);
        if (count($t) == 2) { // Both a course and an owner are needed.
            if (array_key_exists($t[1], $owners)) {
                $temp = $owners[$t[1]]['courses'];
                $owners[$t[1]]['courses'] = array_merge($temp, array($t[0] => get_course($t[0])));
            } else {
                $owners[$t[1]]['courses'] = array($t[0] => get_course($t[0]));
                $owners[$t[1]]['user'] = $DB->get_record("user", array("id" => $t[1]));
            }
        }
    }

    if (empty($owners)) { // If 0 courses are selected, show message and form again.
        $_SESSION["formdata"] = $formdata;
        $_SESSION["error"] = get_string('nousersselected', 'tool_coursearchiver');
        $returnurl = new moodle_url('/admin/tool/coursearchiver/step3.php');
        redirect($returnurl);
    }

    switch($submitted){
        case get_string('hideemail', 'tool_coursearchiver'):
            $mode = tool_coursearchiver_processor::MODE_HIDEEMAIL;
            $_SESSION["formdata"] = serialize($users);
            $_SESSION["mode"] = $mode;
            $returnurl = new moodle_url('/admin/tool/coursearchiver/step4.php');
            redirect($returnurl);
            break;
        case get_string('archiveemail', 'tool_coursearchiver'):
            $mode = tool_coursearchiver_processor::MODE_ARCHIVEEMAIL;
            $_SESSION["formdata"] = serialize($users);
            $_SESSION["mode"] = $mode;
            $returnurl = new moodle_url('/admin/tool/coursearchiver/step4.php');
            redirect($returnurl);
            break;
        default:
            $_SESSION["error"] = get_string('unknownerror', 'tool_coursearchiver');
            $returnurl = new moodle_url('/admin/tool/coursearchiver/index.php');
            redirect($returnurl);
    }

} else if (!empty($formdata)) {  // FORM 2 SUBMITTED, SHOW FORM 3.
    $courses = unserialize($formdata);

    // Check again to make sure courses are coming across correctly.
    if (!is_array($courses) || empty($courses)) {
        $_SESSION["error"] = get_string('nocoursesselected', 'tool_coursearchiver');
        $returnurl = new moodle_url('/admin/tool/coursearchiver/step1.php');
        redirect($returnurl);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading_with_help(get_string('coursearchiver', 'tool_coursearchiver'), 'coursearchiver', 'tool_coursearchiver');

    if (!empty($error)) {
        echo $OUTPUT->container($error, 'coursearchiver_myformerror');
    }

    $param = array("mode" => tool_coursearchiver_processor::MODE_GETEMAILS, "courses" => $courses);
    $mform = new tool_coursearchiver_step3_form(null, array("processor_data" => $param));

    $mform->display();

    echo $OUTPUT->footer();
} else { // IN THE EVENT OF A FAILURE, JUST GO BACK TO THE BEGINNING.
    $_SESSION["error"] = get_string('unknownerror', 'tool_coursearchiver');
    $returnurl = new moodle_url('/admin/tool/coursearchiver/index.php');
    redirect($returnurl);
}