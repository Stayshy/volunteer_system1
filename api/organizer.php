<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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
file_put_contents('../debug.log', "Organizer request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $organizer_id = $_GET['id'] ?? '';
    $user_id = $_GET['user_id'] ?? '';
    $user_role = $_GET['user_role'] ?? '';

    file_put_contents('../debug.log', "GET params: organizer_id=$organizer_id, user_id=$user_id, user_role=$user_role\n", FILE_APPEND);

    if (empty($organizer_id)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID организатора\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID организатора']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id, name, email, phone, organization, avatar FROM organizers WHERE id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (SELECT organizers): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса (SELECT organizers): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $organizer_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (SELECT organizers): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса (SELECT organizers): ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result();
    $organizer = $result->fetch_assoc();

    if (!$organizer) {
        file_put_contents('../debug.log', "Организатор с ID $organizer_id не найден\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['error' => 'Организатор не найден']);
        exit;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as subscribers FROM subscriptions WHERE organizer_id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (subscribers): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса (subscribers): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $organizer_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (subscribers): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса (subscribers): ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result()->fetch_assoc();
    $organizer['subscribers'] = $result['subscribers'];

    $stmt = $conn->prepare('SELECT COUNT(*) as event_count FROM events WHERE organizer_id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (event_count): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса (event_count): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $organizer_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (event_count): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса (event_count): ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result()->fetch_assoc();
    $organizer['event_count'] = $result['event_count'];

    if ($user_id && $user_role) {
        $stmt = $conn->prepare('SELECT COUNT(*) as subscribed FROM subscriptions WHERE volunteer_id = ? AND organizer_id = ?');
        if (!$stmt) {
            file_put_contents('../debug.log', "Ошибка подготовки запроса (subscribed): " . $conn->error . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка подготовки запроса (subscribed): ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('ii', $user_id, $organizer_id);
        if (!$stmt->execute()) {
            file_put_contents('../debug.log', "Ошибка выполнения запроса (subscribed): " . $stmt->error . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка выполнения запроса (subscribed): ' . $stmt->error]);
            exit;
        }

        $result = $stmt->get_result()->fetch_assoc();
        $organizer['subscribed'] = $result['subscribed'] > 0;
    }

    http_response_code(200);
    echo json_encode($organizer);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        file_put_contents('../debug.log', "Ошибка: Неверный формат данных для PUT в organizer.php\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $id = $data['id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? null;
    $organization = $data['organization'] ?? null;
    $avatar = $data['avatar'] ?? null;

    if (empty($id) || empty($name) || empty($email)) {
        file_put_contents('../debug.log', "Ошибка: Пустые обязательные поля для PUT - id: $id, name: $name, email: $email\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: ID, имя, email']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE organizers SET name = ?, email = ?, phone = ?, organization = ?, avatar = ? WHERE id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки PUT-запроса: " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sssssi', $name, $email, $phone, $organization, $avatar, $id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения PUT-запроса: " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $stmt->error]);
        exit;
    }

    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'organization' => $organization,
            'avatar' => $avatar
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Организатор не найден']);
    }
}

$conn->close();
?>