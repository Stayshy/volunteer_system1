<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $news_id = $data['news_id'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $user_role = $data['user_role'] ?? '';

    if (empty($news_id) || empty($user_id) || empty($user_role)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID новости, пользователя и роль']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id FROM likes WHERE news_id = ? AND user_id = ? AND user_role = ?');
    $stmt->bind_param('iis', $news_id, $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare('DELETE FROM likes WHERE news_id = ? AND user_id = ? AND user_role = ?');
        $stmt->bind_param('iis', $news_id, $user_id, $user_role);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO likes (news_id, user_id, user_role) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $news_id, $user_id, $user_role);
        $stmt->execute();
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
}

$conn->close();
?>