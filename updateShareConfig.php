<?php

require "baseInfo.php";
require "config.php";

use Illuminate\Support\Facades\Cache;

/**
 * Fetch pending server plans with caching for performance.
 */
function getPendingServerPlans($connection) {
    return Cache::remember('pending_server_plans', 3600, function() use ($connection) {
        $stmt = $connection->prepare("SELECT id, server_id, inbound_id, type FROM server_plans WHERE (type IS NULL OR type = '') AND inbound_id != 0");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $connection->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Error fetching server plans: " . $stmt->error);
        }
        $plans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $plans;
    });
}

/**
 * Update the type field in the database securely.
 */
function updateServerPlanType($connection, $netType, $rowId) {
    $stmt = $connection->prepare("UPDATE server_plans SET type = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare update statement: " . $connection->error);
    }
    $netType = htmlspecialchars($netType, ENT_QUOTES, 'UTF-8');
    $stmt->bind_param("si", $netType, $rowId);
    $stmt->execute();
    if ($stmt->error) {
        throw new Exception("Error updating server plan: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * Fetch server configurations securely.
 */
function fetchServerConfig($serverId) {
    try {
        $serverId = intval($serverId); // Sanitize server ID
        $response = getJson($serverId);
        if (!$response || !isset($response->obj)) {
            throw new Exception("Invalid response or missing 'obj' field");
        }
        return $response->obj;
    } catch (Exception $e) {
        error_log("Error fetching server config: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate and sanitize the input data.
 */
function validateAndSanitizeInput($input) {
    return [
        'server_id' => filter_var($input['server_id'], FILTER_VALIDATE_INT) ?: null,
        'inbound_id' => filter_var($input['inbound_id'], FILTER_VALIDATE_INT) ?: null,
        'type' => isset($input['type']) ? htmlspecialchars($input['type'], ENT_QUOTES, 'UTF-8') : ''
    ];
}

/**
 * Log error messages in a secure format.
 */
function logError($message) {
    error_log("[SECURITY] " . $message);
}

/**
 * Main logic for processing server plans.
 */
try {
    $serverPlans = getPendingServerPlans($connection);

    if (!empty($serverPlans)) {
        foreach ($serverPlans as $plan) {
            $plan = validateAndSanitizeInput($plan);
            $serverId = $plan['server_id'];
            $inboundId = $plan['inbound_id'];
            $rowId = $plan['id'];

            if (!$serverId || !$inboundId) {
                logError("Invalid server plan data: " . json_encode($plan));
                continue;
            }

            $serverConfig = fetchServerConfig($serverId);

            if ($serverConfig) {
                foreach ($serverConfig as $config) {
                    if (isset($config->id) && $config->id == $inboundId) {
                        $streamSettings = json_decode($config->streamSettings);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            logError("Invalid JSON in streamSettings for server ID $serverId");
                            continue;
                        }
                        $netType = $streamSettings->network ?? null;
                        if (!is_null($netType)) {
                            updateServerPlanType($connection, $netType, $rowId);
                        }
                        break;
                    }
                }
            } else {
                logError("No server configuration found for server ID $serverId");
            }
        }
        echo "Operation completed successfully. Please refresh the page.";
    } else {
        echo "No matching records found.";
    }
} catch (Exception $e) {
    logError("Critical error: " . $e->getMessage());
    echo "A critical error occurred. Please check the logs.";
}

?>
