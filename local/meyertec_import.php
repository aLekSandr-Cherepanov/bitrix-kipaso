<?php
/**
 * Скрипт импорта товаров из meyertec XML
 * 
 * Импортирует категории и товары с изображениями, характеристиками
 * и другими данными из XML-файла meyertec.
 */

// Подключаем ядро Bitrix
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Проверяем наличие необходимых модулей
if (!CModule::IncludeModule("iblock")) {
    die("Ошибка подключения модуля iblock");
}
if (!CModule::IncludeModule("catalog")) {
    die("Ошибка подключения модуля catalog");
}

// Настройки
$XML_URL = "https://meyertec.owen.ru/export/catalog.xml?host=owen.kipaso.ru&key=afOavhVgttik-rIgesgbk6Zkk-Y_by8W";
$IBLOCK_ID = 0; // ID вашего инфоблока с товарами (нужно заменить на реальный)
$SECTION_IBLOCK_ID = 0; // ID вашего инфоблока с разделами (если совпадает с товарным, то оставить тем же)
$LOG_FILE = $_SERVER["DOCUMENT_ROOT"] . "/local/meyertec_import_log.txt";

// Логирование
function logMessage($message) {
    global $LOG_FILE;
    $logMessage = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    file_put_contents($LOG_FILE, $logMessage, FILE_APPEND);
    echo $message . "<br>";
}

// Функция для проверки соединения с БД
function checkDBConnection() {
    global $DB;
    
    if (!$DB->IsConnected()) {
        try {
            $DB->DoConnect();
            logMessage("Соединение с базой данных восстановлено");
            return true;
        } catch (Exception $e) {
            logMessage("Ошибка восстановления соединения с БД: " . $e->getMessage());
            return false;
        }
    }
    
    return true;
}

// Функция для загрузки файла по URL
function downloadFile($url) {
    $tempName = tempnam(sys_get_temp_dir(), 'meyertec_');
    $client = new \Bitrix\Main\Web\HttpClient();
    if ($client->download($url, $tempName)) {
        $fileInfo = pathinfo($url);
        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : 'jpg';
        
        $fileArray = CFile::MakeFileArray($tempName);
        if ($fileArray) {
            $fileArray['name'] = md5(microtime() . $url) . '.' . $extension;
            return $fileArray;
        }
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
        $arFields = Array(
            "ACTIVE" => "Y",
            "IBLOCK_ID" => $SECTION_IBLOCK_ID,
            "NAME" => $name,
            "SORT" => 500,
            "XML_ID" => $xml_id,
        );
        
        if ($parentId > 0) {
            $arFields["IBLOCK_SECTION_ID"] = $parentId;
        }
        
        $sectionId = $bs->Add($arFields);
        if ($sectionId) {
            logMessage("Создан раздел: " . $name . " (ID: " . $sectionId . ")");
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
    
    // Добавляем товар
    $productId = $el->Add($arLoadProductArray);
    
    if ($productId) {
        logMessage("Добавлен товар: " . $data["NAME"] . " (ID: " . $productId . ")");
        
        // Устанавливаем цены
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
file_put_contents($LOG_FILE, '');

// Начинаем импорт
logMessage("Начинаем импорт товаров из XML");

// Загружаем XML
logMessage("Загрузка XML из " . $XML_URL);
$xml = simplexml_load_file($XML_URL);

if (!$xml) {
    logMessage("Ошибка загрузки XML");
    die("Ошибка загрузки XML");
}

// Счетчики для статистики
$totalCategories = 0;
$totalProducts = 0;
$addedProducts = 0;
$updatedProducts = 0;
$errorProducts = 0;

// Обрабатываем категории и товары
logMessage("Обработка категорий...");

// Мапинг ID категорий для корректного соотнесения товаров
$categoryMap = array();

if (isset($xml->groups->group)) {
    // Обрабатываем категории
    foreach ($xml->groups->group as $group) {
        $groupName = (string)$group->name;
        $groupId = (string)$group->id;
        $parentId = 0;
        
        // Если есть родительская группа
        if (!empty($group->parentId)) {
            $parentGroupId = (string)$group->parentId;
            if (isset($categoryMap[$parentGroupId])) {
                $parentId = $categoryMap[$parentGroupId];
            }
        }
        
        // Создаем или находим раздел
        $sectionId = findOrCreateSection($groupName, $parentId, $groupId);
        if ($sectionId) {
            $categoryMap[$groupId] = $sectionId;
            $totalCategories++;
        }
    }
}

logMessage("Обработано категорий: " . $totalCategories);
logMessage("Обработка товаров...");

// Обрабатываем товары
if (isset($xml->products->product)) {
    foreach ($xml->products->product as $product) {
        // Основная информация о товаре
        $productId = (string)$product->id;
        $productName = (string)$product->name;
        $productFullName = (string)$product->fullName;
        $price = (float)$product->price;
        $groupId = (string)$product->groupId;
        $description = (string)$product->text3;
        $characteristics = (string)$product->packing;
        
        // Проверяем наличие секции для товара
        $sectionId = 0;
        if (!empty($groupId) && isset($categoryMap[$groupId])) {
            $sectionId = $categoryMap[$groupId];
        }
        
        // Подготавливаем данные для товара
        $productData = array(
            "NAME" => $productFullName ?: $productName,
            "XML_ID" => $productId,
            "IBLOCK_SECTION_ID" => $sectionId,
            "DETAIL_TEXT" => $description,
            "PRICE" => $price,
            "PROPERTY_VALUES" => array()
        );
        
        // Добавляем свойства товара
        $productData["PROPERTY_VALUES"]["XML_ID"] = $productId;
        $productData["PROPERTY_VALUES"]["NAME"] = $productName;
        
        if (!empty($description)) {
            $productData["PROPERTY_VALUES"]["DETAIL_TEXT"] = $description;
        }
        
        if (!empty($characteristics)) {
            $productData["PROPERTY_VALUES"]["CHARACTERISTICS"] = $characteristics;
        }
        
        // Обрабатываем изображения
        $pictureIds = array();
        $mainPicture = null;
        
        if (isset($product->images->image) && count($product->images->image) > 0) {
            $isFirstImage = true;
            
            foreach ($product->images->image as $image) {
                $imageUrl = (string)$image['src'];
                if (!empty($imageUrl)) {
                    // Проверяем соединение с БД
                    if (!checkDBConnection()) {
                        logMessage("Ошибка соединения с БД при обработке изображений");
                        continue;
                    }
                    
                    $fileArray = downloadFile($imageUrl);
                    if ($fileArray) {
                        $picId = CFile::SaveFile($fileArray, "iblock");
                        
                        if ($picId) {
                            if ($isFirstImage) {
                                $mainPicture = $picId;
                                $isFirstImage = false;
                            } else {
                                $pictureIds[] = $picId;
                            }
                        }
                    }
                }
            }
            
            // Устанавливаем основное изображение
            if ($mainPicture) {
                $productData["PREVIEW_PICTURE"] = CFile::MakeFileArray($mainPicture);
            }
            
            // Устанавливаем дополнительные изображения
            if (!empty($pictureIds)) {
                $productData["PROPERTY_VALUES"]["MORE_PHOTO"] = $pictureIds;
            }
        }
        
        // Добавляем или обновляем товар
        $result = findOrCreateProduct($productId, $productData);
        
        if ($result) {
            $totalProducts++;
        } else {
            $errorProducts++;
        }
    }
}

logMessage("Обработано товаров: " . $totalProducts);
logMessage("Ошибок при обработке товаров: " . $errorProducts);
logMessage("Импорт завершен!");
