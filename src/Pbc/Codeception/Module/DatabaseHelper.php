<?php
/**
 * DatabaseHelper
 * Help with the population of database with tests
 *
 * PHP version >=5.6
 *
 * @category   Module
 * @package    Codeception
 * @subpackage Helper
 * @author     Nate Nolting <naten@paulbunyan.net>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/paulbunyannet/codeception-helpers
 */

Namespace Pbc\Codeception\Module;

use \Codeception\Actor;
use \Codeception\Module as CodeceptionModule;
use \Codeception\Exception\ModuleException;

/**
 * DatabaseHelper
 * Help with the population of database with tests
 *
 * @category   Module
 * @package    Codeception
 * @subpackage Helper
 * @author     Nate Nolting <naten@paulbunyan.net>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/paulbunyannet/codeception-helpers
 */
class DatabaseHelper extends CodeceptionModule
{
    protected static $connection;

    /**
     * Populate the database with a dump file.
     * Database config array requires:
     * server
     * user
     * password
     * database
     * dump: full path to your mysql dump file
     *
     * @param Actor $I          Actor
     * @param array $config     Config array
     * @param bool  $dropTables Drop tables prior to importing the dump
     *
     * @return void
     */
    public function populateDatabase(Actor $I, array $config = [], $dropTables = true)
    {
        if ($dropTables) {
            $this->dropTables($config);
        }
        $I->runShellCommand('mysql -h ' . $config['server'] . ' -u ' . $config['user'] . ' -p\'' . $config['password'] . '\' ' . $config['database'] . ' < ' . $config['dump']);
    }

    /**
     * Truncate all tables
     * Database config array requires:
     * server
     * user
     * password
     * database
     *
     * @param array $config Config array
     *
     * @return void
     */
    public function truncateTables($config = [])
    {

        $connection = new \mysqli(
            $config['server'],
            $config['user'],
            $config['password'],
            $config['database']
        );
        $sql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE '" . $config['database'] . "'";
        $result = $connection->query($sql);
        $tables = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($tables as $table) {
            $connection->query("TRUNCATE TABLE `" . $table['TABLE_NAME'] . "`");
        }
    }

    /**
     * Drop all tables
     * Database config array requires:
     * server
     * user
     * password
     * database
     *
     * @param array $config Config array
     *
     * @return void
     */
    public function dropTables($config = [])
    {

        $connection = new \mysqli(
            $config['server'],
            $config['user'],
            $config['password'],
            $config['database']
        );
        $sql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE '" . $config['database'] . "'";
        $result = $connection->query($sql);
        $tables = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($tables as $table) {
            $connection->query("DROP TABLE `" . $table['TABLE_NAME'] . "`");
        }
    }

    /**
     * Run a query
     * Database config array requires:
     * server
     * user
     * password
     * database
     *
     * @param Actor  $I      Actor
     * @param array  $config Config array
     * @param string $query  Query to run
     *
     * @return void
     */
    public function runQuery($I, $config = [], $query = '')
    {
        $queryFile = __DIR__ . '/' . md5($query) . '.sql';
        file_put_contents($queryFile, $query);
        $I->runShellCommand('mysql -h ' . $config['server'] . ' -u ' . $config['user'] . ' -p\'' . $config['password'] . '\' ' . $config['database'] . ' < ' . $queryFile);
        unlink($queryFile);
    }

    /**
     * Execute a SQL query
     * Use: $I->executeOnDatabase('UPDATE `users` SET `email` = NULL WHERE `users`.`id` = 1; ');
     *
     * @param string $sql query
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     * @throws ModuleException
     */
    public function executeOnDb($sql)
    {
        $handler = $this->getModule('Db')->dbh;
        $this->debugSection('Query', $sql);
        $prepare = $handler->prepare($sql);

        return $prepare->execute();
    }


    /**
     * Return row a SQL query
     * Use: $I->grabRowFromDatabase('SELECT * FROM `users` WHERE `users`.`id` = 1; ');
     *
     * @param string $sql query
     *
     * @return boolean|object Returns result object on success or null on failure.
     * @throws ModuleException
     */
    public function grabRowFromDb($sql)
    {
        $result = $this->grabRowsFromDb($sql);
        if ($result) {
            return $result[0];
        }
        return null;
    }

    /**
     * Return results a SQL query
     * Use: $I->grabRowsFromDatabase('SELECT * FROM `users` ');
     *
     * @param string $sql query
     *
     * @return boolean|array Returns result array on success or null on failure.
     * @throws ModuleException
     */
    public function grabRowsFromDb($sql)
    {
        $handler = $this->getModule('Db')->dbh;
        $this->debugSection('Query', $sql);
        $prepare = $handler->prepare($sql);
        $execute = $prepare->execute();
        if ($execute) {
            return $prepare->fetchAll();
        }
        return null;
    }
}
