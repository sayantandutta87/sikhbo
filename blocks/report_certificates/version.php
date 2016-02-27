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

$plugin->version  = 2015071500;       // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2014111000;       // Requires this Moodle version 2.8.
$plugin->cron = 0;                    // Period for cron to check this module (secs).
$plugin->component = 'block_report_certificates'; // To check on upgrade, that module sits in correct place.
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v4-0715';

$plugin->dependencies = array(
    'mod_certificate' => ANY_VERSION
);
