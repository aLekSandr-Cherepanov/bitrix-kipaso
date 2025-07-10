<?php
// Отключаем ограничение по времени выполнения
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// Включаем вывод в консоль
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Определяем корневую директорию
$_SERVER["DOCUMENT_ROOT"] = __DIR__;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

// Включаем логирование
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'].'/upload/owen_import_log.txt');
ini_set('log_errors', 1);

// Подключаем Битрикс
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/iblock/prolog.php');

// Проверяем наличие модуля iblock
if(!CModule::IncludeModule('iblock')) {
    die("Ошибка: модуль iblock не подключен\n");
}

// Подключаем основной скрипт импорта
require_once($_SERVER["DOCUMENT_ROOT"].'/newPhpCatalog.php');

// Функция для логирования в консоль
function console_log($message) {
    echo date('Y-m-d H:i:s') . " | " . $message . "\n";
}

console_log("Начинаем импорт...");

try {
    // Запускаем импорт секций и товаров
    console_log("Импортируем разделы...");
    importSections($xml->categories->category);
    
    console_log("Импортируем товары...");
    importProducts();
    
    console_log("Импорт успешно завершен!");
} catch (Exception $e) {
    console_log("ОШИБКА: " . $e->getMessage());
}
