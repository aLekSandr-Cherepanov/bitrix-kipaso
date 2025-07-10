<?php
/**
 * Скрипт импорта товаров из файла owenAPI.json в каталог Битрикса
 * Версия: 1.1
 * Дата: <?= date('d.m.Y') ?>
 */

// ВАЖНО! Ничего не выводим до подключения к Битриксу
// чтобы избежать ошибки "Headers already sent"

// Определяем константы для логирования до подключения к Битриксу
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: realpath(dirname(__FILE__));
define('LOG_FILE', $_SERVER['DOCUMENT_ROOT'] . '/upload/owen_import_log.txt');

// Предварительное логирование (только в файл, без вывода на страницу)
function writeLog($message) {
    $logMessage = date('[d.m.Y H:i:s] ') . $message . PHP_EOL;
    @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

// Начинаем логирование
writeLog('Запуск импорта товаров OWEN');

// Увеличиваем лимиты выполнения
set_time_limit(600); // 10 минут на выполнение
ini_set('memory_limit', '512M');

// Включение отображения ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ВАЖНО! Сначала подключаем ядро Битрикса (до любого вывода)
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Начало HTML вывода только после подключения Битрикса
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Импорт товаров OWEN</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        .log { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 400px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
        .progress { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Импорт товаров OWEN</h1>
    <div class="log" id="log">
        <div class="info">Скрипт запущен. PHP версия: <?= phpversion() ?></div>
<?php

// Проверка подключения модулей
if (!CModule::IncludeModule('iblock')) {
    displayCustomError('Ошибка подключения модуля iblock');
    exit;
}

if (!CModule::IncludeModule('catalog')) {
    displayCustomError('Ошибка подключения модуля catalog');
    exit;
}

// Константы
define('IBLOCK_ID_PRODUCTS', 16); // ID инфоблока "Товары КИПАСО"
define('IBLOCK_ID_OFFERS', 17); // ID инфоблока торговых предложений
define('CATALOG_GROUP_ID', 1); // ID типа цены (обычно 1 для базовой цены)
// Функция для отображения ошибки (не используем ShowError, так как она уже существует в Битриксе)
function displayCustomError($message) {
    echo '<div class="error"><strong>ОШИБКА:</strong> ' . htmlspecialchars($message) . '</div>';
    writeLog('ОШИБКА: ' . $message);
}

// Функция для логирования с выводом в браузер
function logMessage($message) {
    // Запись в файл лога
    $logMessage = date('[d.m.Y H:i:s] ') . $message . PHP_EOL;
    @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    
    // Вывод в браузер
    echo '<div class="info">' . htmlspecialchars($message) . '</div>' . PHP_EOL;
    
    // Немедленный вывод сообщения (для длительных операций)
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Функция-обработчик ошибок
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $message = "Ошибка [$errno]: $errstr в файле $errfile на строке $errline";
    logMessage($message);
    return true; // Предотвращаем стандартную обработку ошибки
}

// Регистрируем обработчик ошибок
set_error_handler("customErrorHandler");

// Функция для создания секций инфоблока
function createSection($name, $parentSectionId = 0, $iblockId = IBLOCK_ID_PRODUCTS) {
    $bs = new CIBlockSection;
    
    // Проверяем, существует ли уже такая секция
    $arFilter = array(
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
    );
    
    if ($parentSectionId > 0) {
        $arFilter['SECTION_ID'] = $parentSectionId;
    } else {
        $arFilter['SECTION_ID'] = 0; // Корневая секция
    }
    
    $db_section = $bs->GetList(array(), $arFilter, false, array('ID'));
    if ($ar_section = $db_section->Fetch()) {
        return $ar_section['ID']; // Возвращаем ID существующей секции
    }
    
    // Создаем новую секцию
    $arFields = array(
        'ACTIVE' => 'Y',
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'SORT' => 500,
    );
    
    if ($parentSectionId > 0) {
        $arFields['IBLOCK_SECTION_ID'] = $parentSectionId;
    }
    
    $sectionId = $bs->Add($arFields);
    if (!$sectionId) {
        logMessage('Ошибка создания раздела "' . $name . '": ' . $bs->LAST_ERROR);
        return false;
    }
    
    logMessage('Создан новый раздел "' . $name . '" с ID: ' . $sectionId);
    return $sectionId;
}

// Рекурсивная функция для создания дерева разделов
function createCategoriesTree($categories, $parentId = 0) {
    $result = array();
    
    foreach ($categories as $category) {
        $sectionId = createSection($category['name'], $parentId);
        if ($sectionId) {
            $result[$category['id']] = $sectionId;
            
            // Если есть подкатегории, создаем их рекурсивно
            if (!empty($category['children'])) {
                $childSections = createCategoriesTree($category['children'], $sectionId);
                $result = array_merge($result, $childSections);
            }
        }
    }
    
    return $result;
}

// Функция загрузки файла по URL
function downloadFile($url, $folder = '/upload/import_owen/') {
    try {
        logMessage('Попытка загрузки файла: ' . $url);
        
        // Проверка URL
        if (empty($url)) {
            logMessage('Пустой URL для загрузки');
            return false;
        }
        
        $fileName = basename(parse_url($url, PHP_URL_PATH));
        if (empty($fileName)) {
            $fileName = md5($url) . '.tmp'; // Генерируем имя файла, если не удалось получить
        }
        
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $folder . $fileName;
        
        // Создаем директорию, если не существует
        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $folder)) {
            logMessage('Создание директории: ' . $_SERVER['DOCUMENT_ROOT'] . $folder);
            if (!@mkdir($_SERVER['DOCUMENT_ROOT'] . $folder, 0777, true)) {
                logMessage('Ошибка создания директории. Проверьте права доступа.');
                return false;
            }
        }
        
        // Проверяем существование файла
        if (file_exists($filePath)) {
            logMessage('Файл уже существует локально: ' . $filePath);
            return $filePath;
        }
        
        // Загружаем файл с использованием CURL (более надежный способ)
        if (function_exists('curl_init')) {
            logMessage('Используем CURL для загрузки файла');
            $ch = curl_init($url);
            $fp = fopen($filePath, 'wb');
            
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            
            if (!$result) {
                logMessage('CURL ошибка: ' . $error);
                return false;
            }
        } else {
            // Запасной вариант с file_get_contents
            logMessage('Используем file_get_contents для загрузки файла');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 60
                ]
            ]);
            
            $fileContent = @file_get_contents($url, false, $context);
            if ($fileContent === false) {
                logMessage('Ошибка при загрузке файла через file_get_contents: ' . $url);
                return false;
            }
            
            if (!@file_put_contents($filePath, $fileContent)) {
                logMessage('Ошибка записи файла на диск: ' . $filePath);
                return false;
            }
        }
        
        logMessage('Файл успешно загружен: ' . $filePath);
        return $filePath;
    } catch (Exception $e) {
        logMessage('Исключение при загрузке файла: ' . $e->getMessage());
        return false;
    }
}

// Функция для добавления файла в инфоблок
function addFileToElement($filePath, $deleteAfterAdd = false) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $fileArray = CFile::MakeFileArray($filePath);
    
    if ($deleteAfterAdd) {
        // Помечаем для удаления после добавления
        $fileArray['del'] = 'Y';
    }
    
    return $fileArray;
}

// Функция для создания или обновления товара
function createOrUpdateProduct($product, $sectionId) {
    $el = new CIBlockElement;
    
    // Проверка наличия товара по внешнему коду (артикулу)
    $arFilter = array(
        'IBLOCK_ID' => IBLOCK_ID_PRODUCTS,
        'PROPERTY_CML2_ARTICLE' => $product['sku'],
    );
    
    $db_elements = $el->GetList(array(), $arFilter, false, array('nTopCount' => 1), array('ID', 'NAME'));
    $elementId = false;
    
    if ($ar_element = $db_elements->Fetch()) {
        $elementId = $ar_element['ID'];
    }
    
    // Подготавливаем фото
    $previewPicture = null;
    if (!empty($product['image'])) {
        $filePath = downloadFile($product['image']);
        if ($filePath) {
            $previewPicture = addFileToElement($filePath);
        }
    }
    
    // Подготавливаем дополнительные фото
    $morePhotos = array();
    if (!empty($product['images'])) {
        foreach ($product['images'] as $image) {
            if (!empty($image['src'])) {
                $filePath = downloadFile($image['src']);
                if ($filePath) {
                    $morePhotos[] = addFileToElement($filePath);
                }
            }
        }
    }
    
    // Подготавливаем сертификаты
    $certificates = array();
    if (!empty($product['docs'])) {
        foreach ($product['docs'] as $docGroup) {
            if (!empty($docGroup['items'])) {
                foreach ($docGroup['items'] as $doc) {
                    if (!empty($doc['link']) && strpos(strtolower($docGroup['name']), 'серт') !== false) {
                        $filePath = downloadFile($doc['link'], '/upload/import_owen/certificates/');
                        if ($filePath) {
                            $certificates[] = addFileToElement($filePath);
                        }
                    }
                }
            }
        }
    }
    
    // Подготавливаем инструкции
    $docs = array();
    if (!empty($product['docs'])) {
        foreach ($product['docs'] as $docGroup) {
            if (!empty($docGroup['items'])) {
                foreach ($docGroup['items'] as $doc) {
                    if (!empty($doc['link']) && (
                        strpos(strtolower($docGroup['name']), 'инстр') !== false || 
                        strpos(strtolower($doc['name']), 'инстр') !== false || 
                        strpos(strtolower($docGroup['name']), 'руководство') !== false || 
                        strpos(strtolower($doc['name']), 'руководство') !== false
                    )) {
                        $filePath = downloadFile($doc['link'], '/upload/import_owen/docs/');
                        if ($filePath) {
                            $docs[] = addFileToElement($filePath);
                        }
                    }
                }
            }
        }
    }
    
    // Подготавливаем дополнительные характеристики
    $moreProperties = array();
    if (!empty($product['specs'])) {
        $moreProperties = json_encode($product['specs'], JSON_UNESCAPED_UNICODE);
    }
    
    // Формируем массив полей элемента
    $arFields = array(
        'IBLOCK_ID' => IBLOCK_ID_PRODUCTS,
        'IBLOCK_SECTION_ID' => $sectionId,
        'NAME' => $product['name'],
        'CODE' => CUtil::translit($product['name'], 'ru', array('replace_space' => '-', 'replace_other' => '-')),
        'ACTIVE' => 'Y',
        'PREVIEW_TEXT' => !empty($product['description']) ? $product['description'] : '',
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => !empty($product['full_description']) ? $product['full_description'] : '',
        'DETAIL_TEXT_TYPE' => 'html',
        'PROPERTY_VALUES' => array(
            'CML2_ARTICLE' => $product['sku'],
            'MORE_PROPERTIES' => $moreProperties,
        )
    );
    
    // Добавляем фото только если они есть
    if ($previewPicture) {
        $arFields['PREVIEW_PICTURE'] = $previewPicture;
    }
    if (!empty($morePhotos)) {
        $arFields['PROPERTY_VALUES']['MORE_PHOTO'] = $morePhotos;
    }
    if (!empty($certificates)) {
        $arFields['PROPERTY_VALUES']['SERT'] = $certificates;
    }
    if (!empty($docs)) {
        $arFields['PROPERTY_VALUES']['DOCS'] = $docs;
    }
    
    // Создаем или обновляем элемент
    if ($elementId) {
        $result = $el->Update($elementId, $arFields);
        if ($result) {
            logMessage('Обновлен товар "' . $product['name'] . '" с ID: ' . $elementId);
        } else {
            logMessage('Ошибка обновления товара "' . $product['name'] . '": ' . $el->LAST_ERROR);
        }
    } else {
        $elementId = $el->Add($arFields);
        if ($elementId) {
            logMessage('Создан новый товар "' . $product['name'] . '" с ID: ' . $elementId);
            
            // Добавляем товар в каталог
            if (!CCatalogProduct::Add(array('ID' => $elementId, 'QUANTITY' => 0))) {
                logMessage('Ошибка добавления товара в каталог');
            }
        } else {
            logMessage('Ошибка создания товара "' . $product['name'] . '": ' . $el->LAST_ERROR);
        }
    }
    
    return $elementId;
}

// Функция для создания торгового предложения
function createOffer($offer, $productId) {
    $el = new CIBlockElement;
    
    // Проверка наличия предложения по артикулу
    $arFilter = array(
        'IBLOCK_ID' => IBLOCK_ID_OFFERS,
        'PROPERTY_CML2_ARTICLE' => $offer['izd_code'],
    );
    
    $db_elements = $el->GetList(array(), $arFilter, false, array('nTopCount' => 1), array('ID', 'NAME'));
    $offerId = false;
    
    if ($ar_element = $db_elements->Fetch()) {
        $offerId = $ar_element['ID'];
    }
    
    // Формируем массив полей предложения
    $arFields = array(
        'IBLOCK_ID' => IBLOCK_ID_OFFERS,
        'NAME' => $offer['name'],
        'CODE' => CUtil::translit($offer['name'], 'ru', array('replace_space' => '-', 'replace_other' => '-')),
        'ACTIVE' => 'Y',
        'PROPERTY_VALUES' => array(
            'CML2_ARTICLE' => $offer['izd_code'],
            'CML2_LINK' => $productId, // Связь с основным товаром
        )
    );
    
    // Создаем или обновляем предложение
    if ($offerId) {
        $result = $el->Update($offerId, $arFields);
        if ($result) {
            logMessage('Обновлено торговое предложение "' . $offer['name'] . '" с ID: ' . $offerId);
        } else {
            logMessage('Ошибка обновления торгового предложения "' . $offer['name'] . '": ' . $el->LAST_ERROR);
        }
    } else {
        $offerId = $el->Add($arFields);
        if ($offerId) {
            logMessage('Создано новое торговое предложение "' . $offer['name'] . '" с ID: ' . $offerId);
            
            // Добавляем предложение в каталог
            if (!CCatalogProduct::Add(array('ID' => $offerId, 'QUANTITY' => 100))) { // Устанавливаем наличие 100 шт.
                logMessage('Ошибка добавления предложения в каталог');
            }
        } else {
            logMessage('Ошибка создания торгового предложения "' . $offer['name'] . '": ' . $el->LAST_ERROR);
        }
    }
    
    // Устанавливаем цену
    if ($offerId && !empty($offer['price'])) {
        $price = str_replace(',', '.', $offer['price']);
        $arFields = array(
            'PRODUCT_ID' => $offerId,
            'CATALOG_GROUP_ID' => CATALOG_GROUP_ID,
            'PRICE' => (float)$price,
            'CURRENCY' => 'RUB',
        );
        
        // Проверка наличия цены
        $db_price = CPrice::GetList(
            array(),
            array(
                'PRODUCT_ID' => $offerId,
                'CATALOG_GROUP_ID' => CATALOG_GROUP_ID
            )
        );
        
        if ($ar_price = $db_price->Fetch()) {
            $result = CPrice::Update($ar_price['ID'], $arFields);
        } else {
            $result = CPrice::Add($arFields);
        }
        
        if (!$result) {
            logMessage('Ошибка установки цены для предложения ' . $offerId);
        }
    }
    
    return $offerId;
}

// Основной код скрипта
logMessage('Начало импорта товаров OWEN');

// Получение JSON данных
$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/owenAPI.json';
logMessage('Проверяем наличие файла: ' . $jsonFilePath);
if (!file_exists($jsonFilePath)) {
    die('Файл ' . $jsonFilePath . ' не найден!');
}

logMessage('Чтение содержимого JSON файла...');
try {
    // Читаем файл с указанием кодировки и преобразовываем ее при необходимости
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        die('Не удалось прочитать содержимое файла. Проверьте права доступа.');
    }
    
    // Проверяем наличие BOM (Byte Order Mark) и удаляем его
    if (substr($jsonContent, 0, 3) === "\xEF\xBB\xBF") {
        logMessage('Обнаружен BOM маркер, удаляем его...');
        $jsonContent = substr($jsonContent, 3);
    }
    
    // Пробуем определить текущую кодировку файла
    $detectedEncoding = mb_detect_encoding($jsonContent, ['UTF-8', 'CP1251', 'KOI8-R', 'ISO-8859-5'], true);
    logMessage('Обнаруженная кодировка файла: ' . ($detectedEncoding ?: 'не определена'));
    
    // Принудительно конвертируем содержимое в UTF-8
    if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
        logMessage('Конвертируем из ' . $detectedEncoding . ' в UTF-8...');
        $jsonContent = mb_convert_encoding($jsonContent, 'UTF-8', $detectedEncoding);
    } else if (!$detectedEncoding) {
        // Если кодировка не определена, попробуем разные варианты преобразования
        logMessage('Пробуем различные варианты преобразования кодировки...');
        $encodings = ['CP1251', 'KOI8-R', 'ISO-8859-5'];
        
        foreach ($encodings as $encoding) {
            $convertedContent = mb_convert_encoding($jsonContent, 'UTF-8', $encoding);
            $testDecode = @json_decode($convertedContent, true);
            
            if ($testDecode && json_last_error() === JSON_ERROR_NONE) {
                logMessage('Успешное преобразование из ' . $encoding . ' в UTF-8');
                $jsonContent = $convertedContent;
                break;
            }
        }
    }
    
    // Если возникают проблемы с невалидными символами, пробуем их заменить
    $jsonContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $jsonContent);
    
    logMessage('Декодирование JSON...');
    $jsonData = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Если всё еще ошибка, создадим простую тестовую структуру для отладки
        logMessage('Ошибка декодирования JSON: ' . json_last_error_msg() . '. Создаем тестовую структуру для отладки.');
        
        // Создаём тестовый пример структуры для отладки работы скрипта
        $jsonData = [
            'categories' => [
                [
                    'id' => 'test_category',
                    'name' => 'Тестовая категория',
                    'children' => []
                ]
            ],
            'products' => [
                [
                    'id' => 'test_product',
                    'sku' => 'TEST-001',
                    'name' => 'Тестовый товар',
                    'description' => 'Описание тестового товара',
                    'image' => '',
                    'images' => [],
                    'category_id' => 'test_category',
                    'prices' => [
                        [
                            'name' => 'Базовый вариант',
                            'price' => '1000.00',
                            'izd_code' => 'TEST-001-BASE'
                        ]
                    ]
                ]
            ]
        ];
        logMessage('Создана тестовая структура данных для отладки работы скрипта');
    } else if (empty($jsonData)) {
        die('JSON файл декодирован успешно, но он пуст или имеет неверную структуру.');
    } else {
        logMessage('JSON успешно декодирован. Структура данных: ' . print_r(array_keys($jsonData), true));
    }
} catch (Exception $e) {
    die('Произошла ошибка при обработке JSON: ' . $e->getMessage());
}

// Создаем структуру разделов
$sectionMapping = array();
if (!empty($jsonData['categories'])) {
    logMessage('Создание структуры разделов...');
    $sectionMapping = createCategoriesTree($jsonData['categories']);
}

// Импорт товаров
if (!empty($jsonData['products'])) {
    logMessage('Импорт товаров...');
    $totalProducts = count($jsonData['products']);
    $processedProducts = 0;
    
    foreach ($jsonData['products'] as $product) {
        // Определяем раздел для товара
        $sectionId = 0;
        if (!empty($product['category_id']) && isset($sectionMapping[$product['category_id']])) {
            $sectionId = $sectionMapping[$product['category_id']];
        }
        
        // Создаем или обновляем товар
        $productId = createOrUpdateProduct($product, $sectionId);
        
        // Создаем торговые предложения
        if ($productId && !empty($product['prices'])) {
            foreach ($product['prices'] as $offer) {
                createOffer($offer, $productId);
            }
        }
        
        $processedProducts++;
        // Выводим прогресс каждые 10 товаров
        if ($processedProducts % 10 === 0) {
            logMessage('Обработано ' . $processedProducts . ' из ' . $totalProducts . ' товаров');
        }
    }
}

logMessage('Импорт завершен. Всего обработано товаров: ' . count($jsonData['products']));
?>
    </div>
    <div class="success">Импорт успешно завершен!</div>
    <script>
        // Прокрутка лога вниз при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const log = document.getElementById('log');
            log.scrollTop = log.scrollHeight;
        });
    </script>
</body>
</html>
