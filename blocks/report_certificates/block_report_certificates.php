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
 * Version details
 * 
 * Report certificates block
 * --------------------------
 * Displays all issued certificates for users with unique codes. 
 * The certificates will also be issued for courses that have been archived since issuing of the certificates 
 *
 * @copyright  2015 onwards Manieer Chhettri | Marie Curie, UK | <manieer@gmail.com>
 * @author     Manieer Chhettri | Marie Curie, UK | <manieer@gmail.com> | 2015
 * @package    block_report_certificates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/certificate/locallib.php');

/**
 * 
 * Report previously issued certificates.
 * 
 * Displays all previously issued certificatesfor logged in user.
 * 
 * @copyright 2015 onwards Manieer Chhettri | Marie Curie, UK | <manieer@gmail.com>.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_report_certificates extends block_base {

    /**
     * 
     * Report previously issued certificates.
     * 
     * Displays all previously issued certificatesfor logged in user.
     */
    public function init() {
        $this->title   = get_string('report_certificates', 'block_report_certificates');
        $this->version = 2015071500;
    }

    /**
     * 
     * Retrieving relevant required data.
     * 
     * Retrieving data and populating them for displaying on the block.
     */
    public function get_content() {
        global $USER, $DB, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        // User ID.
        $userid = optional_param('userid', $USER->id, PARAM_INT);

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        // Table headers.
        $table = new html_table();
        $table->head = array(get_string('report_certificates_tblheader_coursename', 'block_report_certificates'),
                             get_string('report_certificates_tblheader_grade', 'block_report_certificates'),
                             get_string('report_certificates_tblheader_code', 'block_report_certificates'),
                             get_string('report_certificates_tblheader_issuedate', 'block_report_certificates'),
                             get_string('report_certificates_tblheader_download', 'block_report_certificates'));
        $table->align = array ("left", "center", "center", "center", "center");

        $sql = "SELECT DISTINCT ci.id AS certificateid, ci.userid, ci.code AS code,
                                ci.timecreated AS citimecreated,
                                crt.name AS certificatename, crt.*,
                                cm.id AS coursemoduleid, cm.course, cm.module,
                                c.id AS id, c.fullname AS fullname, c.*,
				                ctx.id AS contextid, ctx.instanceid AS instanceid,
				                f.itemid AS itemid, f.filename AS filename
                           FROM {certificate_issues} ci
                     INNER JOIN {certificate} crt
                             ON crt.id = ci.certificateid
                     INNER JOIN {course_modules} cm
                             ON cm.course = crt.course
                     INNER JOIN {course} c
                             ON c.id = cm.course
                     INNER JOIN {context} ctx
                             ON ctx.instanceid = cm.id
                     INNER JOIN {files} f
                             ON f.contextid = ctx.id
                          WHERE ctx.contextlevel = 70 AND
                                f.mimetype = 'application/pdf' AND
                                ci.userid = f.userid AND
                                ci.userid = :userid
                       GROUP BY ci.code
                       ORDER BY ci.timecreated ASC";
            // CONTEXT_MODULE (ctx.contextlevel = 70).
            // PDF FILES ONLY (f.mimetype = 'application/pdf').

        $limit = " LIMIT 5"; // Limiting the output results to just five records.
        $certificates = $DB->get_records_sql($sql.$limit, array('userid' => $USER->id));

        if (empty($certificates)) {
            $this->content->text = get_string('report_certificates_noreports', 'block_report_certificates');
            return $this->content;
        } else {
            foreach ($certificates as $certdata) {

                $certdata->printdate = 1; // Modify printdate so that date is always printed.
                $certrecord = new stdClass();
                $certrecord->timecreated = $certdata->citimecreated;

                // Date format.
                $dateformat = get_string('strftimedate', 'langconfig');

                // Required variables for output.
                $userid = $certrecord->userid = $certdata->userid;
                $certificateid = $certrecord->certificateid = $certdata->certificateid;
                $contextid = $certrecord->contextid = $certdata->contextid;
                $courseid = $certrecord->id = $certdata->id;
                $coursename = $certrecord->fullname = $certdata->fullname;
                $filename = $certrecord->filename = $certdata->filename;
                $code = $certrecord->code = $certdata->code;

                // Retrieving grade and date for each certificate.
                $grade = certificate_get_grade($certdata, $certrecord, $userid, $valueonly = true);
                $date = $certrecord->timecreated = $certdata->citimecreated;

                // Direct course link.
                $link = html_writer::link(new moodle_url('/course/view.php', array('id' => $courseid)),
                $coursename, array('fullname' => $coursename));

                // Direct certificate download link.
                $filelink = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'
                            .$contextid.'/mod_certificate/issue/'
                            .$certificateid.'/'.$filename);

                 $outputlink = '<a href="'.$filelink.'" >'
                      .'<img src="../blocks/report_certificates/pix/download.png" alt="Please download"'
                      .'width="100px" height="29px">'
                      .'</a>';

                 $table->data[] = array ($link, $grade, $code, userdate($date, $dateformat), $outputlink);

            }

                 $this->content->footer = html_writer::link(new moodle_url('/blocks/report_certificates/report.php',
                                 array('userid' => $USER->id)),
                                 get_string('report_certificates_footermessage', 'block_report_certificates'));
        }

        $this->content->text = html_writer::table($table);
        return $this->content;
    }
}
