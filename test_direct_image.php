<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Функция для проверки доступности изображения
function checkImageAvailability($url) {
    echo "<h3>Проверка доступности изображения</h3>";
    echo "<p>URL: {$url}</p>";
    
    // Создаем контекст с имитацией браузера
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                      "Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8\r\n" .
                      "Referer: https://owen.ru/\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    
    // Проверяем с file_get_contents
    echo "<h4>Проверка через file_get_contents:</h4>";
    $start = microtime(true);
    $fileContent = @file_get_contents($url, false, $context);
    $time = microtime(true) - $start;
    
    if ($fileContent !== false) {
        $fileSize = strlen($fileContent);
        echo "<p style='color:green'>Доступно! Размер: {$fileSize} байт, время: " . round($time, 2) . " сек.</p>";
        
        // Сохраняем содержимое во временный файл для проверки
        $tempFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/test_image_' . md5($url) . '.tmp';
        file_put_contents($tempFile, $fileContent);
        
        // Проверяем, что это действительно изображение
        $imageInfo = @getimagesize($tempFile);
        if ($imageInfo !== false) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mime = $imageInfo['mime'];
            echo "<p style='color:green'>Это изображение: {$width}x{$height}, тип: {$mime}</p>";
            
            // Показываем изображение
            echo "<img src='{$url}' style='max-width:300px; border:1px solid #ccc;' />";
        } else {
            echo "<p style='color:red'>Файл не является изображением!</p>";
            echo "<pre>" . htmlspecialchars(substr($fileContent, 0, 200)) . "...</pre>";
        }
        
        // Удаляем временный файл
        @unlink($tempFile);
    } else {
        echo "<p style='color:red'>Недоступно через file_get_contents! Время: " . round($time, 2) . " сек.</p>";
    }
    
    // Проверяем с curl если доступен
    if (function_exists('curl_init')) {
        echo "<h4>Проверка через CURL:</h4>";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Referer: https://owen.ru/'
        ]);
        
        $start = microtime(true);
        $curlContent = curl_exec($ch);
        $time = microtime(true) - $start;
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $curlSize = strlen($curlContent);
            echo "<p style='color:green'>Доступно через CURL! HTTP код: {$httpCode}, Content-Type: {$contentType}, размер: {$curlSize} байт, время: " . round($time, 2) . " сек.</p>";
        } else {
            echo "<p style='color:red'>Недоступно через CURL! HTTP код: {$httpCode}, Content-Type: {$contentType}, время: " . round($time, 2) . " сек.</p>";
        }
    } else {
        echo "<p style='color:orange'>CURL не установлен в PHP</p>";
    }
}

// Сравниваем оригинальный URL и URL с заменой поддомена
function compareUrls($originalUrl) {
    echo "<div style='margin:20px 0; padding:20px; border:1px solid #ccc;'>";
    echo "<h2>Сравнение доступа к изображениям</h2>";
    
    echo "<div style='background:#f5f5f5; padding:10px; margin-bottom:20px;'>";
    echo "<h3>Оригинальный URL (через meyertec.owen.ru):</h3>";
    checkImageAvailability($originalUrl);
    echo "</div>";
    
    // Заменяем поддомен для прямого доступа
    $directUrl = str_replace('https://meyertec.owen.ru/', 'https://owen.ru/', $originalUrl);
    
    echo "<div style='background:#f5f5f5; padding:10px;'>";
    echo "<h3>Прямой URL (через owen.ru):</h3>";
    checkImageAvailability($directUrl);
    echo "</div>";
    
    echo "</div>";
}

// Проверяем изображения с разными URL
$testUrls = [
    'https://meyertec.owen.ru/uploads/195/mt22-s36_5.png',
    'https://meyertec.owen.ru/uploads/194/mt22-s25_5.png',
    'https://meyertec.owen.ru/uploads/195/mt22-s17_5.png',
];

echo "<h1>Тест доступности изображений Meyertec</h1>";

foreach ($testUrls as $url) {
    compareUrls($url);
}

// Для тестирования конкретного URL
if (isset($_GET['url']) && !empty($_GET['url'])) {
    echo "<h2>Тестирование указанного URL</h2>";
    $testUrl = $_GET['url'];
    compareUrls($testUrl);
}

echo "<div style='margin-top:20px;'>";
echo "<h2>Протестировать свой URL</h2>";
echo "<form method='get'>";
echo "<input type='text' name='url' size='80' placeholder='Введите URL изображения для проверки' />";
echo "<input type='submit' value='Проверить' />";
echo "</form>";
echo "</div>";
?>
