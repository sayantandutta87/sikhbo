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
 * Utility file.
 *
 * The effort of all given authors below gives you this current version of the file.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @author     Mike Holzer
 * @author     Forrest Gaston
 * @author     Juan Pablo Torres Herrera
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @author     John Hoopes <hoopes@wisc.edu>, University of Wisconsin - Madison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config.php';

global $CFG;

require_once $CFG->dirroot . '/lib/clilib.php';
require_once __DIR__ . '/autoload.php';
require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/mergeusers/lib.php');

/**
 *
 *
 * Lifecycle:
 * <ol>
 *   <li>Once: <code>$mut = new MergeUserTool();</code></li>
 *   <li>N times: <code>$mut->merge($from, $to);</code> Passing two objects with at least
 *   two attributes ('id' and 'username') on each, this will merge the user $from into the
 *   user $to, so that the $from user will be empty of activity.</li>
 * </ol>
 *
 * @author Jordi Pujol-Ahulló
 */
class MergeUserTool
{

    /**
     * @var bool true if current database is supported; false otherwise.
     */
    protected $supportedDatabase;

    /**
     * @var array associative array showing the user-related fields per database table,
     * without the $CFG->prefix on each.
     */
    protected $userFieldsPerTable;

    /**
     * @var array string array with all known database table names to skip in analysis,
     * without the $CFG->prefix on each.
     */
    protected $tablesToSkip;

    /**
     * @var array string array with the current skipped tables with the $CFG->prefix on each.
     */
    protected $tablesSkipped;

    /**
     * @var array associative array with special cases for tables with compound indexes,
     * without the $CFG->prefix on each.
     */
    protected $tablesWithCompoundIndex;

    /**
     * @var string Database-specific SQL to get the list of database tables.
     */
    protected $sqlListTables;

    /**
     * @var array array with table names (without $CFG->prefix) and the list of field names
     * that are related to user.id. The key 'default' is the default for any non matching table name.
     */
    protected $userFieldNames;

    /**
     * @var tool_mergeusers_logger logger for merging users.
     */
    protected $logger;

    /**
     * @var array associative array (tablename => classname) with the
     * TableMerger tools to process all database tables.
     */
    protected $tableMergers;

    /**
     * @var array list of table names processed by TableMerger's.
     */
    protected $tablesProcessedByTableMergers;

    /**
     * Initializes
     * @global object $CFG
     * @param tool_mergeusers_config $config local configuration.
     * @param tool_mergeusers_logger $logger logger facility to save results of mergings.
     */
    public function __construct(tool_mergeusers_config $config = null, tool_mergeusers_logger $logger = null)
    {
        global $CFG;

        $this->logger = (is_null($logger)) ? new tool_mergeusers_logger() : $logger;
        $config = (is_null($config)) ? tool_mergeusers_config::instance() : $config;
        $this->supportedDatabase = true;

        $this->checkTransactionSupport();

        switch ($CFG->dbtype) {
            case 'sqlsrv':
            case 'mssql':
                $this->sqlListTables = "SELECT name FROM sys.Tables WHERE name LIKE '" .
                    $CFG->prefix . "%' AND type = 'U' ORDER BY name";
                break;
            case 'mysqli':
            case 'mariadb':
                $this->sqlListTables = 'SHOW TABLES like "' . $CFG->prefix . '%"';
                break;
            case 'pgsql':
                $this->sqlListTables = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '" .
                    $CFG->prefix . "%' AND table_schema = 'public'";
                break;
            default:
                $this->supportedDatabase = false;
                $this->sqlListTables = "";
        }

        // these are tables we don't want to modify due to logging or security reasons.
        // we flip key<-->value to accelerate lookups.
        $this->tablesToSkip = array_flip($config->exceptions);
        $excluded = explode(',', get_config('tool_mergeusers', 'excluded_exceptions'));
        $excluded = array_flip($excluded);
        if (!isset($excluded['none'])) {
            foreach ($excluded as $exclude => $nonused) {
                unset($this->tablesToSkip[$exclude]);
            }
        }

        // these are special cases, corresponding to tables with compound indexes that
        // need a special treatment.
        $this->tablesWithCompoundIndex = $config->compoundindexes;

        // Initializes user-related field names.
        $userFieldNames = array();
        foreach ($config->userfieldnames as $tablename => $fields) {
            $userFieldNames[$tablename] = "'" . implode("','", $fields) . "'";
        }
        $this->userFieldNames = $userFieldNames;

        // Load available TableMerger tools.
        $tableMergers = array();
        $tablesProcessedByTableMergers = array();
        foreach ($config->tablemergers as $tableName => $class) {
            $tm = new $class();
            // ensure any provided class is a class of TableMerger
            if (!$tm instanceof TableMerger) {
                // aborts execution by showing an error.
                if (CLI_SCRIPT) {
                    cli_error('Error: ' . __METHOD__ . ':: ' . get_string('notablemergerclass', 'tool_mergeusers',
                                    $class));
                } else {
                    print_error('notablemergerclass', 'tool_mergeusers',
                            new moodle_url('/admin/tool/mergeusers/index.php'), $class);
                }
            }
            // append any additional table to skip.
            $tablesProcessedByTableMergers = array_merge($tablesProcessedByTableMergers, $tm->getTablesToSkip());
            $tableMergers[$tableName] = $tm;
        }
        $this->tableMergers = $tableMergers;
        $this->tablesProcessedByTableMergers = array_flip($tablesProcessedByTableMergers);

        // this will abort execution if local database is not supported.
        $this->checkDatabaseSupport();

        // initializes the list of fields and tables to check in the current database,
        // given the local configuration.
        $this->init();
    }

    /**
     * Merges two users into one. User-related data records from user id $fromid are merged into the
     * user with id $toid.
     * @global object $CFG
     * @global moodle_database $DB
     * @param int $toid The user inheriting the data
     * @param int $fromid The user being replaced
     * @return array An array(bool, array, int) having the following cases: if array(true, log, id)
     * users' merging was successful and log contains all actions done; if array(false, errors, id)
     * means users' merging was aborted and errors contain the list of errors.
     * The last id is the log id of the merging action for later visual revision.
     */
    public function merge($toid, $fromid)
    {
        $result = $this->_merge($toid, $fromid);

        $event = new stdClass();
        $event->newid = $toid;
        $event->oldid = $fromid;
        $event->log = $result[1];
        $event->timemodified = time();
        events_trigger(($result[0]) ? 'merging_success' : 'merging_failed', $event);

        $result[] = $this->logger->log($toid, $fromid, $result[0], $result[1]);
        return $result;
    }

    /**
     * Real method that performs the merging action.
     * @global object $CFG
     * @global moodle_database $DB
     * @param int $toid The user inheriting the data
     * @param int $fromid The user being replaced
     * @return array An array(bool, array) having the following cases: if array(true, log)
     * users' merging was successful and log contains all actions done; if array(false, errors)
     * means users' merging was aborted and errors contain the list of errors.
     */
    private function _merge($toid, $fromid)
    {
        global $CFG, $DB;

        // initial checks.
        // database type is supported?
        if (!$this->supportedDatabase) {
            return array(false, array(get_string('errordatabase', 'tool_mergeusers', $CFG->dbtype)));
        }

        // are they the same?
        if ($fromid == $toid) {
            // yes. do nothing.
            return array(false, array(get_string('errorsameuser', 'tool_mergeusers')));
        }

        // ok, now we have to work;-)
        // first of all... initialization!
        $errorMessages = array();
        $actionLog = array();
        $transaction = $DB->start_delegated_transaction();

        try {
            // processing each table name
            $data = array(
                'toid' => $toid,
                'fromid' => $fromid,
            );
            foreach ($this->userFieldsPerTable as $tableName => $userFields) {
                $data['tableName'] = $tableName;
                $data['userFields'] = $userFields;
                if (isset($this->tablesWithCompoundIndex[$tableName])) {
                    $data['compoundIndex'] = $this->tablesWithCompoundIndex[$tableName];
                } else {
                    unset($data['compoundIndex']);
                }

                $tableMerger = (isset($this->tableMergers[$tableName])) ?
                        $this->tableMergers[$tableName] :
                        $this->tableMergers['default'];

                // process the given $tableName.
                $tableMerger->merge($data, $actionLog, $errorMessages);
            }
        } catch (Exception $e) {
            $errorMessages[] = nl2br("Exception thrown when merging: '" . $e->getMessage() . '".' .
                    html_writer::empty_tag('br') . $DB->get_last_error() . html_writer::empty_tag('br') .
                    'Trace:' . html_writer::empty_tag('br') .
                    $e->getTraceAsString() . html_writer::empty_tag('br'));
        }

        // concludes with true if no error
        if (empty($errorMessages)) {
            $transaction->allow_commit();

            // add skipped tables as first action in log
            $skippedTables = array();
            if (!empty($this->tablesSkipped)) {
                $skippedTables[] = get_string('tableskipped', 'tool_mergeusers', implode(", ", $this->tablesSkipped));
            }

            return array(true, array_merge($skippedTables, $actionLog));
        } else {
            try {
                //thrown controlled exception.
                $transaction->rollback(new Exception(__METHOD__ . ':: Rolling back transcation.'));
            } catch (Exception $e) { /* do nothing, just for correctness */
            }
        }

        // concludes with an array of error messages otherwise.
        return array(false, $errorMessages);
    }

    // ****************** INTERNAL UTILITY METHODS ***********************************************

    /**
     * Initializes the list of database table names and user-related fields for each table.
     * @global object $CFG
     * @global moodle_database $DB
     */
    private function init()
    {
        global $CFG, $DB;

        $userFieldsPerTable = array();

        $tableNames = $DB->get_records_sql($this->sqlListTables);
        $prefixLength = strlen($CFG->prefix);

        foreach ($tableNames as $fullTableName => $toIgnore) {

            if (!trim($fullTableName)) {
                //This section should never be executed due to the way Moodle returns its resultsets
                // Skipping due to blank table name
                continue;
            } else {
                $tableName = substr($fullTableName, $prefixLength);
                // table specified to be excluded.
                if (isset($this->tablesToSkip[$tableName])) {
                    $this->tablesSkipped[$tableName] = $fullTableName;
                    continue;
                }
                // table specified to be processed additionally by a TableMerger.
                if (isset($this->tablesProcessedByTableMergers[$tableName])) {
                    continue;
                }
            }

            // detect available user-related fields among database tables.
            $userFields = (isset($this->userFieldNames[$tableName])) ?
                    $this->userFieldNames[$tableName] :
                    $this->userFieldNames['default'];

            $currentFields = $this->getCurrentUserFieldNames($fullTableName, $userFields);

            if ($currentFields !== false) {
                $userFieldsPerTable[$tableName] = array_values($currentFields);
            }
        }

        $this->userFieldsPerTable = $userFieldsPerTable;
    }

    /**
     * Check whether current Moodle's database type is supported.
     * If it is not supported, it aborts the execution with an error message, checking whether
     * it is on a CLI script or on web.
     */
    private function checkDatabaseSupport()
    {
        global $CFG;

        if (!$this->supportedDatabase) {
            if (CLI_SCRIPT) {
                cli_error('Error: ' . __METHOD__ . ':: ' . get_string('errordatabase', 'tool_mergeusers', $CFG->dbtype));
            } else {
                print_error('errordatabase', 'tool_mergeusers', new moodle_url('/admin/tool/mergeusers/index.php'),
                        $CFG->dbtype);
            }
        }
    }

    /**
     * Checks whether the current database supports transactions.
     * If settings of this plugin are set up to allow only transactions,
     * this method aborts the execution. Otherwise, this method will return
     * true or false whether the current database supports transactions or not,
     * respectively.
     * @return bool true if database transactions are supported. false otherwise.
     */
    public function checkTransactionSupport()
    {
        global $CFG;

        $transactionsSupported = tool_mergeusers_transactionssupported();
        $forceOnlyTransactions = get_config('tool_mergeusers', 'transactions_only');

        if (!$transactionsSupported && $forceOnlyTransactions) {
            if (CLI_SCRIPT) {
                cli_error('Error: ' . __METHOD__ . ':: ' . get_string('errortransactionsonly', 'tool_mergeusers',
                                $CFG->dbtype));
            } else {
                print_error('errortransactionsonly', 'tool_mergeusers',
                        new moodle_url('/admin/tool/mergeusers/index.php'), $CFG->dbtype);
            }
        }

        return $transactionsSupported;
    }

    /**
     * Gets the matching fields on the given $tableName against the given $userFields.
     * @param string $tableName database table name to analyse, with $CFG->prefix.
     * @param string $userFields candidate user fields to check.
     * @return bool | array false if no matching field name;
     * string array with matching field names otherwise.
     */
    private function getCurrentUserFieldNames($tableName, $userFields)
    {
        global $CFG, $DB;
        return $DB->get_fieldset_sql("
            SELECT DISTINCT column_name
            FROM
                INFORMATION_SCHEMA.Columns
            WHERE
                TABLE_NAME = ? AND
                (TABLE_SCHEMA = ? OR TABLE_CATALOG=?) AND
                COLUMN_NAME IN (" . $userFields . ")",
            array($tableName, $CFG->dbname, $CFG->dbname));
    }
}
