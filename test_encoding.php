<?php
// Проверка mb_string функций
echo "<h1>Проверка функций mb_string</h1>";
echo "PHP версия: " . phpversion() . "<br>";

if (function_exists('mb_detect_encoding')) {
    echo "Функция mb_detect_encoding доступна<br>";
    $test = "тестовая строка";
    $encoding = mb_detect_encoding($test, ['UTF-8', 'CP1251'], true);
    echo "Обнаруженная кодировка: " . $encoding;
} else {
    echo "Функция mb_detect_encoding НЕ доступна. Расширение mb_string не установлено или отключено.";
}
?>
