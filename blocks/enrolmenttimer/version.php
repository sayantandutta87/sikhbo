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
 * Version File
 *
 * @package    block_enrolmenttimer
 * @copyright  2014 Aaron Leggett - LearningWorks Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$plugin->version = 2015031914;  // YYYYMMDDHH (year, month, day, 24-hr time)
$plugin->requires = 2013111800; // YYYYMMDDHH (This is the release version for Moodle 2.6)
$plugin->cron = 3600; 			// cron runs every hour
$plugin->component = 'block_enrolmenttimer';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'Moodle 2.6+ (Build: 20131118)';