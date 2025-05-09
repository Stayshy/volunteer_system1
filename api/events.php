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
    $status = $_GET['status'] ?? null;

    if (!$organizer_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID организатора']);
        exit;
    }

    $query = 'SELECT id, title, description, event_date, max_participants, organizer_id, status, hours FROM events WHERE organizer_id = ?';
    $params = [$organizer_id];
    $types = 'i';

    if ($status) {
        $query .= ' AND status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);

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
    $status = $data['status'] ?? 'active';
    $hours = $data['hours'] ?? 0;

    if (empty($title) || empty($event_date) || empty($organizer_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: название, дата, организатор']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO events (title, description, event_date, max_participants, organizer_id, status, hours) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sssisis', $title, $description, $event_date, $max_participants, $organizer_id, $status, $hours);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $stmt->error]);
        exit;
    }

    if ($stmt->affected_rows === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Мероприятие не создано']);
        exit;
    }

    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $id = $data['id'] ?? '';
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? null;
    $event_date = $data['event_date'] ?? '';
    $max_participants = $data['max_participants'] ?? 0;
    $status = $data['status'] ?? 'active';
    $hours = $data['hours'] ?? 0;

    if (empty($id) || empty($title) || empty($event_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: ID, название, дата']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, max_participants = ?, status = ?, hours = ? WHERE id = ?');
    $stmt->bind_param('sssissi', $title, $description, $event_date, $max_participants, $status, $hours, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Мероприятие не найдено']);
    }
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