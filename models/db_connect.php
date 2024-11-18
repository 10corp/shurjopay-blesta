<?php
/**
 * ShurjoPay Plugin for Blesta
 *
 * @package blesta
 * @subpackage blesta.plugins.shurjopay
 * @author Md Wali Mosnad Ayshik
 * @copyright Copyright (c) [2024], [shurjoMukhi LTD.]
 * @copyright Copyright (c) 1998-2024, Web Services LLC
 * @link http://www.10corp.com/ 10CORP
 * @license [Since 2024]
 * @link [https://github.com/10corp/shurjopay-blesta/]
 */

function execute($query) 
{
	$database_info = Configure::get('Blesta.database_info');
	// Define database connection variables
	$serverName = $database_info['host'];
	$dbName = $database_info['database'];
	$userName = $database_info['user'];
	$password = $database_info['pass'];
	$db_port = isset($database_info['port']) ? $database_info['port'] : 3306; // Default to 3306 if not set
   
    // Connect to the database
    $conn = mysqli_connect($serverName, $userName, $password, $dbName, $db_port);

    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    if (mysqli_query($conn, $query)) {
     
    } else {
        echo "Error executing query: " . mysqli_error($conn);
    }

    mysqli_close($conn);
}

/**
 * Execute a SELECT SQL query and return the result set as an array.
 *
 * @param string $query The SELECT SQL query to execute
 * @return array The resulting data in an associative array format
 */
function get($query)
{
	$database_info = Configure::get('Blesta.database_info');
	// Define database connection variables
	$serverName = $database_info['host'];
	$dbName = $database_info['database'];
	$userName = $database_info['user'];
	$password = $database_info['pass'];
	$db_port = isset($database_info['port']) ? $database_info['port'] : 3306; // Default to 3306 if not set
    $data = array(); // Initialize an array to store the results

    // Connect to the database
    $conn = mysqli_connect($serverName, $userName, $password, $dbName, $db_port);

    // Check if connection was successful
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    // Execute the query and store the result
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    } else {
        echo "No rows returned or error: " . mysqli_error($conn);
    }

    // Close the database connection
    mysqli_close($conn);

    return $data; // Return the result set as an array
}
?>
