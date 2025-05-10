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
    file_put_contents('../debug.log', "Ошибка подключения к базе данных: " . $conn->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $conn->connect_error]);
    exit;
}

$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "News request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? '';
    $user_role = $_GET['user_role'] ?? '';
    $status = $_GET['status'] ?? null;
    $organizer_id = $_GET['organizer_id'] ?? null;

    file_put_contents('../debug.log', "GET params: user_id=$user_id, user_role=$user_role, status=$status, organizer_id=$organizer_id\n", FILE_APPEND);

    if (empty($user_id) || empty($user_role)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID пользователя и роль\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID пользователя и роль']);
        exit;
    }

    $query = '
        SELECT n.id, n.organizer_id, n.event_id, n.title, n.content, n.created_at,
               o.name as organizer_name,
               e.title as event_title, e.event_date, e.status,
               (SELECT COUNT(*) FROM likes l WHERE l.news_id = n.id) as likes,
               (SELECT COUNT(*) FROM likes l WHERE l.news_id = n.id AND l.user_id = ? AND l.user_role = ?) as liked,
               (SELECT COUNT(*) FROM subscriptions s WHERE s.volunteer_id = ? AND s.organizer_id = n.organizer_id) as subscribed
        FROM news n
        JOIN organizers o ON n.organizer_id = o.id
        LEFT JOIN events e ON n.event_id = e.id
    ';
    $params = [$user_id, $user_role, $user_id];
    $types = 'iss';

    if ($organizer_id) {
        $query .= ' WHERE n.organizer_id = ?';
        $params[] = $organizer_id;
        $types .= 'i';
    }

    if ($status && !$organizer_id) {
        $query .= ' WHERE e.status = ?';
        $params[] = $status;
        $types .= 's';
    } elseif ($status) {
        $query .= ' AND e.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $query .= ' ORDER BY n.created_at DESC';

    file_put_contents('../debug.log', "SQL Query: $query\n", FILE_APPEND);
    file_put_contents('../debug.log', "Params: " . implode(', ', $params) . "\n", FILE_APPEND);

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки GET-запроса: " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения GET-запроса: " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result();
    $news = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($news);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        file_put_contents('../debug.log', "Ошибка: Неверный формат данных для POST\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $organizer_id = $data['organizer_id'] ?? '';
    $event_id = isset($data['event_id']) && $data['event_id'] !== '' ? $data['event_id'] : null;
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';

    if (empty($organizer_id) || empty($title) || empty($content)) {
        file_put_contents('../debug.log', "Ошибка: Пустые обязательные поля - organizer_id: $organizer_id, title: $title, content: $content\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: организатор, заголовок, содержание']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO news (organizer_id, event_id, title, content) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки POST-запроса: " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('iiss', $organizer_id, $event_id, $title, $content);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения POST-запроса: " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $stmt->error]);
        exit;
    }

    if ($stmt->affected_rows > 0) {
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при создании новости']);
    }
}

$conn->close();
?>