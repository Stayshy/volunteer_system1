<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешён']);
    exit;
}

$deepSeekApiKey = 'sk-a387906f60a2488080eb58e0cfffeb28'; // ВСТАВЬ СВОЙ API-КЛЮЧ DEEPSEEK, НАПРИМЕР: ds-xxxxxxxxxxxxxxxxxxxxxxxx
$deepSeekUrl = 'https://api.deepseek.com/v1/chat/completions';

// Проверка API-ключа
if (empty($deepSeekApiKey) || $deepSeekApiKey === 'sk-a387906f60a2488080eb58e0cfffeb28') {
    http_response_code(500);
    echo json_encode(['error' => 'API-ключ DeepSeek отсутствует или не заменён в proxy.php']);
    file_put_contents('deepseek_log.txt', "Error: API-ключ DeepSeek отсутствует или не заменён в proxy.php\n", FILE_APPEND);
    exit;
}

$requestBody = file_get_contents('php://input');
if (!$requestBody) {
    http_response_code(400);
    echo json_encode(['error' => 'Отсутствует тело запроса']);
    file_put_contents('deepseek_log.txt', "Error: Отсутствует тело запроса\n", FILE_APPEND);
    exit;
}

// Логируем запрос
file_put_contents('deepseek_log.txt', "Request: $requestBody\n", FILE_APPEND);

$ch = curl_init($deepSeekUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $deepSeekApiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$responseContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

// Логируем ответ и возможные ошибки cURL
file_put_contents('deepseek_log.txt', "Response (HTTP $httpCode, Content-Type: $responseContentType): $response\n", FILE_APPEND);
if ($curlError) {
    file_put_contents('deepseek_log.txt', "cURL Error: $curlError\n", FILE_APPEND);
}

// Проверяем, является ли ответ JSON
if (strpos($responseContentType, 'application/json') !== false) {
    http_response_code($httpCode);
    echo $response;
} else {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Ответ от DeepSeek API не является JSON: ' . $response]);
    file_put_contents('deepseek_log.txt', "Error: Ответ от DeepSeek API не является JSON: $response\n", FILE_APPEND);
}
?>