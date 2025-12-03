<?php
/**
 * POS System Sync Helper
 * Functions to sync medicines with the external POS system
 */

/**
 * Sync medicine to POS system
 * 
 * @param array $medicineData Medicine data to sync
 * @return array Result with success status and message
 */
function syncMedicineToPOS($medicineData) {
    $posApiUrl = getPosApiUrl();
    
    // Prepare data
    $posData = [
        'action' => 'sync',
        'medicine_id' => $medicineData['id'] ?? $medicineData['medicine_id'] ?? '',
        'medicine_group' => $medicineData['category'] ?? $medicineData['medicine_group'] ?? 'Uncategorized',
        'medicine_name' => $medicineData['name'] ?? $medicineData['medicine_name'] ?? '',
        'generic_name' => $medicineData['generic_name'] ?? '',
        'dosage' => $medicineData['dosage'] ?? $medicineData['dosage_form'] ?? $medicineData['unit'] ?? '',
        'form' => $medicineData['form'] ?? $medicineData['dosage_form'] ?? $medicineData['unit'] ?? '',
        'stock' => $medicineData['quantity'] ?? $medicineData['stock'] ?? 0,
        'price' => $medicineData['price'] ?? 0.00
    ];
    
    if (empty($posData['medicine_id']) || empty($posData['medicine_name'])) {
        return [
            'success' => false,
            'message' => 'Missing required fields for POS sync'
        ];
    }
    
    return sendPosRequest($posApiUrl, $posData, 'sync');
}

/**
 * Update medicine in POS system
 * 
 * @param array $medicineData Medicine data to update
 * @return array Result with success status and message
 */
function updateMedicineInPOS($medicineData) {
    // For now, use the same function as sync
    // You can customize this if POS system has a separate update endpoint
    return syncMedicineToPOS($medicineData);
}

/**
 * Delete medicine from POS system
 * 
 * @param string $medicineId Medicine ID to delete
 * @return array Result with success status and message
 */
function deleteMedicineFromPOS($medicineId) {
    $posApiUrl = getPosApiUrl();
    
    $deleteData = [
        'action' => 'delete',
        'medicine_id' => $medicineId
    ];
    
    return sendPosRequest($posApiUrl, $deleteData, 'delete');
}

/**
 * Send POST request to POS server (form-encoded fallback)
 */
function sendPosRequest($url, array $data, $operation = 'sync') {
    if (!pingPosServer($url)) {
        error_log("POS {$operation} warning: Host appears unreachable, attempting request anyway.");
    }

    $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);
    $jsonHeaders = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payloadJson)
    ];

    // First attempt: JSON payload
    $jsonAttempt = performCurlRequest($url, $payloadJson, $jsonHeaders, $operation);
    if ($jsonAttempt['success']) {
        return $jsonAttempt;
    }

    // Fallback to form-encoded payload
    $payloadForm = http_build_query($data);
    $formHeaders = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($payloadForm)
    ];

    $formAttempt = performCurlRequest($url, $payloadForm, $formHeaders, $operation);
    if ($formAttempt['success']) {
        return $formAttempt;
    }

    // Final fallback: stream context
    $streamAttempt = performStreamRequest($url, $payloadForm, $formHeaders, $operation);
    if ($streamAttempt['success']) {
        return $streamAttempt;
    }

    return [
        'success' => false,
        'message' => $formAttempt['message'] ?? 'Unable to reach POS server'
    ];
}

/**
 * Fetch medicines directly from POS server (read-only)
 */
function fetchMedicinesFromPOS(array $params = []) {
    $posApiUrl = getPosApiUrl();
    $query = array_merge(['action' => 'fetch'], $params);
    return sendPosGetRequest($posApiUrl, $query, 'fetch');
}

function sendPosGetRequest($url, array $params, $operation = 'fetch') {
    if (!pingPosServer($url)) {
        error_log("POS {$operation} warning: Host appears unreachable, attempting GET anyway.");
    }

    $queryString = http_build_query($params);
    $fullUrl = $url . (strpos($url, '?') === false ? '?' : '&') . $queryString;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("POS {$operation} GET cURL Error: " . $curlError);
            return [
                'success' => false,
                'message' => 'POS fetch failed: ' . $curlError
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'POS fetch completed successfully',
                'response' => json_decode($response, true)
            ];
        }

        error_log("POS {$operation} GET HTTP Error: Code $httpCode, Response: " . $response);
        return [
            'success' => false,
            'message' => "POS fetch failed with HTTP code: $httpCode",
            'response' => $response
        ];
    }

    $response = @file_get_contents($fullUrl);
    if ($response === false) {
        $error = error_get_last();
        error_log("POS {$operation} GET stream Error: " . ($error['message'] ?? 'Unknown error'));
        return [
            'success' => false,
            'message' => 'POS fetch failed: Unable to connect to POS system'
        ];
    }

    return [
        'success' => true,
        'message' => 'POS fetch completed successfully',
        'response' => json_decode($response, true)
    ];
}

function performCurlRequest($url, $payload, $headers, $operation) {
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'cURL not available',
            'type' => 'curl_unavailable'
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("POS {$operation} cURL Error: " . $curlError);
        return [
            'success' => false,
            'message' => 'POS sync failed: ' . $curlError,
            'type' => 'curl_error'
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'POS operation completed successfully',
            'response' => json_decode($response, true)
        ];
    }

    error_log("POS {$operation} HTTP Error: Code $httpCode, Response: " . $response);
    return [
        'success' => false,
        'message' => "POS sync failed with HTTP code: $httpCode",
        'response' => $response,
        'type' => 'http_error'
    ];
}

function performStreamRequest($url, $payload, $headers, $operation) {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 20
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = error_get_last();
        error_log("POS {$operation} stream Error: " . ($error['message'] ?? 'Unknown error'));
        return [
            'success' => false,
            'message' => 'POS sync failed: Unable to connect to POS system',
            'type' => 'stream_error'
        ];
    }

    return [
        'success' => true,
        'message' => 'POS operation completed successfully',
        'response' => json_decode($response, true)
    ];
}

function pingPosServer($url) {
    $parts = parse_url($url);
    $host = $parts['host'] ?? null;
    if (!$host) {
        return false;
    }

    $port = ($parts['scheme'] ?? 'http') === 'https' ? 443 : 80;
    $fp = @fsockopen($host, $port, $errno, $errstr, 3);
    if (!$fp) {
        error_log("POS server unreachable: {$host}:{$port} - $errstr ($errno)");
        return false;
    }
    fclose($fp);
    return true;
}

function getPosApiUrl() {
    if (defined('POS_API_URL')) {
        return POS_API_URL;
    }

    // Default URL (can be overridden by defining POS_API_URL in config)
    return 'http://26.238.3.101/ERPs/POS/SIA/server/api/main/get_medicines.php';
}
