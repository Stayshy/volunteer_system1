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

$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "Register request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
    exit;
}

$data = json_decode($raw_input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? '';
$phone = $data['phone'] ?? null;
$organization = $data['organization'] ?? null;

if (empty($name) || empty($email) || empty($password) || !in_array($role, ['volunteer', 'organizer'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните все обязательные поля и выберите роль']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM volunteers WHERE email = ? UNION SELECT id FROM organizers WHERE email = ?');
$stmt->bind_param('ss', $email, $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Пользователь с таким email уже существует']);
    exit;
}

// Хеширование пароля
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    if ($role === 'volunteer') {
        $stmt = $conn->prepare('INSERT INTO volunteers (name, email, phone, password) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $phone, $hashed_password);
    } else {
        $stmt = $conn->prepare('INSERT INTO organizers (name, email, organization, password) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $organization, $hashed_password);
    }
    $stmt->execute();
    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Регистрация успешна']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при регистрации: ' . $e->getMessage()]);
}

$conn->close();
?>