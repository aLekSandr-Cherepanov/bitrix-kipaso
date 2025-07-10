<?php
// Только проверка наличия файлов, без попыток подключения
// Это должно работать без ошибок 500

echo "<h1>Проверка файлов Битрикса</h1>";
echo "<p>PHP версия: " . phpversion() . "</p>";

// Функция для проверки файла
function checkFile($path) {
    echo "<div style='margin: 5px 0;'>";
    echo "<strong>$path</strong>: ";
    if (file_exists($path)) {
        echo "<span style='color:green;'>существует</span>";
        echo " (размер: " . filesize($path) . " байт, права: " . decoct(fileperms($path) & 0777) . ")";
    } else {
        echo "<span style='color:red;'>не найден</span>";
    }
    echo "</div>";
}

// Проверяем основные файлы
$bitrixRoot = $_SERVER['DOCUMENT_ROOT'] . '/bitrix';
$files = array(
    $bitrixRoot . '/modules/main/include/prolog_before.php',
    $bitrixRoot . '/modules/main/include.php',
    $bitrixRoot . '/modules/main/include/prolog_after.php',
    $bitrixRoot . '/modules/main/classes/general/main.php',
    $bitrixRoot . '/modules/iblock/include.php',
    $bitrixRoot . '/modules/catalog/include.php',
    $_SERVER['DOCUMENT_ROOT'] . '/.settings.php',
    $_SERVER['DOCUMENT_ROOT'] . '/.htaccess',
    $_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php'
);

echo "<h2>Проверка основных файлов:</h2>";
foreach ($files as $file) {
    checkFile($file);
}

// Проверяем структуру директорий
$dirs = array(
    $bitrixRoot,
    $bitrixRoot . '/modules',
    $bitrixRoot . '/modules/main',
    $bitrixRoot . '/modules/iblock',
    $bitrixRoot . '/modules/catalog',
    $bitrixRoot . '/components',
    $_SERVER['DOCUMENT_ROOT'] . '/upload'
);

echo "<h2>Проверка директорий:</h2>";
foreach ($dirs as $dir) {
    echo "<div style='margin: 5px 0;'>";
    echo "<strong>$dir</strong>: ";
    if (is_dir($dir)) {
        echo "<span style='color:green;'>существует</span>";
        echo " (права: " . decoct(fileperms($dir) & 0777) . ")";
    } else {
        echo "<span style='color:red;'>не найден</span>";
    }
    echo "</div>";
}

// Проверка наличия .settings.php или dbconn.php
echo "<h2>Проверка файлов конфигурации Битрикса:</h2>";

// Пытаемся найти .settings.php в разных местах
$settingsFiles = array(
    $_SERVER['DOCUMENT_ROOT'] . '/.settings.php',
    $bitrixRoot . '/.settings.php',
    $bitrixRoot . '/php_interface/.settings.php'
);

$settingsFound = false;
foreach ($settingsFiles as $file) {
    if (file_exists($file)) {
        echo "<div style='color:green;'>.settings.php найден в $file</div>";
        $settingsFound = true;
        break;
    }
}

if (!$settingsFound) {
    echo "<div style='color:red;'>.settings.php не найден в стандартных местах</div>";
}

// Проверка dbconn.php
$dbconnPath = $bitrixRoot . '/php_interface/dbconn.php';
if (file_exists($dbconnPath)) {
    echo "<div style='color:green;'>dbconn.php найден</div>";
} else {
    echo "<div style='color:red;'>dbconn.php не найден</div>";
}

// Проверка .htaccess
echo "<h2>Содержимое .htaccess (если есть):</h2>";
$htaccessPath = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
if (file_exists($htaccessPath)) {
    echo "<pre>";
    // Безопасно отображаем первые 50 строк .htaccess
    $lines = file($htaccessPath, FILE_IGNORE_NEW_LINES);
    $counter = 0;
    foreach ($lines as $line) {
        echo htmlspecialchars($line) . "\n";
        $counter++;
        if ($counter >= 50) {
            echo "... (файл слишком большой, показаны первые 50 строк)";
            break;
        }
    }
    echo "</pre>";
} else {
    echo "<p style='color:red;'>Файл .htaccess не найден</p>";
}
?>
