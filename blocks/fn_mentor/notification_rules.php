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
 * Strings for component 'block_fn_mentor', language 'en'
 *
 * @package   block_fn_mentor
 * @copyright Michael Gardener <mgardener@cissq.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/fn_mentor/lib.php');
require_once($CFG->dirroot . '/blocks/fn_mentor/notificaton_form.php');

//Parameters
$menteeid = optional_param('menteeid', NULL, PARAM_INT);
$courseid = optional_param('courseid', NULL, PARAM_INT);
$id       = optional_param('id', 0, PARAM_INT);
$action   = optional_param('action', 'add', PARAM_TEXT);

require_login();

//PERMISSION
require_capability('block/fn_mentor:createnotificationrule', context_system::instance());

if (($action == 'edit') && ($id)) {
    $notification_rule = $DB->get_record('block_fn_mentor_notification',array('id'=>$id),'*',MUST_EXIST);
}

$title = get_string('page_title_assign_mentor', 'block_fn_mentor');
$heading = $SITE->fullname;

$PAGE->set_url('/blocks/fn_mentor/notification.php');
$PAGE->set_pagelayout('course');
$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
$PAGE->set_heading($heading);
$PAGE->set_cacheable(true);

$PAGE->requires->css('/blocks/fn_mentor/css/styles.css');

$PAGE->navbar->add(get_string('pluginname', 'block_fn_mentor'), new moodle_url('/blocks/fn_mentor/course_overview.php'));
$PAGE->navbar->add(get_string('notification_rules', 'block_fn_mentor'), new moodle_url('/blocks/fn_mentor/notification_rules.php'));




echo $OUTPUT->header();

echo '<div id="notification-wrapper">';
echo '<h1>' . get_string('notification_rules', 'block_fn_mentor') . '</h1>';

if ($notification_rules = $DB->get_records('block_fn_mentor_notification')) {
    $rule_number = 0;
    foreach ($notification_rules as $notification_rule) {
        $rule_number++;
        echo block_fn_mentor_render_notification_rule_table($notification_rule, $rule_number);
    }
}

echo block_fn_mentor_single_button_form ('create_new_rule', new moodle_url('/blocks/fn_mentor/notification.php'), NULL, get_string('create_new_rule', 'block_fn_mentor'));
echo '</div>';



echo $OUTPUT->footer();