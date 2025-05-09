<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Обработка предварительного запроса OPTIONS
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

// Отладка: логируем запрос
$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "Profile request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['id'] ?? '';
    $role = $_GET['role'] ?? '';
    if (empty($user_id) || !in_array($role, ['volunteer', 'organizer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID пользователя и роль']);
        exit;
    }

    if ($role === 'volunteer') {
        $stmt = $conn->prepare('SELECT id, name, email, phone, avatar FROM volunteers WHERE id = ?');
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, organization FROM organizers WHERE id = ?');
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Пользователь не найден']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $id = $data['id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? null; // Email теперь необязательный
    $phone = $data['phone'] ?? null;
    $avatar = $data['avatar'] ?? null;
    $role = $data['role'] ?? '';

    // Проверка обязательных полей
    $missing_fields = [];
    if (empty($id)) $missing_fields[] = 'ID';
    if (empty($name)) $missing_fields[] = 'имя';
    if (!in_array($role, ['volunteer', 'organizer'])) $missing_fields[] = 'роль';

    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните обязательные поля: ' . implode(', ', $missing_fields)]);
        exit;
    }

    // Проверка уникальности email (если email указан)
    if (!empty($email)) {
        $stmt = $conn->prepare('SELECT id FROM volunteers WHERE email = ? AND id != ? UNION SELECT id FROM organizers WHERE email = ? AND id != ?');
        $stmt->bind_param('sisi', $email, $id, $email, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email уже используется']);
            exit;
        }
    }

    try {
        if ($role === 'volunteer') {
            $stmt = $conn->prepare('UPDATE volunteers SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $name, $email, $phone, $avatar, $id);
        } else {
            $stmt = $conn->prepare('UPDATE organizers SET name = ?, email = ?, organization = ? WHERE id = ?');
            $stmt->bind_param('sssi', $name, $email, $avatar, $id); // Используем avatar как organization
        }
        $stmt->execute();
        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'avatar' => $avatar
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Пользователь не найден или данные не изменены']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при обновлении профиля: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
}

$conn->close();
?>