<?php
// Включение отображения всех ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Увеличиваем лимиты выполнения
set_time_limit(600); // 10 минут на выполнение
ini_set('memory_limit', '512M');

// Константы
define('LOG_FILE', $_SERVER['DOCUMENT_ROOT'] . '/upload/owen_import_log.txt'); // Файл для логирования

// Функция для логирования
function logMessage($message) {
    // Запись в файл лога
    $logMessage = date('[d.m.Y H:i:s] ') . $message . PHP_EOL;
    if (defined('LOG_FILE')) {
        @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    }
    
    // Вывод в браузер
    echo htmlspecialchars($message) . '<br>';
    // Немедленный вывод сообщения (для длительных операций)
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Функция для обработки ошибок
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    logMessage("Ошибка [$errno]: $errstr в файле $errfile на строке $errline");
    return true;
}
set_error_handler("customErrorHandler");

// Обработка исключений
function exceptionHandler($exception) {
    logMessage("Исключение: " . $exception->getMessage() . " в файле " . $exception->getFile() . " на строке " . $exception->getLine());
}
set_exception_handler("exceptionHandler");

// Выводим заголовок
echo "<h1>Импорт товаров OWEN</h1>";
logMessage("Скрипт запущен. Версия PHP: " . phpversion());

// Этап 1: Проверка наличия JSON-файла
$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/owenAPI.json';
logMessage("Проверка наличия файла: " . $jsonFilePath);

if (!file_exists($jsonFilePath)) {
    die("<p style='color:red'>Файл owenAPI.json не найден в корне сайта! Пожалуйста, загрузите его через FTP или панель управления хостингом.</p>");
}

// Этап 2: Чтение и анализ JSON-файла
logMessage("Чтение файла JSON...");
try {
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        die("Не удалось прочитать содержимое файла. Проверьте права доступа.");
    }
    
    logMessage("Файл прочитан успешно. Размер: " . strlen($jsonContent) . " байт");
    
    // Удаляем BOM-маркер, если он есть
    if (substr($jsonContent, 0, 3) === "\xEF\xBB\xBF") {
        logMessage("Обнаружен BOM-маркер, удаляем его");
        $jsonContent = substr($jsonContent, 3);
    }
    
    // Определяем кодировку
    $detectedEncoding = mb_detect_encoding($jsonContent, ['UTF-8', 'CP1251', 'KOI8-R'], true);
    logMessage("Обнаруженная кодировка: " . ($detectedEncoding ?: 'не определена'));
    
    // Конвертируем в UTF-8 при необходимости
    if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
        logMessage("Конвертируем из $detectedEncoding в UTF-8");
        $jsonContent = mb_convert_encoding($jsonContent, 'UTF-8', $detectedEncoding);
    }
    
    // Очищаем потенциально проблемные символы
    $jsonContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $jsonContent);
    
    // Пробуем декодировать JSON
    logMessage("Декодирование JSON...");
    $jsonData = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Ошибка декодирования JSON: " . json_last_error_msg());
        
        // Выводим часть содержимого для анализа
        echo "<h2>Анализ первых 1000 байт файла:</h2>";
        echo "<pre>" . htmlspecialchars(substr($jsonContent, 0, 1000)) . "...</pre>";
        
        // Выводим шестнадцатеричное представление для поиска проблемных символов
        echo "<h2>Шестнадцатеричное представление начала файла:</h2>";
        echo "<pre>" . chunk_split(bin2hex(substr($jsonContent, 0, 200)), 2, ' ') . "...</pre>";
        
        die("Не удалось декодировать JSON. Возможно, файл содержит ошибки синтаксиса или проблемы с кодировкой.");
    }
    
    // Проверяем структуру JSON-файла
    if (empty($jsonData)) {
        die("JSON файл декодирован, но он пуст или имеет неверную структуру.");
    }
    
    // Выводим структуру данных
    logMessage("JSON успешно декодирован");
    echo "<h2>Структура данных JSON:</h2>";
    echo "<pre>";
    
    if (isset($jsonData['categories'])) {
        echo "Категории: " . count($jsonData['categories']) . " шт.\n";
    } else {
        echo "Категории: не найдены\n";
    }
    
    if (isset($jsonData['products'])) {
        echo "Товары: " . count($jsonData['products']) . " шт.\n";
        // Выводим примеры товаров
        echo "\nПример первого товара:\n";
        $firstProduct = $jsonData['products'][0];
        echo "ID: " . $firstProduct['id'] . "\n";
        echo "Название: " . $firstProduct['name'] . "\n";
        echo "Артикул: " . $firstProduct['sku'] . "\n";
        
        if (isset($firstProduct['prices'])) {
            echo "Торговые предложения: " . count($firstProduct['prices']) . " шт.\n";
        }
    } else {
        echo "Товары: не найдены\n";
    }
    
    echo "</pre>";
    
    // Этап 3: Вывод рекомендаций для дальнейшей работы
    echo "<h2>Рекомендации для импорта:</h2>";
    echo "<ol>";
    echo "<li>JSON-файл успешно проанализирован и готов к импорту.</li>";
    echo "<li>Убедитесь, что инфоблок 'Товары КИПАСО' имеет ID=16 в вашей системе.</li>";
    echo "<li>Убедитесь, что инфоблок для торговых предложений имеет ID=17 в вашей системе.</li>";
    echo "<li>При необходимости измените ID инфоблоков в файле owen_import.php.</li>";
    echo "<li>При возникновении ошибок 500 проверьте настройки проактивной защиты Битрикса.</li>";
    echo "</ol>";
    
    // Кнопка для запуска полного импорта
    echo "<div style='margin-top:20px; padding:10px; background-color:#f0f0f0;'>";
    echo "<p><strong>Теперь, когда мы подтвердили корректность JSON-файла, можно запустить полный импорт.</strong></p>";
    echo "<p>Для запуска импорта необходимо решить проблему с подключением к Битриксу:</p>";
    echo "<ol>";
    echo "<li>Проверьте настройки проактивной защиты в админке Битрикса</li>";
    echo "<li>Временно отключите проактивную защиту</li>";
    echo "<li>Проверьте файл .htaccess на наличие ограничений</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color:red; padding:10px; border:1px solid red;'>";
    echo "Произошла ошибка при обработке файла: " . $e->getMessage();
    echo "</div>";
}

echo "<hr>";
echo "<p>Выполнение скрипта завершено.</p>";
?>
