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
file_put_contents('../debug.log', "Login request: $raw_input\n", FILE_APPEND);

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

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните email и пароль']);
    exit;
}

file_put_contents('../debug.log', "Login attempt: Email: $email\n", FILE_APPEND);

$stmt = $conn->prepare('SELECT id, name, email, phone, avatar, password FROM volunteers WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'avatar' => $row['avatar'],
            'role' => 'volunteer'
        ]);
        exit;
    }
}

$stmt = $conn->prepare('SELECT id, name, email, organization, password FROM organizers WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'organization' => $row['organization'],
            'role' => 'organizer'
        ]);
        exit;
    }
}

http_response_code(401);
echo json_encode(['error' => 'Неверный email или пароль']);

$conn->close();
?>