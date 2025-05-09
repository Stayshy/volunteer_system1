<?php
$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    die('Ошибка подключения: ' . $conn->connect_error);
}
echo 'Подключение к базе данных успешно!';
$result = $conn->query('SELECT * FROM volunteers WHERE email = "ivan@example.com"');
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo '<br>Найден пользователь: ' . $row['name'] . ', Email: ' . $row['email'] . ', Пароль: ' . $row['password'];
} else {
    echo '<br>Пользователь ivan@example.com не найден';
}
$conn->close();
?>

