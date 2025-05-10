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
    $news_id = $_GET['news_id'] ?? '';
    if (empty($news_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID новости']);
        exit;
    }

    $stmt = $conn->prepare('
        SELECT c.id, c.content, c.created_at,
               CASE
                   WHEN c.user_role = "volunteer" THEN (SELECT name FROM volunteers WHERE id = c.user_id)
                   WHEN c.user_role = "organizer" THEN (SELECT name FROM organizers WHERE id = c.user_id)
               END as user_name
        FROM comments c
        WHERE c.news_id = ?
        ORDER BY c.created_at DESC
    ');
    $stmt->bind_param('i', $news_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($comments);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $news_id = $data['news_id'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $user_role = $data['user_role'] ?? '';
    $content = $data['content'] ?? '';

    if (empty($news_id) || empty($user_id) || empty($user_role) || empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID новости, пользователя, роль и текст комментария']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO comments (news_id, user_id, user_role, content) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiss', $news_id, $user_id, $user_role, $content);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        http_response_code(201);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при добавлении комментария']);
    }
}

$conn->close();
?>