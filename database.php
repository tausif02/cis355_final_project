<?php
/* ---------------------------------------------------------------------------
 * filename    : database.php
 * author      : George Corser, gcorser@gmail.com
 * description : This class enables PHP to connect to MySQL using 
 *               PDO (PHP Data Objects). See: https://phpdelusions.net/pdo#why
 * important   : This file contains passwords!
 *               Do not put real version of this file in public github repo!
 *               Create sibling subdirectory and at top of all PHP files:
 *               require '../database/database.php';
 * ---------------------------------------------------------------------------
 */
// The Database class enables PHP to connect-to/disconnect-from MySQL database
class Database {
	
	// declare and initialize variables for connect() function
	private static $dbName         = 'cis355'; // Database name
	private static $dbHost         = 'localhost'; // Host
	private static $dbUsername     = 'root'; // MySQL username (default for XAMPP)
	private static $dbUserPassword = ''; // MySQL password (leave empty for XAMPP default)
	
	// declare and initialize PDO instance variable: $connection
	private static $connection  = null;
	
	// method: __construct()
	public function __construct() {
		exit('No constructor required for class: Database');
	} 
	
	// method: connect()
	public static function connect() {
		if (null == self::$connection) {      
			try {
				self::$connection =  new PDO( "mysql:host=".self::$dbHost.";"."dbname=".self::$dbName, self::$dbUsername, self::$dbUserPassword);  
			}
			catch(PDOException $e) { die($e->getMessage()); }
		} 	
		// echo "Connected."; exit(); // uncomment to test database connection
		return self::$connection;
	} 
	
	// method: disconnect()
	public static function disconnect() {
		self::$connection = null;
	} 
	
} // end class: Database
?>