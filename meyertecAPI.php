<?php
/**
 * Скрипт импорта товаров из meyertec XML
 * 
 * Импортирует категории и товары с изображениями, характеристиками
 * и другими данными из XML-файла meyertec.
 */

// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Увеличиваем лимиты времени и памяти для больших XML
ini_set('memory_limit', '512M');
set_time_limit(1800); // 30 минут

// Подключаем ядро Bitrix
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Проверяем наличие необходимых модулей
try {
    if (!CModule::IncludeModule("iblock")) {
        throw new Exception("Ошибка подключения модуля iblock");
    }
    
    if (!CModule::IncludeModule("catalog")) {
        // Если модуль каталога не установлен, продолжаем без него
        logMessage("Модуль catalog не подключен, некоторые функции будут недоступны");
    }
    
    // Подключаем другие модули, если они есть
    if (CModule::IncludeModule("sale")) {
        logMessage("Модуль sale подключен");
    }
} catch (Exception $e) {
    die($e->getMessage());
}

// Настройки
$XML_URL = "https://meyertec.owen.ru/export/catalog.xml?host=owen.kipaso.ru&key=afOavhVgttik-rIgesgbk6Zkk-Y_by8W";

// Попытка определить ID инфоблока каталога товаров
$res = CIBlock::GetList(
    array(),
    array('TYPE' => 'catalog', 'SITE_ID' => SITE_ID),
    true
);
if ($ar_res = $res->Fetch()) {
    $IBLOCK_ID = $ar_res['ID'];
    $SECTION_IBLOCK_ID = $ar_res['ID'];
} else {
    // Если не найден, установим стандартные для торгового каталога
    $IBLOCK_ID = 2; // Типичный ID для торгового каталога
    $SECTION_IBLOCK_ID = 2;
}

// Путь к логам
try {
    // Попытка определить корневую директорию сайта
    if (!empty($_SERVER["DOCUMENT_ROOT"])) {
        $LOG_FILE = $_SERVER["DOCUMENT_ROOT"] . "/meyertec_import_log.txt";
    } else {
        // Резервный путь - текущая директория
        $LOG_FILE = dirname(__FILE__) . "/meyertec_import_log.txt";
    }
    
    // Проверяем возможность записи в директорию
    $logDir = dirname($LOG_FILE);
    if (!is_dir($logDir) || !is_writable($logDir)) {
        // Если директория недоступна для записи, пишем во временную директорию
        $LOG_FILE = sys_get_temp_dir() . "/meyertec_import_log.txt";
    }
} catch (Exception $e) {
    // В случае ошибки используем текущую директорию
    $LOG_FILE = dirname(__FILE__) . "/meyertec_import_log.txt";
}

// Логирование
function logMessage($message) {
    global $LOG_FILE;
    $logMessage = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    
    if (!empty($LOG_FILE)) {
        try {
            // Создаем директорию для логов, если не существует
            $logDir = dirname($LOG_FILE);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            if (is_writable($logDir)) {
                file_put_contents($LOG_FILE, $logMessage, FILE_APPEND);
            } else {
                // Если директория недоступна, запишем во временную директорию
                $tempFile = sys_get_temp_dir() . "/meyertec_import_log.txt";
                @file_put_contents($tempFile, $logMessage, FILE_APPEND);
            }
        } catch (Throwable $e) {
            // Перехватываем все возможные ошибки, включая Fatal error
            echo "Ошибка записи лога: " . $e->getMessage() . "<br>";
        }
    }
    
    echo $message . "<br>";
    
    // Flush output для лучшего отображения прогресса
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

// Функция проверки соединения с БД перед работой с файлами
function checkDBConnection() {
    global $DB;
    if (!is_object($DB)) {
        return false;
    }
    
    try {
        $result = $DB->Query('SELECT 1', true);
        if ($result === false) {
            // Если запрос не выполнен, пытаемся переподключиться
            if (method_exists($DB, 'DoConnect')) {
                $DB->DoConnect();
                logMessage("Соединение с базой данных восстановлено");
            } else {
                logMessage("Не удалось восстановить соединение с БД");
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        logMessage("Ошибка при проверке соединения с БД: " . $e->getMessage());
        return false;
    }
}

// Исправленная функция для загрузки изображений с явным скачиванием файла
function downloadAndSaveImage($imageUrl) {
    if (empty($imageUrl)) {
        logMessage("Пустой URL изображения");
        return false;
    }
    
    try {
        // Явно скачиваем файл вместо использования ссылки
        $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'jpg'; // По умолчанию jpg
        }
        
        // Создаем временный файл
        $tempFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/temp_' . md5(microtime() . $imageUrl) . '.' . $ext;
        
        // Скачиваем файл напрямую
        if (!function_exists('curl_init')) {
            $fileContent = file_get_contents($imageUrl);
            if ($fileContent === false) {
                logMessage("Не удалось загрузить изображение: {$imageUrl}");
                return false;
            }
            file_put_contents($tempFile, $fileContent);
        } else {
            // Используем CURL для более надежной загрузки
            $ch = curl_init($imageUrl);
            $fp = fopen($tempFile, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            
            if ($httpCode != 200) {
                unlink($tempFile);
                logMessage("Неудачный запрос, HTTP код: {$httpCode} для URL: {$imageUrl}");
                return false;
            }
        }
        
        // Проверяем, что файл существует
        if (!file_exists($tempFile) || filesize($tempFile) == 0) {
            logMessage("Не удалось сохранить изображение во временный файл: {$tempFile}");
            if (file_exists($tempFile)) unlink($tempFile);
            return false;
        }
        
        // Устанавливаем максимальные права доступа к файлу
        chmod($tempFile, 0666);
        
        // Создаем массив для CFile из локального файла
        $fileArray = CFile::MakeFileArray($tempFile);
        
        if (!$fileArray || !is_array($fileArray) || empty($fileArray)) {
            logMessage("Не удалось создать массив файла для {$tempFile}");
            if (file_exists($tempFile)) unlink($tempFile);
            return false;
        }
        
        // Устанавливаем необходимые параметры
        $fileArray["MODULE_ID"] = "iblock";
        
        // Устанавливаем имя файла
        $fileName = basename($imageUrl);
        if (empty($fileName) || strpos($fileName, '?') !== false) {
            $fileName = "image_{$ext}_" . time() . ".{$ext}";
        }
        $fileArray["name"] = $fileName;
        
        // Устанавливаем тип файла
        $fileArray["type"] = "image/" . (strtolower($ext) == 'jpg' ? 'jpeg' : strtolower($ext));
        
        // Добавляем публичные параметры
        $fileArray["PUBLIC"] = "Y";

        
        // Сохраняем файл в Bitrix и получаем ID
        $fileId = CFile::SaveFile($fileArray, "iblock");
        
        if ($fileId) {
            logMessage("Успешно сохранено изображение, ID: {$fileId}, Тип: {$fileArray['type']}");
            return $fileId;
        } else {
            logMessage("Ошибка при сохранении файла через CFile::SaveFile");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Ошибка при загрузке изображения: " . $e->getMessage());
        return false;
    }
}

// Функция для загрузки файла по URL
function downloadFile($url) {
    try {
        $tempName = tempnam(sys_get_temp_dir(), 'meyertec_');
        
        // Настройка клиента с увеличенным таймаутом
        $client = new \Bitrix\Main\Web\HttpClient(array(
            'socketTimeout' => 30,
            'streamTimeout' => 30,
            'disableSslVerification' => true
        ));
        
        if ($client->download($url, $tempName)) {
            $fileInfo = pathinfo($url);
            $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : 'jpg';
            
            $fileArray = CFile::MakeFileArray($tempName);
            if ($fileArray) {
                $fileArray['name'] = md5(microtime() . $url) . '.' . $extension;
                return $fileArray;
            } else {
                logMessage("Ошибка создания массива файла для: " . $url);
            }
        } else {
            logMessage("Ошибка загрузки файла: " . $url . ". Ошибка: " . $client->getError());
        }
    } catch (Exception $e) {
        logMessage("Исключение при загрузке файла: " . $e->getMessage());
    }
    
    return false;
}

// Функция для поиска или создания раздела
function findOrCreateSection($name, $parentId = 0, $xml_id = '') {
    global $SECTION_IBLOCK_ID;
    
    // Проверяем соединение с БД
    if (!checkDBConnection()) {
        logMessage("Ошибка соединения с БД при работе с разделом: " . $name);
        return false;
    }
    
    $arFilter = array(
        "IBLOCK_ID" => $SECTION_IBLOCK_ID,
        "NAME" => $name,
    );
    
    if ($parentId > 0) {
        $arFilter["SECTION_ID"] = $parentId;
    } else {
        $arFilter["SECTION_ID"] = 0;
    }
    
    $db_list = CIBlockSection::GetList(Array(), $arFilter, false, array("ID", "XML_ID"));
    if ($section = $db_list->Fetch()) {
        return $section["ID"];
    } else {
        $bs = new CIBlockSection;
        
        // Формируем символьный код
        $code = '';
        
        // Если xml_id содержит число, используем его
        if (is_numeric($xml_id)) {
            $code = 'category_' . $xml_id;
        } else {
            // Иначе транслитерируем название
            $code = CUtil::translit($name, "ru", array(
                "replace_space" => "-", 
                "replace_other" => "-"
            ));
            
            // Если и это не работает, создадим уникальный код
            if (empty($code)) {
                $code = 'category_' . md5($name . time());
            }
        }
        
        $arFields = Array(
            "ACTIVE" => "Y",
            "IBLOCK_ID" => $SECTION_IBLOCK_ID,
            "NAME" => $name,
            "CODE" => $code, // Добавляем символьный код
            "SORT" => 500,
            "XML_ID" => $xml_id,
        );
        
        if ($parentId > 0) {
            $arFields["IBLOCK_SECTION_ID"] = $parentId;
        }
        
        $sectionId = $bs->Add($arFields);
        if ($sectionId) {
            logMessage("Создан раздел: " . $name . " (ID: " . $sectionId . ", CODE: " . $code . ")");
            return $sectionId;
        } else {
            logMessage("Ошибка создания раздела " . $name . ": " . $bs->LAST_ERROR);
            return false;
        }
    }
}

// Функция для поиска или добавления товара
function findOrCreateProduct($xml_id, $data) {
    global $IBLOCK_ID;
    
    // Проверяем соединение с БД
    if (!checkDBConnection()) {
        logMessage("Ошибка соединения с БД при работе с товаром: " . $data["NAME"]);
        return false;
    }
    
    $arSelect = array("ID", "XML_ID");
    $arFilter = array(
        "IBLOCK_ID" => $IBLOCK_ID,
        "XML_ID" => $xml_id,
    );
    
    $res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    if ($item = $res->Fetch()) {
        // Товар найден, обновляем его
        return updateProduct($item["ID"], $data);
    } else {
        // Товар не найден, добавляем новый
        return addProduct($data);
    }
}

// Функция для добавления товара
function addProduct($data) {
    global $IBLOCK_ID;
    
    // Проверяем соединение с БД
    if (!checkDBConnection()) {
        logMessage("Ошибка соединения с БД при добавлении товара: " . $data["NAME"]);
        return false;
    }
    
    // Проверка привязки к разделам
    if (empty($data["IBLOCK_SECTION_ID"])) {
        // Находим хотя бы один раздел, если нет привязки
        $db_groups = CIBlockSection::GetList(
            Array("SORT" => "ASC"),
            Array("IBLOCK_ID" => $IBLOCK_ID, "ACTIVE" => "Y"),
            false,
            Array("ID")
        );
        if ($ar_group = $db_groups->Fetch()) {
            $data["IBLOCK_SECTION_ID"] = $ar_group["ID"];
            logMessage("Товар '{$data["NAME"]}' не имел привязки к разделам, используем первый найденный раздел");
        } else {
            // Если нет ни одного раздела, создаем новый
            $bs = new CIBlockSection;
            $arFields = Array(
                "ACTIVE" => "Y",
                "IBLOCK_ID" => $IBLOCK_ID,
                "NAME" => "Импортированные товары",
                "SORT" => 500
            );
            $sectionId = $bs->Add($arFields);
            if ($sectionId) {
                $data["IBLOCK_SECTION_ID"] = $sectionId;
                logMessage("Создан новый раздел 'Импортированные товары'");
            } else {
                logMessage("Ошибка создания раздела: " . $bs->LAST_ERROR);
                return false;
            }
        }
    }
    
    $el = new CIBlockElement;
    
    $arLoadProductArray = array(
        "IBLOCK_ID" => $IBLOCK_ID,
        "IBLOCK_SECTION_ID" => $data["IBLOCK_SECTION_ID"],
        "NAME" => $data["NAME"],
        "XML_ID" => $data["XML_ID"],
        "CODE" => \CUtil::translit($data["NAME"], "ru", array("replace_space" => "-", "replace_other" => "-")),
        "ACTIVE" => "Y",
        "DETAIL_TEXT" => $data["DETAIL_TEXT"],
        "DETAIL_TEXT_TYPE" => "html",
        "PROPERTY_VALUES" => $data["PROPERTY_VALUES"]
    );
    
    if (isset($data["PREVIEW_PICTURE"]) && $data["PREVIEW_PICTURE"]) {
        $arLoadProductArray["PREVIEW_PICTURE"] = $data["PREVIEW_PICTURE"];
    }
    
    if (isset($data["DETAIL_PICTURE"]) && $data["DETAIL_PICTURE"]) {
        $arLoadProductArray["DETAIL_PICTURE"] = $data["DETAIL_PICTURE"];
    }
    
    // Добавляем товар
    $productId = $el->Add($arLoadProductArray);
    
    if ($productId) {
        logMessage("Добавлен товар: " . $data["NAME"] . " (ID: " . $productId . ")");
        
        // Добавляем дополнительные свойства
        if (!empty($data["PROPERTY_VALUES"])) {
            CIBlockElement::SetPropertyValuesEx($productId, $IBLOCK_ID, $data["PROPERTY_VALUES"]);
            logMessage("Добавлены свойства для товара ID: " . $productId);
        }
        
        // Устанавливаем цены
        if (isset($data["PRICE"]) && $data["PRICE"] > 0) {
            if (CModule::IncludeModule("catalog")) { // Проверяем наличие модуля каталога
                $price = $data["PRICE"];
                
                $arFields = array(
                    "PRODUCT_ID" => $productId,
                    "CATALOG_GROUP_ID" => 1, // ID базовой цены
                    "PRICE" => $price,
                    "CURRENCY" => "RUB"
                );
                
                $res = CPrice::GetList(
                    array(),
                    array(
                        "PRODUCT_ID" => $productId,
                        "CATALOG_GROUP_ID" => 1
                    )
                );
                
                if ($arr = $res->Fetch()) {
                    CPrice::Update($arr["ID"], $arFields);
                } else {
                    CPrice::Add($arFields);
                }
            } else {
                logMessage("Цена не установлена: модуль catalog не подключен");
            }
        }
        
        return $productId;
    } else {
        logMessage("Ошибка при добавлении товара " . $data["NAME"] . ": " . $el->LAST_ERROR);
        return false;
    }
}

// Функция для обновления товара
function updateProduct($productId, $data) {
    global $IBLOCK_ID;
    
    // Проверяем соединение с БД
    if (!checkDBConnection()) {
        logMessage("Ошибка соединения с БД при обновлении товара: " . $data["NAME"]);
        return false;
    }
    
    $el = new CIBlockElement;
    
    $arLoadProductArray = array(
        "IBLOCK_SECTION_ID" => $data["IBLOCK_SECTION_ID"],
        "NAME" => $data["NAME"],
        "DETAIL_TEXT" => $data["DETAIL_TEXT"],
        "DETAIL_TEXT_TYPE" => "html"
    );
    
    if (isset($data["PREVIEW_PICTURE"]) && $data["PREVIEW_PICTURE"]) {
        $arLoadProductArray["PREVIEW_PICTURE"] = $data["PREVIEW_PICTURE"];
    }
    
    // Обновляем товар
    $res = $el->Update($productId, $arLoadProductArray);
    
    if ($res) {
        logMessage("Обновлен товар: " . $data["NAME"] . " (ID: " . $productId . ")");
        
        // Обновляем свойства
        if (isset($data["PROPERTY_VALUES"]) && !empty($data["PROPERTY_VALUES"])) {
            CIBlockElement::SetPropertyValuesEx($productId, $IBLOCK_ID, $data["PROPERTY_VALUES"]);
        }
        
        // Обновляем цены
        if (isset($data["PRICE"]) && $data["PRICE"] > 0) {
            $price = $data["PRICE"];
            
            $arFields = array(
                "PRODUCT_ID" => $productId,
                "CATALOG_GROUP_ID" => 1, // ID базовой цены
                "PRICE" => $price,
                "CURRENCY" => "RUB"
            );
            
            $res = CPrice::GetList(
                array(),
                array(
                    "PRODUCT_ID" => $productId,
                    "CATALOG_GROUP_ID" => 1
                )
            );
            
            if ($arr = $res->Fetch()) {
                CPrice::Update($arr["ID"], $arFields);
            } else {
                CPrice::Add($arFields);
            }
        }
        
        return $productId;
    } else {
        logMessage("Ошибка при обновлении товара " . $data["NAME"] . ": " . $el->LAST_ERROR);
        return false;
    }
}

// Очищаем лог
if (!empty($LOG_FILE)) {
    try {
        $logDir = dirname($LOG_FILE);
        if (is_dir($logDir) && is_writable($logDir)) {
            file_put_contents($LOG_FILE, '');
        }
    } catch (Throwable $e) {
        echo "Не удалось очистить лог-файл: " . $e->getMessage() . "<br>";
    }
}

// Начинаем импорт
logMessage("Начинаем импорт товаров из XML");

// Загружаем XML
logMessage("Загрузка XML из " . $XML_URL);

try {
    // Используем контекст для установки таймаута
    $context = stream_context_create(
        array(
            'http' => array(
                'timeout' => 300 // 5 минут таймаут
            )
        )
    );
    
    $xmlContent = file_get_contents($XML_URL, false, $context);
    
    if ($xmlContent === false) {
        throw new Exception("Не удалось получить содержимое по URL");
    }
    
    $xml = simplexml_load_string($xmlContent);
    
    if (!$xml) {
        throw new Exception("Не удалось распарсить XML");
    }
} catch (Exception $e) {
    logMessage("Ошибка загрузки XML: " . $e->getMessage());
    die("Ошибка загрузки XML: " . $e->getMessage());
}

$totalCategories = 0;
$totalProducts = 0;
$addedProducts = 0;
$updatedProducts = 0;
$errorProducts = 0;

// Массив соответствия ID категорий из XML и ID разделов в Bitrix
$categoryMap = array();

// Обрабатываем категории
if (isset($xml->categories->category)) {
    logMessage("Обработка категорий...");
    
    // Дебаг информация о структуре XML
    $xmlAttributes = array_keys(get_object_vars($xml));
    logMessage("Структура XML: " . implode(", ", $xmlAttributes));
    
    $categories = $xml->categories->category;
    $categoriesCount = count($categories);
    logMessage("Найдено категорий: " . $categoriesCount);
    
    $totalCategories = 0;
    
    foreach ($categories as $category) {
        // Выводим атрибуты для дебага
        $attrs = $category->attributes();
        $categoryId = (string) $attrs->id; // Используем атрибут id из XML
        
        // Добавляем вывод структуры категории для дебага
        if ($category->name) {
            $categoryName = trim((string) $category->name); // Название находится во вложенном элементе name
            $categoryImage = ($category->image) ? (string) $category->image : '';
            
            logMessage("Найдена категория: ID=$categoryId, Название=$categoryName");
            
            if (!empty($categoryId) && !empty($categoryName)) {
                logMessage("Обработка категории: ID=$categoryId, Название=$categoryName");
                // Создаем или находим секцию
                $sectionId = findOrCreateSection($categoryName, 0, "category_{$categoryId}");
                
                if ($sectionId) {
                    logMessage("Добавлена/найдена категория: ID=$categoryId, Название=$categoryName, Раздел ID=$sectionId");
                    // Сохраняем соответствие ID из XML и ID в Bitrix
                    $categoryMap[$categoryId] = $sectionId;
                    $totalCategories++;
                } else {
                    logMessage("Ошибка при создании категории: ID=$categoryId, Название=$categoryName");
                }
            } else {
                logMessage("Пропуск категории с пустым ID или названием: ID=$categoryId, Название=$categoryName");
            }
        } else {
            logMessage("Ошибка: Не найден элемент name для категории с ID=$categoryId");
        }
    }
    
    logMessage("Обработано категорий: " . $totalCategories);
} else {
    logMessage("Не найдены категории в XML");
}

// Обрабатываем товары
logMessage("Структура XML: проверяем товары");

// Проверяем доступные узлы в XML для товаров
if (isset($xml->products->product)) {
    logMessage("Найдено товаров: " . count($xml->products->product));
    
    // Проверяем структуру первого товара
    $firstItem = $xml->products->product[0];
    logMessage("Структура первого товара: " . implode(", ", array_keys(get_object_vars($firstItem))));
    
    foreach ($xml->products->product as $item) {
        // Основная информация о товаре
        $productId = (string)$item->id;
        $productName = (string)$item->name;
        $productFullName = isset($item->full_name) ? (string)$item->full_name : $productName;
        $price = (float)(isset($item->price) ? $item->price : 0);
        
        // Находим ID категории
        $categoryId = isset($item->category) ? (string)$item->category : '';
        
        // Описание и характеристики
        $text = isset($item->text) ? (string)$item->text : '';
        $text2 = isset($item->text2) ? (string)$item->text2 : '';
        $text3 = isset($item->text3) ? (string)$item->text3 : '';
        
        // Формируем описание из доступных полей
        $description = '';
        if (!empty($text)) $description .= $text;
        if (!empty($text2)) $description .= "<br><br>" . $text2;
        if (!empty($text3)) $description .= "<br><br>" . $text3;
        
        // Дополнительные характеристики
        $unit = isset($item->unit) ? (string)$item->unit : '';
        $multiplicity = isset($item->multiplicity) ? (string)$item->multiplicity : '';
        $link = isset($item->link) ? (string)$item->link : '';
        $packing = isset($item->packing) ? (string)$item->packing : '';
        
        // Проверяем наличие секции для товара
        $sectionId = 0;
        if (!empty($categoryId) && isset($categoryMap[$categoryId])) {
            $sectionId = $categoryMap[$categoryId];
            logMessage("Товар '{$productName}' привязан к категории {$categoryId} (раздел ID: {$sectionId})");
        }
        
        // Если раздел не найден, попробуем взять первый доступный
        if ($sectionId == 0 && isset($categoryMap) && is_array($categoryMap) && !empty($categoryMap)) {
            // Берем первый раздел из массива
            $sectionId = reset($categoryMap);
            logMessage("Товар '{$productName}' будет добавлен в первый доступный раздел");
        }
        
        // Подготавливаем данные для товара
        $productData = array(
            "NAME" => $productFullName ?: $productName,
            "XML_ID" => $productId,
            "IBLOCK_SECTION_ID" => $sectionId,
            "DETAIL_TEXT" => $description,
            "DETAIL_TEXT_TYPE" => "html",
            "PRICE" => $price,
            "PROPERTY_VALUES" => array()
        );
        
        // Добавляем свойства товара
        $productData["PROPERTY_VALUES"]["ARTICLE"] = $productId; // Артикул товара
        
        // Дополнительные свойства, если они есть в инфоблоке
        if (!empty($unit)) {
            $productData["PROPERTY_VALUES"]["UNIT"] = $unit; // Единица измерения
        }
        
        if (!empty($multiplicity)) {
            $productData["PROPERTY_VALUES"]["MULTIPLICITY"] = $multiplicity; // Кратность
        }
        
        if (!empty($link)) {
            $productData["PROPERTY_VALUES"]["EXTERNAL_LINK"] = $link; // Ссылка на товар
        }
        
        if (!empty($packing)) {
            $productData["PROPERTY_VALUES"]["PACKING"] = $packing; // Упаковка
        }
        
        // Обрабатываем статус наличия
        if (isset($item->storeStatus)) {
            $storeStatus = (string)$item->storeStatus;
            $storeValue = isset($item->storeValue) ? (string)$item->storeValue : '';
            
            // Получение статуса наличия для Bitrix
            $productData["PROPERTY_VALUES"]["STORE_STATUS"] = $storeStatus;
            if (!empty($storeValue)) {
                $productData["PROPERTY_VALUES"]["STORE_VALUE"] = $storeValue;
            }
        }
        
        // Обрабатываем изображения согласно полученному совету
        
        // Основное изображение
        if (isset($item->image)) {
            $attrs = $item->image->attributes();
            $imageUrl = (string)$attrs->src;
            
            // Заменяем поддомен meyertec.owen.ru на owen.ru для прямого доступа к изображению
            $directImageUrl = str_replace('https://meyertec.owen.ru/', 'https://owen.ru/', $imageUrl);
            
            echo "<pre>URL основного изображения: {$imageUrl}</pre>";
            echo "<pre>Прямой URL изображения: {$directImageUrl}</pre>";
            
            // Используем прямой URL для скачивания
            $imageUrl = $directImageUrl;
            logMessage("Найдено основное изображение: $imageUrl");
            
            if (!empty($imageUrl)) {
                // Явно скачиваем во временный файл для надежности
                $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
                if (empty($ext)) {
                    $ext = 'png'; // Основываясь на URL, по умолчанию png
                }
                
                $tmpFilePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/temp_' . md5(microtime() . rand(0, 1000) . $imageUrl) . '.' . $ext;
                
                // Сохраним полный URL для отладки
                $debugFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_url.txt';
                file_put_contents($debugFile, $imageUrl . "\n", FILE_APPEND);
                
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $imageUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    // Добавляем заголовки для имитации браузера
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                        'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Referer: https://meyertec.owen.ru/'
                    ]);
                    
                    $fileContent = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    curl_close($ch);
                    
                    // Сохраним заголовки для отладки первого изображения
                    static $debugDone = false;
                    if (!$debugDone) {
                        $debugFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_headers.txt';
                        file_put_contents($debugFile, "URL: {$imageUrl}\nHTTP Code: {$httpCode}\nContent-Type: {$contentType}\n");
                        $debugDone = true;
                    }
                    
                    if ($httpCode == 200 && $fileContent) {
                        file_put_contents($tmpFilePath, $fileContent);
                        
                        // Сохраним первый скачанный файл для анализа
                        static $debugFileSaved = false;
                        if (!$debugFileSaved) {
                            $debugFileContent = $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_image.txt';
                            file_put_contents($debugFileContent, substr($fileContent, 0, 200)); // Первые 200 байт
                            $debugFileSaved = true;
                        }
                    } else {
                        logMessage("Ошибка при загрузке файла: HTTP код {$httpCode}");
                    }
                } else {
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
                        file_put_contents($tmpFilePath, $fileContent);
                    }
                }
                
                // Проверяем, что файл существует и это действительно изображение
                if (file_exists($tmpFilePath) && filesize($tmpFilePath) > 0) {
                    // Проверка, что это действительно изображение
                    $imageInfo = @getimagesize($tmpFilePath);
                    if ($imageInfo === false) {
                        logMessage("Файл {$imageUrl} не является изображением. Проверьте содержимое в /upload/debug_image.txt");
                        @unlink($tmpFilePath); // Удаляем файл, если это не изображение
                        continue; // Пропускаем этот файл
                    }
                    
                    // Определяем MIME-тип на основе результатов getimagesize
                    $detectedMimeType = $imageInfo['mime']; // Например, 'image/jpeg'
                    chmod($tmpFilePath, 0666); // Устанавливаем права на файл
                    
                    // Создаем массив файла из локального файла
                    $fileArray = CFile::MakeFileArray($tmpFilePath);
                    
                    if ($fileArray) {
                        // Явно указываем тип файла и модуль, используя обнаруженный MIME-тип
                        $fileArray["MODULE_ID"] = "iblock";
                        $fileArray["name"] = basename($imageUrl);
                        $fileArray["type"] = $detectedMimeType; // Используем обнаруженный MIME-тип
                        
                        // Добавляем отладочную информацию
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                        logMessage("Изображение {$fileArray["name"]} обработано, размер: {$width}x{$height}, MIME-тип: {$detectedMimeType}");
                        
                        // Сохраняем файл в Bitrix и получаем ID
                        $fileId = CFile::SaveFile($fileArray, "iblock");
                        
                        if ($fileId > 0) {
                            // Передаем ID файла, а не массив
                            $productData["PREVIEW_PICTURE"] = $fileId;
                            $productData["DETAIL_PICTURE"] = $fileId;
                            logMessage("Изображение сохранено в Bitrix с ID: {$fileId}, добавлено в поля PREVIEW_PICTURE и DETAIL_PICTURE");
                            
                            // Сохраняем также оригинальный URL изображения в свойстве для справки
                            if (!isset($productData["PROPERTY_VALUES"])) {
                                $productData["PROPERTY_VALUES"] = array();
                            }
                            if (!isset($productData["PROPERTY_VALUES"]["IMAGE_LINKS"])) {
                                $productData["PROPERTY_VALUES"]["IMAGE_LINKS"] = array();
                            }
                            $productData["PROPERTY_VALUES"]["IMAGE_LINKS"][] = $directImageUrl;
                        } else {
                            logMessage("Ошибка при сохранении изображения в Bitrix");
                        }
                    } else {
                        logMessage("Ошибка при создании массива файла для основного изображения {$imageUrl}");
                    }
                    
                    // Удаляем временный файл
                    @unlink($tmpFilePath);
                } else {
                    logMessage("Ошибка при загрузке основного изображения {$imageUrl}");
                }
            }
        }
        
        // Дополнительные изображения - точно как в newPhpCatalog
        $morePhoto = [];
        if (isset($item->images)) {
            $additionalImages = $item->images->children();
            $additionalCount = count($additionalImages);
            logMessage("Найдено дополнительных изображений: $additionalCount");
            echo "<pre>Найдено дополнительных изображений: $additionalCount</pre>";
            
            foreach ($additionalImages as $addImage) {
                $attrs = $addImage->attributes();
                $imageUrl = (string)$attrs->src;
                
                if (!empty($imageUrl)) {
                    // Заменяем поддомен meyertec.owen.ru на owen.ru для прямого доступа к изображению
                    $directImageUrl = str_replace('https://meyertec.owen.ru/', 'https://owen.ru/', $imageUrl);
                    
                    echo "<pre>URL дополнительного изображения: {$imageUrl}</pre>";
                    echo "<pre>Прямой URL доп. изображения: {$directImageUrl}</pre>";
                    
                    // Используем прямой URL для скачивания
                    $imageUrl = $directImageUrl;
                    
                    // Явно скачиваем во временный файл для надежности
                    $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
                    if (empty($ext)) {
                        $ext = 'png'; // Основываясь на URL, по умолчанию png
                    }
                    
                    $tmpFilePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/temp_' . md5(microtime() . rand(0, 1000) . $imageUrl) . '.' . $ext;
                    
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $imageUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        // Добавляем заголовки для имитации браузера
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                            'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                            'Referer: https://meyertec.owen.ru/'
                        ]);
                        
                        $fileContent = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode == 200 && $fileContent) {
                            file_put_contents($tmpFilePath, $fileContent);
                        } else {
                            logMessage("Ошибка при загрузке доп. файла: HTTP код {$httpCode}");
                        }
                    } else {
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
                            file_put_contents($tmpFilePath, $fileContent);
                        }
                    }
                    
                    // Проверяем, что файл существует и это действительно изображение
                    if (file_exists($tmpFilePath) && filesize($tmpFilePath) > 0) {
                        // Проверка, что это действительно изображение
                        $imageInfo = @getimagesize($tmpFilePath);
                        if ($imageInfo === false) {
                            logMessage("Доп. файл {$imageUrl} не является изображением");
                            @unlink($tmpFilePath); // Удаляем файл, если это не изображение
                            continue; // Пропускаем этот файл
                        }
                        
                        // Определяем MIME-тип на основе результатов getimagesize
                        $detectedMimeType = $imageInfo['mime']; 
                        chmod($tmpFilePath, 0666); // Устанавливаем права на файл
                        
                        // Создаем массив файла из локального файла
                        $fileArray = CFile::MakeFileArray($tmpFilePath);
                        
                        if ($fileArray) {
                            // Явно указываем тип файла и модуль
                            $fileArray["MODULE_ID"] = "iblock";
                            $fileArray["name"] = basename($imageUrl);
                            $fileArray["type"] = $detectedMimeType; // Используем обнаруженный MIME-тип
                            
                            // Добавляем отладочную информацию
                            $width = $imageInfo[0];
                            $height = $imageInfo[1];
                            
                            // Сохраняем файл в Bitrix и получаем ID
                            $fileId = CFile::SaveFile($fileArray, "iblock");
                            
                            if ($fileId > 0) {
                                // Добавляем ID файла в массив дополнительных изображений
                                $morePhoto[] = $fileId; // Сохраняем только ID, не массив файла
                                logMessage("Доп. изображение сохранено в Bitrix с ID: {$fileId}, размер: {$width}x{$height}");
                                
                                // Сохраняем также оригинальный URL в свойстве для справки
                                if (!isset($productData["PROPERTY_VALUES"])) {
                                    $productData["PROPERTY_VALUES"] = array();
                                }
                                if (!isset($productData["PROPERTY_VALUES"]["IMAGE_LINKS"])) {
                                    $productData["PROPERTY_VALUES"]["IMAGE_LINKS"] = array();
                                }
                                $productData["PROPERTY_VALUES"]["IMAGE_LINKS"][] = $directImageUrl;
                            } else {
                                logMessage("Ошибка при сохранении доп. изображения в Bitrix: {$fileArray["name"]}");
                            }
                        } else {
                            logMessage("Ошибка при создании массива файла для {$imageUrl}");
                        }
                        
                        // Удаляем временный файл
                        @unlink($tmpFilePath);
                    } else {
                        logMessage("Ошибка при загрузке доп. файла {$imageUrl}");
                    }
                }
            }
            
            // Добавляем массивы файлов в свойство MORE_PHOTO
            if (!empty($morePhoto)) {
                $productData["PROPERTY_VALUES"]["MORE_PHOTO"] = $morePhoto;
                logMessage("Установлено дополнительных изображений: " . count($morePhoto));
            }
        }
        // Добавление изображений в массив данных товара произведено выше
        
        // Добавляем или обновляем товар
        $result = findOrCreateProduct($productId, $productData);
        
        if (isset($result['ELEMENT_ID']) && $result['ELEMENT_ID'] > 0) {
            $elementId = $result['ELEMENT_ID'];
            $propertyValues = array();
            
            // Дополнительно переустанавливаем свойство MORE_PHOTO
            if (!empty($morePhotoIds)) {
                $propertyValues["MORE_PHOTO"] = $morePhotoIds;
            }
            
            // Применяем свойства через SetPropertyValuesEx
            if (!empty($propertyValues)) {
                CIBlockElement::SetPropertyValuesEx($elementId, $catalogIblockId, $propertyValues);
                logMessage("Установлены дополнительные свойства для элемента ID: $elementId");
            }
            
            // Принудительно генерируем ресайз изображений
            if (isset($productData["PREVIEW_PICTURE"]) && is_numeric($productData["PREVIEW_PICTURE"])) {
                $previewImg = CFile::ResizeImageGet(
                    $productData["PREVIEW_PICTURE"], 
                    array('width' => 500, 'height' => 500), 
                    BX_RESIZE_IMAGE_PROPORTIONAL, 
                    true
                );
                logMessage("Создан кешированный ресайз основного изображения: " . $previewImg['src']);
            }
            
            if (isset($productData["DETAIL_PICTURE"]) && is_numeric($productData["DETAIL_PICTURE"])) {
                $detailImg = CFile::ResizeImageGet(
                    $productData["DETAIL_PICTURE"], 
                    array('width' => 800, 'height' => 800), 
                    BX_RESIZE_IMAGE_PROPORTIONAL, 
                    true
                );
                logMessage("Создан кешированный ресайз детального изображения");
            }
            
            // Создаем ресайзы для дополнительных изображений
            if (!empty($morePhotoIds)) {
                foreach ($morePhotoIds as $photoId) {
                    $moreImg = CFile::ResizeImageGet(
                        $photoId, 
                        array('width' => 500, 'height' => 500), 
                        BX_RESIZE_IMAGE_PROPORTIONAL, 
                        true
                    );
                    logMessage("Создан кешированный ресайз доп. изображения: {$photoId}");
                }
            }
        }
        
        if ($result) {
            $totalProducts++;
        } else {
            $errorProducts++;
        }
    }
}

// Выводим статистику импорта
logMessage("Обработано товаров: " . $totalProducts);
logMessage("Добавлено новых товаров: " . $addedProducts);
logMessage("Обновлено существующих товаров: " . $updatedProducts);
logMessage("Ошибок при обработке товаров: " . $errorProducts);

// Очищаем кеш Bitrix
BitrixClearCache();

echo "<h2>Импорт завершен!</h2>";
echo "<p>Всего обработано товаров: {$totalProducts}</p>";
echo "<p>Ошибок: {$errorProducts}</p>";

function BitrixClearCache() {
    global $catalogIblockId;
    logMessage("Начинаем очистку кеша Bitrix");
    
    // Очищаем кеш компонентов
    if(method_exists('CIBlock', 'clearIblockTagCache')) {
        CIBlock::clearIblockTagCache($catalogIblockId);
        logMessage("Очищен теговый кеш инфоблока");
    }

    // Очищаем managed кеш
    if (isset($GLOBALS["CACHE_MANAGER"])) {
        $GLOBALS["CACHE_MANAGER"]->ClearByTag("iblock_id_" . $catalogIblockId);
        logMessage("Очищен managed кеш по тегу iblock");
    }

    // Очищаем остальные кеши
    if (function_exists('BXClearCache')) {
        BXClearCache(true);
        logMessage("Очищен общий кеш Bitrix");
    }
    
    // Очищаем кеш каталога
    if(class_exists('CCatalogProduct')) {
        CCatalogProduct::ClearCache();
        logMessage("Очищен кеш продуктов каталога");
    }
    
    // Инициируем пересоздание кеша изображений через запрос к файлам
    $arFilter = array(
        "MODULE_ID" => "iblock",
        "EXTERNAL_ID" => ""
    );
    $rsFiles = CFile::GetList(array(), $arFilter);
    $counter = 0;
    while($arFile = $rsFiles->Fetch()) {
        if ($counter > 100) break; // Ограничиваем количество файлов для производительности
        
        if (!empty($arFile["ID"])) {
            // Создаем ресайз для каждого изображения
            $resized = CFile::ResizeImageGet(
                $arFile["ID"], 
                array('width' => 500, 'height' => 500), 
                BX_RESIZE_IMAGE_PROPORTIONAL, 
                true
            );
            $counter++;
        }
    }
    
    logMessage("Созданы кешированные изображения для {$counter} файлов");
}

logMessage("Импорт завершен!");

?>
