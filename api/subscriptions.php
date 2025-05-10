<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $organizer_id = $_GET['organizer_id'] ?? '';
    if (empty($organizer_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID организатора']);
        exit;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as subscribers FROM subscriptions WHERE organizer_id = ?');
    $stmt->bind_param('i', $organizer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    http_response_code(200);
    echo json_encode(['subscribers' => $result['subscribers']]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $user_id = $data['user_id'] ?? '';
    $user_role = $data['user_role'] ?? '';
    $organizer_id = $data['organizer_id'] ?? '';

    if (empty($user_id) || empty($user_role) || empty($organizer_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID пользователя, роль и ID организатора']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id FROM subscriptions WHERE user_id = ? AND user_role = ? AND organizer_id = ?');
    $stmt->bind_param('isi', $user_id, $user_role, $organizer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare('DELETE FROM subscriptions WHERE user_id = ? AND user_role = ? AND organizer_id = ?');
        $stmt->bind_param('isi', $user_id, $user_role, $organizer_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO subscriptions (user_id, user_role, organizer_id) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $user_id, $user_role, $organizer_id);
        $stmt->execute();
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
}

$conn->close();
?>