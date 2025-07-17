<?php
// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Тестируем URL изображения из XML
$imageUrl = "https://meyertec.owen.ru/uploads/203/mtb2-bz11_5.png";

echo "<h3>Тестирование обработки редиректа для URL изображения Meyertec</h3>";
echo "<p>Исходный URL: $imageUrl</p>";

// Используем CURL с расширенными опциями для отслеживания редиректа
if (function_exists('curl_init')) {
    $ch = curl_init($imageUrl);
    
    curl_setopt($ch, CURLOPT_NOBODY, 0); // Получаем контент
    curl_setopt($ch, CURLOPT_HEADER, 1); // Получаем заголовки
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Возвращаем результат как строку
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Следуем за редиректами
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10); // Максимум 10 редиректов
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, ""); // Включаем поддержку cookies
    curl_setopt($ch, CURLOPT_COOKIEJAR, ""); // Сохраняем cookies
    
    // Добавляем браузерные заголовки
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer: https://meyertec.owen.ru/'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // Получаем итоговый URL после всех редиректов
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    
    echo "<p><strong>HTTP код:</strong> $httpCode</p>";
    echo "<p><strong>Итоговый URL после редиректа:</strong> $finalUrl</p>";
    echo "<p><strong>Тип контента:</strong> $contentType</p>";
    echo "<p><strong>Заголовки ответа:</strong></p><pre>" . htmlspecialchars($header) . "</pre>";
    
    // Проверяем, это изображение или нет
    if ($httpCode == 200 && strpos($contentType, 'image/') !== false) {
        echo "<p style='color:green'><strong>✅ URL возвращает изображение</strong></p>";
        echo "<p>Ссылка на изображение: <a href='$finalUrl' target='_blank'>$finalUrl</a></p>";
        echo "<img src='$finalUrl' alt='Тест изображения' style='max-width:300px;'>";
    } else {
        echo "<p style='color:red'><strong>❌ URL не возвращает корректное изображение</strong></p>";
    }
    
    curl_close($ch);
} else {
    echo "<p>CURL не установлен на сервере.</p>";
}

// Проверяем также через file_get_contents
echo "<h3>Тест через file_get_contents (без редиректа)</h3>";
try {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                       "Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8\r\n" .
                       "Referer: https://meyertec.owen.ru/\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $fileContent = @file_get_contents($imageUrl, false, $context);
    
    if ($fileContent !== false) {
        $contentLength = strlen($fileContent);
        echo "<p style='color:green'><strong>✅ Получено содержимое размером: $contentLength байт</strong></p>";
    } else {
        echo "<p style='color:red'><strong>❌ Не удалось загрузить содержимое</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Ошибка: " . $e->getMessage() . "</p>";
}
