<?php
// Включение отображения всех ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Функция для вывода сообщений
function debug($message) {
    echo $message . '<br>';
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Начинаем тестирование
echo '<h1>Диагностический тест для owen_import.php</h1>';
debug('Этап 1: Базовые проверки...');

// Проверка версии PHP
debug('Версия PHP: ' . phpversion());

// Проверка настроек сервера
debug('Лимит времени выполнения: ' . ini_get('max_execution_time'));
debug('Лимит памяти: ' . ini_get('memory_limit'));

// Проверка доступа к файловой системе
debug('Этап 2: Проверка доступа к файлам...');

// Проверяем наличие файла owenAPI.json
$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/owenAPI.json';
if (file_exists($jsonFilePath)) {
    debug('Файл owenAPI.json существует.');
    
    // Проверка прав доступа к файлу
    if (is_readable($jsonFilePath)) {
        debug('Файл owenAPI.json доступен для чтения.');
    } else {
        debug('ОШИБКА: Файл owenAPI.json существует, но не доступен для чтения!');
    }
    
} else {
    debug('ОШИБКА: Файл owenAPI.json не найден!');
}

// Проверка директории upload
$uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/';
if (is_dir($uploadPath)) {
    debug('Директория /upload/ существует.');
    
    if (is_writable($uploadPath)) {
        debug('Директория /upload/ доступна для записи.');
        
        // Пробуем создать тестовый файл
        $testFile = $uploadPath . 'test_' . time() . '.txt';
        if (@file_put_contents($testFile, 'Test write') !== false) {
            debug('Успешно создан тестовый файл: ' . $testFile);
            @unlink($testFile); // Удаляем тестовый файл
        } else {
            debug('ОШИБКА: Не удалось создать тестовый файл в директории /upload/!');
        }
    } else {
        debug('ОШИБКА: Директория /upload/ существует, но не доступна для записи!');
    }
} else {
    debug('ОШИБКА: Директория /upload/ не существует!');
}

// Проверка подключения Битрикса и модулей
debug('Этап 3: Проверка Битрикса...');

try {
    debug('Попытка подключения ядра Битрикса...');
    // Используем @ для подавления ошибок, которые могут помешать увидеть наш вывод
    @require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
    debug('Ядро Битрикса подключено.');
    
    // Проверка модулей
    if (class_exists('CModule')) {
        debug('Класс CModule существует.');
        
        if (@CModule::IncludeModule('iblock')) {
            debug('Модуль iblock успешно подключен.');
        } else {
            debug('ОШИБКА: Не удалось подключить модуль iblock!');
        }
        
        if (@CModule::IncludeModule('catalog')) {
            debug('Модуль catalog успешно подключен.');
        } else {
            debug('ОШИБКА: Не удалось подключить модуль catalog!');
        }
    } else {
        debug('ОШИБКА: Класс CModule не найден. Возможно, проблема с подключением Битрикса.');
    }
} catch (Exception $e) {
    debug('ОШИБКА при подключении Битрикса: ' . $e->getMessage());
}

// Проверка инфоблоков
debug('Этап 4: Проверка инфоблоков...');

if (class_exists('CIBlock') && class_exists('CIBlockElement')) {
    debug('Классы CIBlock и CIBlockElement существуют.');
    
    // Проверка инфоблока "Товары КИПАСО" (ID 16)
    try {
        if (class_exists('CIBlock')) {
            $res = @CIBlock::GetByID(16);
            $ar_res = @$res->Fetch();
            if ($ar_res) {
                debug('Инфоблок с ID=16 найден: ' . $ar_res['NAME']);
            } else {
                debug('ОШИБКА: Инфоблок с ID=16 не найден!');
            }
        }
    } catch (Exception $e) {
        debug('ОШИБКА при проверке инфоблока: ' . $e->getMessage());
    }
    
    // Проверка инфоблока торговых предложений (ID 17)
    try {
        if (class_exists('CIBlock')) {
            $res = @CIBlock::GetByID(17);
            $ar_res = @$res->Fetch();
            if ($ar_res) {
                debug('Инфоблок с ID=17 найден: ' . $ar_res['NAME']);
            } else {
                debug('ОШИБКА: Инфоблок с ID=17 не найден!');
            }
        }
    } catch (Exception $e) {
        debug('ОШИБКА при проверке инфоблока: ' . $e->getMessage());
    }
} else {
    debug('ОШИБКА: Классы CIBlock и/или CIBlockElement не найдены.');
}

// Проверка JSON-файла
debug('Этап 5: Тест чтения JSON-файла...');

if (file_exists($jsonFilePath) && is_readable($jsonFilePath)) {
    try {
        $jsonContent = @file_get_contents($jsonFilePath);
        if ($jsonContent !== false) {
            debug('Файл успешно прочитан, размер: ' . strlen($jsonContent) . ' байт.');
            
            // Проверяем первые 100 символов
            debug('Первые 100 символов файла: ' . substr(bin2hex($jsonContent), 0, 100));
            
            // Пробуем декодировать JSON
            debug('Попытка декодирования JSON...');
            $jsonData = @json_decode($jsonContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                debug('JSON успешно декодирован.');
                debug('Структура данных: ' . print_r(array_keys($jsonData), true));
            } else {
                debug('ОШИБКА декодирования JSON: ' . json_last_error_msg());
            }
        } else {
            debug('ОШИБКА: Не удалось прочитать содержимое файла JSON.');
        }
    } catch (Exception $e) {
        debug('ОШИБКА при работе с JSON: ' . $e->getMessage());
    }
}

debug('<strong>Диагностика завершена.</strong>');
?>
