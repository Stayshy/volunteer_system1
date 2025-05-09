<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "Events request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $organizer_id = $_GET['organizer_id'] ?? null;
    if ($organizer_id) {
        $stmt = $conn->prepare('SELECT id, title, description, event_date, max_participants, organizer_id FROM events WHERE organizer_id = ?');
        $stmt->bind_param('i', $organizer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $conn->query('SELECT id, title, description, event_date, max_participants, organizer_id FROM events');
        $events = $result->fetch_all(MYSQLI_ASSOC);
    }
    http_response_code(200);
    echo json_encode($events);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $title = $data['title'] ?? '';
    $description = $data['description'] ?? null;
    $event_date = $data['event_date'] ?? '';
    $max_participants = $data['max_participants'] ?? 0;
    $organizer_id = $data['organizer_id'] ?? '';

    if (empty($title) || empty($event_date) || empty($organizer_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: название, дата, организатор']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO events (title, description, event_date, max_participants, organizer_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssii', $title, $description, $event_date, $max_participants, $organizer_id);
    $stmt->execute();
    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $event_id = $_GET['id'] ?? '';
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID мероприятия']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM registrations WHERE event_id = ?');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();

    $stmt = $conn->prepare('DELETE FROM events WHERE id = ?');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Мероприятие не найдено']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
}

$conn->close();
?>