<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DATA_FILE', 'data.json');
define('BASE_URL', 'http://localhost/shortener/');

// Загружаем данные из файла
function loadData() {
    if (file_exists(DATA_FILE)) {
        $json = file_get_contents(DATA_FILE);
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Сохраняем данные в файл
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Генерация уникального идентификатора
function generateShortCode($length = 6) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

// AJAX-запрос на создание короткой ссылки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $originalUrl = trim($_POST['url']);

    // Проверка на корректность URL
    if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid URL.']);
        exit;
    }

    $data = loadData();

    // Проверяем, существует ли уже такая ссылка
    foreach ($data as $key => $entry) {
        if ($entry['original_url'] === $originalUrl) {
            echo json_encode(['status' => 'success', 'short_url' => BASE_URL . $key]);
            exit;
        }
    }

    // Генерируем уникальный ключ
    $uniqueKey = generateShortCode();
    while (isset($data[$uniqueKey])) {
        $uniqueKey = generateShortCode();
    }

    // Сохраняем новую запись
    $data[$uniqueKey] = [
        'original_url' => $originalUrl,
        'clicks' => 0
    ];
    saveData($data);

    echo json_encode(['status' => 'success', 'short_url' => BASE_URL . $uniqueKey]);
    exit;
}

// Редирект при переходе по короткой ссылке
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SERVER['REQUEST_URI'])) {
    $key = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $key = str_replace('shortener/', '', $key); // Удаляем часть пути, если нужно

    echo "Extracted key: " . htmlspecialchars($key) . "<br>"; // Отладка

    $data = loadData();

    if (isset($data[$key])) {
        echo "Key found in data.json<br>"; // Отладка
        $data[$key]['clicks']++;
        saveData($data);
        header('Location: ' . $data[$key]['original_url']);
        exit;
    } else {
        echo "Key not found in data.json<br>"; // Отладка
        http_response_code(404);
        echo "URL not found.";
        exit;
    }
}

http_response_code(400);
echo "Bad request.";
