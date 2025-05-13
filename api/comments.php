<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Обработка OPTIONS-запроса (для CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Подключение к базе данных
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
        SELECT c.id, c.content, c.created_at, c.user_id, c.user_role,
               CASE
                   WHEN c.user_role = "volunteer" THEN (SELECT name FROM volunteers WHERE id = c.user_id)
                   WHEN c.user_role = "organizer" THEN (SELECT name FROM organizers WHERE id = c.user_id)
               END as user_name
        FROM comments c
        WHERE c.news_id = ?
        ORDER BY c.created_at ASC
    ');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки SQL-запроса: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('i', $news_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения SQL-запроса: ' . $stmt->error]);
        exit;
    }
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($comments);
    $stmt->close();
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

    // Проверяем, существует ли пользователь
    if ($user_role === 'volunteer') {
        $stmt = $conn->prepare("SELECT id FROM volunteers WHERE id = ?");
    } elseif ($user_role === 'organizer') {
        $stmt = $conn->prepare("SELECT id FROM organizers WHERE id = ?");
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Недопустимая роль пользователя']);
        exit;
    }

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки SQL-запроса (проверка пользователя): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения SQL-запроса (проверка пользователя): ' . $stmt->error]);
        exit;
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Пользователь с указанным ID не найден']);
        exit;
    }
    $stmt->close();

    // Вставляем комментарий (без user_name, так как столбца нет)
    $stmt = $conn->prepare('INSERT INTO comments (news_id, user_id, user_role, content) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки SQL-запроса (insert): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('iiss', $news_id, $user_id, $user_role, $content);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения SQL-запроса (insert): ' . $stmt->error]);
        exit;
    }

    if ($stmt->affected_rows > 0) {
        http_response_code(201);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при добавлении комментария']);
    }
    $stmt->close();
}

$conn->close();
?>