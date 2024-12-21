<?php
require "../baseInfo.php";

// Securely retrieving database credentials
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUserName = getenv('DB_USERNAME') ?: $dbUserName;
$dbPassword = getenv('DB_PASSWORD') ?: $dbPassword;
$dbName = getenv('DB_NAME') ?: $dbName;

// Establishing a secure connection to the database
$connection = new mysqli($dbHost, $dbUserName, $dbPassword, $dbName);
if ($connection->connect_error) {
    error_log("Database connection failed: " . $connection->connect_error);
    die("Database connection failed. Check logs for details.");
}

// Ensuring UTF-8 encoding for the database connection
if (!$connection->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $connection->error);
    die("Failed to set database charset. Check logs for details.");
}

// SQL statements for managing tables
$arrays = [
    "CREATE TABLE IF NOT EXISTS `send_list` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `offset` INT(11) NOT NULL DEFAULT 0,
        `type` VARCHAR(20) NOT NULL,
        `text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
        `chat_id` BIGINT(20),
        `message_id` INT(11),
        `file_id` VARCHAR(500),
        `state` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    );",
    "DROP TABLE IF EXISTS `refered_users`;",
    "DROP TABLE IF EXISTS `server_accounts`;",
    "CREATE TABLE IF NOT EXISTS `server_config_temp` AS SELECT * FROM `server_config` WHERE 1=0;",
    "DROP TABLE IF EXISTS `server_config`;",
    "RENAME TABLE `server_config_temp` TO `server_config`;",
    "CREATE TABLE IF NOT EXISTS `discounts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `hash_id` VARCHAR(100) NOT NULL,
        `type` VARCHAR(10) NOT NULL,
        `amount` INT(11) NOT NULL,
        `expire_date` INT(11) NOT NULL,
        `expire_count` INT(11) NOT NULL,
        `used_by` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`)
    );",
];

// Using transactions for atomic execution
$connection->begin_transaction();
try {
    foreach ($arrays as $sql) {
        if ($connection->query($sql) === TRUE) {
            echo "Query executed successfully.\n";
        } else {
            throw new Exception("Error executing query: " . $sql . "\nError: " . $connection->error);
        }
    }
    $connection->commit();
    error_log("All SQL statements executed successfully.");
} catch (Exception $e) {
    $connection->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    echo "An error occurred. All changes were rolled back. Check logs for details.\n";
}

// Closing the connection
$connection->close();
?>
