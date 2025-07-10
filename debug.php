<?php
// Отключаем все возможные ограничения
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Отключаем ограничение по времени выполнения
set_time_limit(0);

// Добавляем заголовок для предотвращения кеширования
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Базовая информация о PHP
echo "<h1>Базовая диагностика</h1>";
echo "<p>PHP версия: " . phpversion() . "</p>";
echo "<p>Сервер: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Путь к document root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<hr>";

// Проверка чтения файла без подключения Битрикса
echo "<h2>Проверка доступа к файлу owenAPI.json</h2>";
$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/owenAPI.json';
if (file_exists($jsonFilePath)) {
    echo "<p>Файл существует!</p>";
    
    if (is_readable($jsonFilePath)) {
        echo "<p>Файл доступен для чтения.</p>";
        
        // Попытка прочитать файл
        $content = file_get_contents($jsonFilePath);
        if ($content !== false) {
            echo "<p>Файл успешно прочитан. Размер: " . strlen($content) . " байт.</p>";
            echo "<p>Первые 200 символов в шестнадцатеричном представлении:<br>";
            echo bin2hex(substr($content, 0, 100)) . "</p>";
            
            // Проверка наличия BOM-маркера
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                echo "<p style='color:orange;'>Обнаружен UTF-8 BOM-маркер в начале файла.</p>";
            }
        } else {
            echo "<p style='color:red;'>ОШИБКА: Не удалось прочитать файл!</p>";
        }
    } else {
        echo "<p style='color:red;'>ОШИБКА: Файл не доступен для чтения!</p>";
    }
} else {
    echo "<p style='color:red;'>ОШИБКА: Файл не найден!</p>";
}

echo "<hr>";
echo "<h2>Проверка директории для записи логов</h2>";

// Проверка директории upload
$uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/upload';
if (is_dir($uploadPath)) {
    echo "<p>Директория существует!</p>";
    
    if (is_writable($uploadPath)) {
        echo "<p>Директория доступна для записи.</p>";
        
        // Пробуем создать тестовый файл
        $testFile = $uploadPath . '/test_debug_' . time() . '.txt';
        $result = file_put_contents($testFile, 'Test content');
        if ($result !== false) {
            echo "<p style='color:green;'>Успешно создан тестовый файл: " . basename($testFile) . "</p>";
            
            // Удаляем тестовый файл
            if (unlink($testFile)) {
                echo "<p>Тестовый файл успешно удален.</p>";
            } else {
                echo "<p style='color:orange;'>Не удалось удалить тестовый файл. Проверьте права доступа.</p>";
            }
        } else {
            echo "<p style='color:red;'>ОШИБКА: Не удалось создать тестовый файл!</p>";
        }
    } else {
        echo "<p style='color:red;'>ОШИБКА: Директория не доступна для записи!</p>";
    }
} else {
    echo "<p style='color:red;'>ОШИБКА: Директория не существует!</p>";
    
    // Пробуем создать директорию
    echo "<p>Попытка создать директорию...</p>";
    if (mkdir($uploadPath, 0775, true)) {
        echo "<p style='color:green;'>Директория успешно создана!</p>";
    } else {
        echo "<p style='color:red;'>Не удалось создать директорию.</p>";
    }
}

// Не подключаем Битрикс, чтобы исключить его как источник проблемы
echo "<hr>";
echo "<h2>Проверка завершена без подключения Битрикса</h2>";
echo "<p>Если вы видите эту страницу, то проблема, вероятно, связана с Битриксом, а не с PHP.</p>";
?>
