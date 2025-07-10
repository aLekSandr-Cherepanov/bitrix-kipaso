<?php
// Проверка функций для работы с файлами
echo "<h1>Проверка функций для работы с файлами</h1>";
echo "PHP версия: " . phpversion() . "<br>";

echo "<h2>Директории и пути:</h2>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Текущий скрипт: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Текущая директория: " . getcwd() . "<br>";

echo "<h2>Тест функции file_exists:</h2>";
$testPath = $_SERVER['DOCUMENT_ROOT'] . '/test_file_functions.php';
if (file_exists($testPath)) {
    echo "Файл $testPath существует<br>";
} else {
    echo "Файл $testPath НЕ существует<br>";
}

echo "<h2>Тест функции file_get_contents:</h2>";
$content = file_get_contents(__FILE__);
if ($content !== false) {
    echo "Успешно прочитан файл (" . strlen($content) . " байт)<br>";
    echo "Первые 100 символов: " . htmlspecialchars(substr($content, 0, 100)) . "...<br>";
} else {
    echo "Не удалось прочитать файл<br>";
}
?>
