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
 * View one merging log.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Jordi Pujol-Ahulló, Sred, Universitat Rovira i Virgili
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

global $CFG, $DB, $PAGE;

// Report all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once($CFG->dirroot . '/lib/adminlib.php');
require_once('lib/autoload.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_mergeusers_viewlog');
$id = required_param('id', PARAM_INT);

$renderer = $PAGE->get_renderer('tool_mergeusers');
$logger = new tool_mergeusers_logger();

$log = $logger->getDetail($id);

if (empty($log)) {
    print_error('wronglogid', 'tool_mergeusers', new moodle_url('/admin/tool/mergeusers/index.php')); //aborts execution
}

$from = $DB->get_record('user', array('id' => $log->fromuserid), 'id, username');
if (!$from) {
    $from = new stdClass();
    $from->id = $log->fromuserid;
    $from->username = get_string('deleted');
}

$to = $DB->get_record('user', array('id' => $log->touserid), 'id, username');
if (!$to) {
    $to = new stdClass();
    $to->id = $log->touserid;
    $to->username = get_string('deleted');
}

echo $renderer->results_page($to, $from, $log->success, $log->log, $log->id);
