<?php

// Load database configuration
include "baseInfo.php";

try {
    // Validate database credentials
    if (empty($dbUserName) || empty($dbPassword) || empty($dbName)) {
        throw new Exception("Database configuration is incomplete.");
    }

    // Create a secure PDO connection
    $dsn = "mysql:host=localhost;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    $connection = new PDO($dsn, $dbUserName, $dbPassword, $options);

    // Start a transaction to ensure atomic execution
    $connection->beginTransaction();

    // Table definitions
    $tables = [
        "CREATE TABLE IF NOT EXISTS `chats` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `create_date` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            `category` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            `state` TINYINT UNSIGNED NOT NULL,
            `rate` TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `chats_info` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `chat_id` INT UNSIGNED NOT NULL,
            `sent_date` BIGINT UNSIGNED NOT NULL,
            `msg_type` VARCHAR(50) DEFAULT NULL,
            `text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `discounts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `hash` VARCHAR(255) NOT NULL UNIQUE,
            `expiry_date` BIGINT UNSIGNED NOT NULL,
            `percentage` TINYINT UNSIGNED NOT NULL CHECK (`percentage` BETWEEN 0 AND 100),
            PRIMARY KEY (`id`),
            CONSTRAINT `chk_expiry_date` CHECK (`expiry_date` > UNIX_TIMESTAMP())
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Execute table creation queries
    foreach ($tables as $query) {
        $connection->exec($query);
    }

    // Commit the transaction
    $connection->commit();

    echo "Tables created successfully.";

} catch (PDOException $e) {
    // Roll back transaction on error
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    // Log error with obfuscated sensitive details
    error_log("Database error occurred. Message: [Hidden for security reasons]");
    error_log("Trace: " . $e->getTraceAsString());

    // Output generic error message for production
    exit(defined('DEBUG') && DEBUG ? "A database error occurred: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : "A database error occurred. Please try again later.");

} catch (Exception $e) {
    // Handle non-database errors
    error_log("General error occurred. Message: [Hidden for security reasons]");
    error_log("Trace: " . $e->getTraceAsString());

    // Output generic error message for production
    exit(defined('DEBUG') && DEBUG ? "An error occurred: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : "An error occurred. Please check the configuration.");
}

?>
