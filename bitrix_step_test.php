<?php
/**
 * Пошаговый тест подключения к Битриксу
 * Показывает результаты каждого этапа
 */

// Отключаем выдачу ошибок в браузер, вместо этого логируем их
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Определяем корневую директорию
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: realpath(dirname(__FILE__));

// Запускаем буферизацию вывода
ob_start();

// Функция безопасного вывода
function safeEcho($message, $status = 'info') {
    static $step = 1;
    echo "<div class='{$status}'>Шаг {$step}: {$message}</div>\n";
    $step++;
    ob_flush();
    flush();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Пошаговый тест Битрикса</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .info { color: #333; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Пошаговый тест подключения к Битриксу</h1>
    
    <?php
    try {
        safeEcho("PHP версия: " . phpversion());
        
        // Шаг 1: Проверка наличия файлов Битрикса
        $prologPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
        if (file_exists($prologPath)) {
            safeEcho("Файл prolog_before.php найден", "success");
        } else {
            safeEcho("Файл prolog_before.php не найден ({$prologPath})", "error");
            throw new Exception("Битрикс не установлен или путь неверный");
        }
        
        // Шаг 2: Проверка другой точки входа в Битрикс
        $mainIncludePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include.php';
        if (file_exists($mainIncludePath)) {
            safeEcho("Файл main/include.php найден", "success");
        } else {
            safeEcho("Файл main/include.php не найден", "warning");
        }
        
        // Шаг 3: Попытка подключения ядра Битрикса
        safeEcho("Попытка подключения к Битриксу (include.php)...");
        
        // Безопасное подключение - сначала включим перехват ошибок
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        
        // Пробуем подключить альтернативную точку входа
        if (file_exists($mainIncludePath)) {
            try {
                require_once($mainIncludePath);
                safeEcho("Файл include.php успешно подключен", "success");
            } catch (Exception $e) {
                safeEcho("Ошибка подключения include.php: " . $e->getMessage(), "error");
            }
        }
        
        // Восстанавливаем стандартный обработчик ошибок
        restore_error_handler();
        
        // Шаг 4: Попытка подключения prolog_before
        safeEcho("Попытка подключения prolog_before.php...");
        try {
            require_once($prologPath);
            safeEcho("prolog_before.php успешно подключен!", "success");
        } catch (Exception $e) {
            safeEcho("Ошибка подключения prolog_before.php: " . $e->getMessage(), "error");
        } catch (Error $e) {
            safeEcho("PHP ошибка при подключении prolog_before.php: " . $e->getMessage(), "error");
        }
        
        // Шаг 5: Проверка модулей
        if (class_exists('CModule')) {
            safeEcho("Класс CModule существует", "success");
            
            try {
                if (CModule::IncludeModule('iblock')) {
                    safeEcho("Модуль iblock успешно подключен", "success");
                } else {
                    safeEcho("Не удалось подключить модуль iblock", "error");
                }
            } catch (Exception $e) {
                safeEcho("Ошибка при подключении модуля iblock: " . $e->getMessage(), "error");
            }
            
            try {
                if (CModule::IncludeModule('catalog')) {
                    safeEcho("Модуль catalog успешно подключен", "success");
                } else {
                    safeEcho("Не удалось подключить модуль catalog", "error");
                }
            } catch (Exception $e) {
                safeEcho("Ошибка при подключении модуля catalog: " . $e->getMessage(), "error");
            }
        } else {
            safeEcho("Класс CModule не найден - ядро Битрикса не подключено", "error");
        }
        
        // Шаг 6: Проверка инфоблоков
        if (class_exists('CIBlock')) {
            safeEcho("Класс CIBlock существует", "success");
            
            // Проверяем существование инфоблоков
            try {
                $iblockProducts = 16; // ID инфоблока "Товары КИПАСО"
                $iblockOffers = 17;   // ID инфоблока торговых предложений
                
                if (CIBlock::GetByID($iblockProducts)->Fetch()) {
                    safeEcho("Инфоблок с ID=16 найден", "success");
                } else {
                    safeEcho("Инфоблок с ID=16 не найден", "warning");
                }
                
                if (CIBlock::GetByID($iblockOffers)->Fetch()) {
                    safeEcho("Инфоблок с ID=17 найден", "success");
                } else {
                    safeEcho("Инфоблок с ID=17 не найден", "warning");
                }
            } catch (Exception $e) {
                safeEcho("Ошибка при проверке инфоблоков: " . $e->getMessage(), "error");
            }
        } else {
            safeEcho("Класс CIBlock не найден", "error");
        }
        
    } catch (Exception $e) {
        safeEcho("Критическая ошибка: " . $e->getMessage(), "error");
    }
    ?>
    
    <h2>Рекомендации по устранению ошибок:</h2>
    <ul>
        <li>Проверьте настройки проактивной защиты в админке Битрикса</li>
        <li>Временно отключите проактивную защиту</li>
        <li>Проверьте .htaccess в корне сайта (можно временно переименовать его в .htaccess.bak)</li>
        <li>Проверьте настройки PHP в php.ini и .htaccess</li>
        <li>Проверьте права доступа к файлам и папкам Битрикса</li>
        <li>Проверьте ID инфоблоков в скриптах импорта</li>
    </ul>
    
    <h3>Подробная информация о сервере:</h3>
    <pre>
    <?php print_r($_SERVER); ?>
    </pre>
</body>
</html>
<?php
ob_end_flush();
?>
