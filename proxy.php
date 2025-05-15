<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/proxy_error.log');

$configPath = 'Z:/home/localhost/config.php';
if (!file_exists($configPath)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found at Z:/home/localhost/config.php']);
    exit;
}
require_once $configPath;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

if (!defined('INTELLIGENCE_API_KEY') || empty(INTELLIGENCE_API_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured in config.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ch = curl_init('https://api.intelligence.io.solutions/api/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . INTELLIGENCE_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    file_put_contents(
        __DIR__ . '/proxy_log.txt',
        date('Y-m-d H:i:s') . " GET Models Response (HTTP $httpCode): " . substr($response ?: 'No response', 0, 1000) . "\n",
        FILE_APPEND
    );

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'API request failed', 'details' => $curlError]);
        exit;
    }

    if ($httpCode !== 200) {
        $result = json_decode($response, true);
        $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : "HTTP $httpCode error";
        http_response_code($httpCode);
        echo json_encode(['error' => $errorMsg]);
        exit;
    }

    http_response_code($httpCode);
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$maxSize = 1000000;
if (isset($_SERVER['CONTENT_LENGTH') && (int)$_SERVER['CONTENT_LENGTH'] > $maxSize) {
    http_response_code(413);
    echo json_encode(['error' => 'Request too large (max 1MB)']);
    exit;
}

$requestBody = file_get_contents('php://input');
if ($requestBody === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to read request body']);
    exit;
}

$data = json_decode($requestBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (empty($data['model'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Model is required']);
    exit;
}

file_put_contents(
    __DIR__ . '/proxy_log.txt',
    date('Y-m-d H:i:s') . " Request to model: " . $data['model'] . "\n",
    FILE_APPEND
);

$ch = curl_init('https://api.intelligence.io.solutions/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . INTELLIGENCE_API_KEY,
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$responseContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

$safeResponse = substr($response ?: 'No response', 0, 1000);
file_put_contents(
    __DIR__ . '/proxy_log.txt',
    date('Y-m-d H:i:s') . " POST Response (HTTP $httpCode, Content-Type: $responseContentType): $safeResponse\n",
    FILE_APPEND
);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'API request failed', 'details' => $curlError]);
    exit;
}

if ($httpCode !== 200) {
    $result = json_decode($response, true);
    $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : "HTTP $httpCode error";
    http_response_code($httpCode);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

if (empty($responseContentType) || strpos($responseContentType, 'application/json') === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Response from API is not JSON',
        'content_type' => $responseContentType,
        'response' => $safeResponse
    ]);
    exit;
}

if (empty($response)) {
    http_response_code(500);
    echo json_encode(['error' => 'Empty response from API']);
    exit;
}

$responseData = json_decode($response, true);
if (isset($responseData['choices'][0]['message']['content'])) {
    $content = $responseData['choices'][0]['message']['content'];
    $contentParts = explode('</think>\n\n', $content);
    $responseData['choices'][0]['message']['content'] = $contentParts[1] ?? $content;
    $response = json_encode($responseData);
}

http_response_code($httpCode);
echo $response;
?>