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
  * Mindmap Course Format - displays course content as interactive,
  * personalized mindmap
  *
  * @package    format_mindmap
  * @copyright  2014 onwards Łukasz Sanokowski, Barbara Dębska
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */

require_once('../../../../config.php');
global $CFG, $DB;
$courseid        = required_param('courseid', PARAM_INT);
$filename        = required_param('filename', PARAM_ALPHANUM).".mm";

if (! $course = $DB->get_record('course', array("id" => $courseid))) {
    print_error(get_string('invalidcourse', 'block_mindmap', $courseid));
}

require_login($course);
$tempfolder = "$CFG->dataroot/temp/mindmap_course_format/";
$filepath = $tempfolder . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    header('HTTP/1.0 404 Not Found');
    die('File not found');
}

header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-length: ' . filesize($filepath));

readfile($filepath);