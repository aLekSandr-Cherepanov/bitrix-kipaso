<?php
// Включаем отображение всех ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Тест подключения к Битриксу</h1>";
echo "<p>PHP версия: " . phpversion() . "</p>";
echo "<p>Текущее время: " . date('Y-m-d H:i:s') . "</p>";

// Проверяем наличие файла prolog_before.php
$prologPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
echo "<p>Путь к prolog_before.php: " . $prologPath . "</p>";

if (file_exists($prologPath)) {
    echo "<p style='color:green'>Файл prolog_before.php существует</p>";
    
    // Безопасное подключение к Битриксу с перехватом ошибок
    echo "<h2>Попытка подключения к Битриксу:</h2>";
    
    // Сохраняем текущий буфер вывода
    ob_start();
    try {
        // Пытаемся подключить ядро Битрикса
        echo "<p>Подключаем prolog_before.php...</p>";
        require_once($prologPath);
        echo "<p style='color:green'>Битрикс успешно подключен!</p>";
        
        // Проверяем основные классы Битрикса
        if (class_exists('CMain')) {
            echo "<p>Класс CMain существует</p>";
        }
        
        if (class_exists('CModule')) {
            echo "<p>Класс CModule существует</p>";
            
            // Проверка подключения модулей
            if (CModule::IncludeModule('iblock')) {
                echo "<p style='color:green'>Модуль iblock успешно подключен</p>";
            } else {
                echo "<p style='color:red'>Не удалось подключить модуль iblock</p>";
            }
            
            if (CModule::IncludeModule('catalog')) {
                echo "<p style='color:green'>Модуль catalog успешно подключен</p>";
            } else {
                echo "<p style='color:red'>Не удалось подключить модуль catalog</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Исключение при подключении Битрикса: " . $e->getMessage() . "</p>";
    } catch (Error $e) {
        echo "<p style='color:red'>Ошибка PHP при подключении Битрикса: " . $e->getMessage() . "</p>";
    }
    
    // Получаем содержимое буфера вывода
    $output = ob_get_clean();
    echo $output;
    
} else {
    echo "<p style='color:red'>Файл prolog_before.php не найден! Проверьте путь к файлу.</p>";
    echo "<p>Возможные пути:</p>";
    echo "<ul>";
    
    // Проверяем несколько возможных путей
    $possiblePaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/bitrix/modules/main/include/prolog_before.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../bitrix/modules/main/include/prolog_before.php',
    ];
    
    foreach($possiblePaths as $path) {
        if (file_exists($path)) {
            echo "<li style='color:green'>$path (существует)</li>";
        } else {
            echo "<li style='color:red'>$path (не найден)</li>";
        }
    }
    
    echo "</ul>";
}

echo "<hr>";
echo "<h2>Рекомендации для решения проблемы с Битриксом:</h2>";
echo "<ol>";
echo "<li>Проверьте настройки проактивной защиты в админке Битрикса</li>";
echo "<li>Временно отключите проактивную защиту для тестовых скриптов</li>";
echo "<li>Если это тестовая среда, можно попробовать отключить проверку подписи скриптов в настройках безопасности</li>";
echo "<li>Проверьте .htaccess файл на наличие ограничений для внешних скриптов</li>";
echo "<li>Проверьте файл .settings.php на наличие ограничений</li>";
echo "</ol>";
?>
