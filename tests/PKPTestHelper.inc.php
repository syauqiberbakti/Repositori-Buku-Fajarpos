<?php
/**
 * @file tests/PKPTestHelper.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestHelper
 * @ingroup tests
 *
 * @brief Class that implements functionality common to all PKP test types.
 */

define('PKP_TEST_ENTIRE_DB', 1);

use PKP\db\DAO;

abstract class PKPTestHelper
{
    //
    // Public helper methods
    //
    /**
     * Backup the given tables.
     *
     * @param $tables array
     * @param $test PHPUnit_Framework_Assert
     */
    public static function backupTables($tables, $test)
    {
        $dao = new DAO();
        $driver = Config::getVar('database', 'driver');
        foreach ($tables as $table) {
            switch ($driver) {
                case 'mysql':
                case 'mysqli':
                    $createLikeSql = "CREATE TABLE backup_${table} LIKE ${table}";
                    break;
                case 'postgres':
                case 'postgres64':
                case 'postgres7':
                case 'postgres8':
                case 'postgres9':
                    $createLikeSql = "CREATE TABLE backup_${table} (LIKE ${table})";
                    break;
                default:
                    $test->fail("Unknown driver \"${driver}\"");
                    return;
            }

            $sqls = [
                "DROP TABLE IF EXISTS backup_${table}",
                $createLikeSql,
                "INSERT INTO backup_${table} SELECT * FROM ${table}"
            ];
            foreach ($sqls as $sql) {
                $dao->update($sql, [], true, false);
            }
        }
    }

    /**
     * Restore the given tables.
     *
     * @param $tables array
     * @param $test PHPUnit_Framework_Assert
     */
    public static function restoreTables($tables, $test)
    {
        $dao = new DAO();
        foreach ($tables as $table) {
            $sqls = [
                "TRUNCATE TABLE ${table}",
                "INSERT INTO ${table} SELECT * FROM backup_${table}",
                "DROP TABLE backup_${table}"
            ];
            foreach ($sqls as $sql) {
                $dao->update($sql, [], true, false);
            }
        }
    }

    /**
     * Restore the database from a dump file.
     */
    public static function restoreDB($test)
    {
        $filename = getenv('DATABASEDUMP');
        if (!$filename || !file_exists($filename)) {
            $test->fail('Database dump filename needs to be specified in env variable DATABASEDUMP!');
            return;
        }

        $output = $status = null; // For PHP scrutinizer
        switch (Config::getVar('database', 'driver')) {
            case 'mysql':
            case 'mysqli':
                exec(
                    $cmd = 'zcat ' .
                    escapeshellarg($filename) .
                    ' | /usr/bin/mysql --user=' .
                    escapeshellarg(Config::getVar('database', 'username')) .
                    ' --password=' .
                    escapeshellarg(Config::getVar('database', 'password')) .
                    ' --host=' .
                    escapeshellarg(Config::getVar('database', 'host')) .
                    ' ' .
                    escapeshellarg(Config::getVar('database', 'name')),
                    $output,
                    $status
                );
                if ($status !== 0) {
                    $test->fail("Error while restoring database from \"${filename}\" (command: \"${cmd}\").");
                }
                break;
            case 'postgres':
            case 'postgres64':
            case 'postgres7':
            case 'postgres8':
            case 'postgres9':
                // WARNING: Does not send a password.
                exec(
                    $cmd = 'zcat ' .
                    escapeshellarg($filename) .
                    ' | /usr/bin/psql --username=' .
                    escapeshellarg(Config::getVar('database', 'username')) .
                    ' --no-password' .
                    ' --host=' .
                    escapeshellarg(Config::getVar('database', 'host')) .
                    ' ' .
                    escapeshellarg(Config::getVar('database', 'name')),
                    $output,
                    $status
                );
                if ($status !== 0) {
                    $test->fail("Error while restoring database from \"${filename}\" (command: \"${cmd}\".");
                }
                break;
        }
    }

    /**
     * Some 3rd-party libraries (i.e. adodb)
     * use the PHP @ operator a lot which can lead
     * to test failures when xdebug's scream parameter
     * is on. This helper method can be used to safely
     * (de)activate this.
     *
     * If the xdebug extension is not installed then
     * this method does nothing.
     *
     * @param $scream boolean
     */
    public static function xdebugScream($scream)
    {
        if (extension_loaded('xdebug')) {
            static $previous = null;
            if ($scream) {
                assert(!is_null($previous));
                ini_set('xdebug.scream', $previous);
            } else {
                $previous = ini_get('xdebug.scream');
                ini_set('xdebug.scream', false);
            }
        }
    }
}
