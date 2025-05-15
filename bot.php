<?php
// Enable error logging, disable display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_error.log');

$configPath = 'Z:/home/localhost/config.php';
if (!file_exists($configPath)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['response' => 'Configuration file not found at Z:/home/localhost/config.php']);
    exit;
}
require_once $configPath;

header('Content-Type: application/json');

if (!defined('INTELLIGENCE_API_KEY') || empty(INTELLIGENCE_API_KEY)) {
    http_response_code(500);
    echo json_encode(['response' => 'API key not configured in config.php']);
    exit;
}

$apiKey = INTELLIGENCE_API_KEY;
$url = "https://api.intelligence.io.solutions/api/v1/chat/completions";
$userMessage = isset($_POST['message']) ? trim($_POST['message']) : "Подбери мероприятие для волонтера";
$model = isset($_POST['model']) ? trim($_POST['model']) : "deepseek-ai/DeepSeek-R1";

if (empty($userMessage)) {
    echo json_encode(['response' => 'Пустой запрос']);
    exit;
}

// Database search for events
$conn = new mysqli("localhost", "root", "", "volunteer_system");
$botResponse = "";
$events = [];

if ($conn->connect_error) {
    $botResponse = "Ошибка подключения к базе данных: " . $conn->connect_error;
} else {
    // Search for matching active events
    $stmt = $conn->prepare("SELECT title FROM events WHERE title LIKE ? AND status = 'active'");
    if ($stmt === false) {
        $botResponse = "Ошибка подготовки SQL: " . $conn->error;
    } else {
        $search = "%$userMessage%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row['title'];
        }
        $stmt->close();
    }

    // If no matches, get popular active events
    if (empty($events)) {
        $stmt = $conn->prepare("SELECT title FROM events WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
        if ($stmt === false) {
            $botResponse = "Ошибка подготовки SQL: " . $conn->error;
        } else {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $events[] = $row['title'];
            }
            $stmt->close();
        }
    }
    $conn->close();
}

// Prepare API prompt with events
$eventsList = !empty($events) ? implode(", ", $events) : "нет доступных мероприятий";
$prompt = "Отвечай только на русском языке, кратко, только сам ответ, без размышлений и лишних слов. Рекомендуй мероприятие из списка: $eventsList. Если запрос пользователя не соответствует списку, выбери подходящее мероприятие из списка или ответь 'Нет подходящих мероприятий'. Для общих вопросов (например, 'Как дела?') отвечай лаконично и дружелюбно.";

$data = [
    "model" => $model,
    "messages" => [
        ["role" => "system", "content" => $prompt],
        ["role" => "user", "content" => $userMessage]
    ],
    "temperature" => 0.7,
    "max_completion_tokens" => 200 // Increased for longer responses
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log API request details
file_put_contents(
    __DIR__ . '/bot_api.log',
    date('Y-m-d H:i:s') . " API Response (HTTP $httpCode): " . substr($response ?: 'No response', 0, 1000) . "\n",
    FILE_APPEND
);

if ($response === false) {
    echo json_encode(['response' => "Ошибка API: $curlError"]);
    exit;
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['response' => "Неверный JSON в ответе API: " . json_last_error_msg()]);
    exit;
}

if ($httpCode !== 200) {
    $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : "HTTP $httpCode error";
    echo json_encode(['response' => "API Error: $errorMsg"]);
    exit;
}

$content = $result['choices'][0]['message']['content'] ?? "Ошибка ответа";
$contentParts = explode('</think>\n\n', $content);
$botResponse = $contentParts[1] ?? $content;

echo json_encode(['response' => $botResponse]);
?>