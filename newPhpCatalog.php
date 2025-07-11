<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3) Включаем расширенное логирование
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'].'/upload/owen_import_log.txt');
ini_set('log_errors', 1);

// Убираем лимит времени 
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '1024M'); 


ini_set('max_input_time', 0);       
ini_set('default_socket_timeout', 600); 
ini_set('post_max_size', '64M');    
ini_set('upload_max_filesize', '64M');
ini_set('output_buffering', 'Off'); 


if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', 1);
    apache_setenv('dont-vary', 1);
}


ignore_user_abort(true);


$_SERVER["DOCUMENT_ROOT"] = __DIR__;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/iblock/prolog.php');


if(!CModule::IncludeModule('iblock')) {
    trigger_error('Модуль iblock не подключен', E_USER_ERROR);
}


$iblockId = 16;
$xmlPath  = $_SERVER["DOCUMENT_ROOT"]."/catalogOven.xml";
if(!file_exists($xmlPath)) {
    die("XML-файл не найден по пути $xmlPath");
}



$xml = simplexml_load_file($xmlPath);
if(!$xml) {
    die("Ошибка парсинга XML");
}

function importSections($nodes, $parentId = 0) {
    global $iblockId;
    $secObj = new CIBlockSection;
    foreach($nodes as $node) {
        $code = (string)$node->id;
        $name = (string)$node->name;

        
        $db = CIBlockSection::GetList(
            [], 
            ["IBLOCK_ID"=>$iblockId, "CODE"=>$code], 
            false, 
            ["ID"]
        );
        if($exist = $db->Fetch()) {
            $sectionId = $exist["ID"];
        } else {
            $arFields = [
                "IBLOCK_ID"      => $iblockId,
                "NAME"           => $name,
                "CODE"           => $code,
                "SORT"           => 500,
                "IBLOCK_SECTION_ID" => $parentId,
                "ACTIVE"         => "Y",
            ];
            $sectionId = $secObj->Add($arFields);
            if(!$sectionId) {
                trigger_error("Ошибка создания раздела $name: ". $secObj->LAST_ERROR, E_USER_WARNING);
                continue;
            }
        }

        
        if(isset($node->items->item)) {
            importSections($node->items->item, $sectionId);
        }
    }
}

use Bitrix\Main\Web\HttpClient;


$docDir = $_SERVER['DOCUMENT_ROOT'].'/upload/doc/';
if (!is_dir($docDir)) {
    mkdir($docDir, 0755, true);
}

/**
 * Функция для проверки соединения с БД и переподключения при необходимости
 */
function checkDBConnection() {
    global $DB;
    
    try {
        // Проверяем соединение путем выполнения простого запроса
        $DB->Query("SELECT 1");
    } catch (\Exception $e) {
        // Если возникла ошибка, пробуем переподключиться
        try {
            $DB->Disconnect();
            $connected = $DB->Connect(
                $DB->DBHost, 
                $DB->DBName, 
                $DB->DBLogin, 
                $DB->DBPassword
            );
            
            if (!$connected) {
                echo "Ошибка соединения с базой данных. Попробуйте запустить скрипт заново.<br>";
                return false;
            }
        } catch (\Exception $e) {
            echo "Ошибка переподключения к БД: " . $e->getMessage() . "<br>";
            return false;
        }
    }
    
    return true;
}

function downloadFile($url, $description = '') {
    global $docDir;
    
    
    $fileName = basename($url);
    $localPath = $docDir . $fileName;
    
    
    if (file_exists($localPath)) {
        
        error_log("Файл уже существует: {$fileName}, используем локальную копию");
        
        
        $fileArray = CFile::MakeFileArray($localPath);
        $fileArray['MODULE_ID'] = 'iblock';
        
        if (!empty($description)) {
            $fileArray['description'] = $description;
        } else {
            $fileArray['description'] = $fileName;
        }
        
        return $fileArray;
    }
    
    
    $http = new HttpClient([
        'socketTimeout' => 600,    
        'streamTimeout' => 1800,   
        'disableSslVerification' => true, 
        'redirect' => true,        
        'redirectMax' => 5,         
        'waitResponse' => true    
    ]);
    
    
    try {
        $success = $http->download($url, $localPath);
        if ($success) {
            
            $fileArray = CFile::MakeFileArray($localPath);
            $fileArray['MODULE_ID'] = 'iblock';
            
            
            
            if (!empty($description)) {
                $fileArray['description'] = $description; 
            } else {
                $fileArray['description'] = $fileName; 
            }
            
            return $fileArray;
        } else {
            $errorMessage = "Не удалось скачать файл: {$url}, ошибка: " . $http->getError();
            trigger_error($errorMessage, E_USER_WARNING);
            error_log($errorMessage);
            return false;
        }
    } catch (\Exception $e) {
        $errorMessage = "Исключение при загрузке файла {$url}: " . $e->getMessage();
        trigger_error($errorMessage, E_USER_WARNING);
        error_log($errorMessage);
        return false;
    }
}

/**
 * Функция обрабатывает документы и сертификаты товара
 * @param SimpleXMLElement $product Товар из XML
 * @return array Массив с двумя элементами: [документы, сертификаты]
 */
function collectProductDocs($product) {
    global $docDir;
    $docsArray = [];
    $certsArray = [];
    
    echo "<hr>Проверка документов для товара: {$product->name}<br>";
    
    if(isset($product->docs)) {
        echo "Секция docs существует.<br>";
        
        // Используем count() для SimpleXML более безопасным способом
        $docsCount = count($product->docs->children());
        echo "Количество дочерних элементов в docs: {$docsCount}<br>";
        
        // Отладка - выводим все дочерние элементы
        foreach($product->docs->children() as $childName => $child) {
            echo "Найден дочерний элемент: {$childName}<br>";
        }
        
        // Перебираем все группы документов
        foreach($product->docs->doc as $docGroup) {
            echo "Обработка группы документов: " . (string)$docGroup->name . "<br>";
            $groupName = (string)$docGroup->name;
            
            // Перебираем документы в группе
            if(isset($docGroup->items)) {
                $itemsCount = count($docGroup->items->children());
                echo "Количество элементов items: {$itemsCount}<br>";
                
                foreach($docGroup->items->item as $docItem) {
                    $docName = (string)$docItem->name;
                    $docLink = (string)$docItem->link;
                    
                    // Скачиваем файл и готовим его для загрузки в Битрикс
                    echo "Загрузка файла: {$docLink}<br>";
                    $fileArray = downloadFile($docLink, $docName);
                    
                    // Если файл успешно загружен, добавляем его в соответствующий массив
                    if ($fileArray) {
                        // Распределяем по типам в зависимости от группы
                        if(mb_strtolower($groupName) === 'документация') {
                            $docsArray[] = $fileArray;
                        } elseif(mb_strtolower($groupName) === 'сертификаты') {
                            $certsArray[] = $fileArray;
                        }
                        // Пропускаем обработку ПО по запросу пользователя
                    }
                }
            }
        }
    }
    
    // Выводим информацию о количестве найденных файлов
    if(!empty($docsArray)) {
        echo "Добавлено " . count($docsArray) . " документов в свойство DOCS<br>";
        echo "<pre>";
        print_r($docsArray);
        echo "</pre>";
    } else {
        echo "Документы не найдены<br>";
    }
    
    if(!empty($certsArray)) {
        echo "Добавлено " . count($certsArray) . " сертификатов в свойство SERT<br>";
        echo "<pre>";
        print_r($certsArray);
        echo "</pre>";
    } else {
        echo "Сертификаты не найдены<br>";
    }
    
    return [$docsArray, $certsArray];
}

function importProducts() {
    
    ini_set('default_socket_timeout', 0); 
    
    global $xml, $iblockId, $docDir;
    $el = new CIBlockElement;
    
   
    $processedCount = 0;
    $totalProducts = 0;
    
    
    foreach($xml->categories->category as $cat) {
        foreach($cat->items->item as $sub) {
            foreach($sub->products->product as $p) {
                $totalProducts++;
            }
        }
    }
    

    foreach($xml->categories->category as $cat) {
        foreach($cat->items->item as $sub) {
            // 1) Находим ID секции
            $sectionCode = (string)$sub->id;
            $dbS = CIBlockSection::GetList(
                [], 
                ["IBLOCK_ID" => $iblockId, "CODE" => $sectionCode], 
                false, 
                ["ID"]
            );
            if(!$sec = $dbS->Fetch()) {
                continue; 
            }
            $sectionId = $sec["ID"];

            
            foreach($sub->products->product as $p) {
                $xmlId      = (string)$p->id;
                $name       = (string)$p->name;
                $detailText = (string)$p->desc;  
                $specificText = trim((string)$p->specs); 
                
                // Вывод отладочной информации о тегах specs
                if ($specificText !== '') {
                } else {
                }
                
                $imgUrl     = (string)$p->image;

                
                $fileArray = \CFile::MakeFileArray($imgUrl);
                $fileArray["MODULE_ID"] = "iblock";

                $propertyValues = [];

                // Обрабатываем документы и сертификаты
                list($docsArray, $certsArray) = collectProductDocs($p);
                
                // Проверяем соединение с БД перед работой с файлами
                checkDBConnection();
                
                // Добавляем свойства в массив, формируем правильный формат для множественных свойств типа "файл"
                if(!empty($docsArray)) {
                    // Для множественных свойств типа файл, нужно передавать значения в специальном формате
                    $docsValues = [];
                    foreach($docsArray as $fileArray) {
                        $fileId = CFile::SaveFile($fileArray, "iblock");
                        if ($fileId) {
                            $docsValues[] = $fileId;
                            echo "Файл сохранен с ID: {$fileId}<br>";
                        }
                    }
                    
                    if (!empty($docsValues)) {
                        $propertyValues['DOCS'] = $docsValues;
                        echo "<hr>Свойство DOCS подготовлено к сохранению: " . count($docsValues) . " документов<br>";
                        echo "<pre>";
                        var_dump($docsValues);
                        echo "</pre>";
                    }
                }
                
                if(!empty($certsArray)) {
                    // Для множественных свойств типа файл, нужно передавать значения в специальном формате
                    $certValues = [];
                    foreach($certsArray as $fileArray) {
                        $fileId = CFile::SaveFile($fileArray, "iblock");
                        if ($fileId) {
                            $certValues[] = $fileId;
                            echo "Сертификат сохранен с ID: {$fileId}<br>";
                        }
                    }
                    
                    if (!empty($certValues)) {
                        $propertyValues['SERT'] = $certValues;
                        echo "<hr>Свойство SERT подготовлено к сохранению: " . count($certValues) . " сертификатов<br>";
                        echo "<pre>";
                        var_dump($certValues);
                        echo "</pre>";
                    }
                }

                // 4.1) Технические характеристики (HTML-свойство)
                if ($specificText !== '') {
                    // Проверяем, содержит ли текст HTML-теги
                    if (strip_tags($specificText) !== $specificText) {
                        // Если содержит HTML-теги, сохраняем как HTML
                        $propertyValues['SPECIFICATIONS_TEXT'] = [
                            'VALUE' => [
                                'TEXT' => $specificText,
                                'TYPE' => 'HTML',
                            ],
                        ];
                    } else {
                        // Если это просто текст, то оборачиваем его в параграфы для форматирования
                        $formattedText = '<p>' . str_replace("\n", '</p><p>', $specificText) . '</p>';
                        $formattedText = str_replace('<p></p>', '', $formattedText);
                        
                        $propertyValues['SPECIFICATIONS_TEXT'] = [
                            'VALUE' => [
                                'TEXT' => $formattedText,
                                'TYPE' => 'HTML',
                            ],
                        ];
                    }
                }
                
                // Объединяем все свойства в один массив
                $allProperties = $propertyValues;
                
                // Проверяем наличие свойств перед формированием загрузки
                if (!empty($allProperties)) {
                    echo "<hr>Всего свойств для загрузки: " . count($allProperties) . "<br>";
                    if (isset($allProperties['DOCS'])) {
                        echo "Документы присутствуют: " . count($allProperties['DOCS']) . "<br>";
                    }
                    if (isset($allProperties['SERT'])) {
                        echo "Сертификаты присутствуют: " . count($allProperties['SERT']) . "<br>";
                    }
                }
                
                $arLoad = [
                    "IBLOCK_ID"         => $iblockId,
                    "XML_ID"            => $xmlId,
                    "NAME"              => $name,
                    "CODE"              => $xmlId,
                    "ACTIVE"            => "Y",
                    "IBLOCK_SECTION_ID" => $sectionId,
                    "DETAIL_TEXT"       => $detailText,
                    "PREVIEW_PICTURE"   => $fileArray,
                    "DETAIL_PICTURE"    => $fileArray,
                    "PROPERTY_VALUES"   => $allProperties, 
                ];

                $resE = CIBlockElement::GetList(
                    [], 
                    ["IBLOCK_ID" => $iblockId, "XML_ID" => $xmlId], 
                    false, 
                    false, 
                    ["ID"]
                )->Fetch();

                if($resE) {
                    $current = CIBlockElement::GetByID($resE["ID"])->GetNext();
                    if ($current && $current["DETAIL_PICTURE"]) {
                        $existingFile = CFile::GetByID($current["DETAIL_PICTURE"])->Fetch();
                        
                        if ($existingFile["ORIGINAL_NAME"] === basename($imgUrl)) {
                            unset($arLoad["PREVIEW_PICTURE"], $arLoad["DETAIL_PICTURE"]);
                        }
                    }
                    
                   
                    if(!$el->Update($resE["ID"], $arLoad)) {
                        trigger_error(
                            "Ошибка обновления товара {$name}: ".$el->LAST_ERROR,
                            E_USER_WARNING
                        );
                    } else {
                        
                        // Дополнительно установим свойства напрямую, чтобы убедиться, что они сохранены
                        if (!empty($allProperties)) {
                            echo "<hr>Явно устанавливаем свойства для товара ID: {$resE["ID"]}<br>";
                            
                            // Проверяем соединение с БД перед операциями с файлами
                            checkDBConnection();
                            
                            // Устанавливаем свойства напрямую
                            CIBlockElement::SetPropertyValuesEx($resE["ID"], $iblockId, $allProperties);
                            
                            // Проверяем, сохранились ли документы
                            echo "<hr>Проверка сохранения документов:<br>";
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "DOCS"]);
                            $docsCount = 0;
                            while($prop = $dbProps->Fetch()) {
                                if($prop["VALUE"]) {
                                    $docsCount++;
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    if($fileInfo) {
                                        echo "Документ {$docsCount}: ID {$prop["VALUE"]}, Имя: {$fileInfo['ORIGINAL_NAME']}, Описание: {$prop["DESCRIPTION"]}<br>";
                                    } else {
                                        echo "Документ {$docsCount}: ID {$prop["VALUE"]} - информация о файле не найдена<br>";
                                    }
                                }
                            }
                            
                            if ($docsCount === 0) {
                                echo "Документы не найдены в свойствах товара<br>";
                            }
                            
                            // Проверяем, сохранились ли сертификаты
                            echo "<hr>Проверка сохранения сертификатов:<br>";
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "SERT"]);
                            $certsCount = 0;
                            while($prop = $dbProps->Fetch()) {
                                if($prop["VALUE"]) {
                                    $certsCount++;
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    if($fileInfo) {
                                        echo "Сертификат {$certsCount}: ID {$prop["VALUE"]}, Имя: {$fileInfo['ORIGINAL_NAME']}, Описание: {$prop["DESCRIPTION"]}<br>";
                                    } else {
                                        echo "Сертификат {$certsCount}: ID {$prop["VALUE"]} - информация о файле не найдена<br>";
                                    }
                                }
                            }
                            
                            if ($certsCount === 0) {
                                echo "Сертификаты не найдены в свойствах товара<br>";
                            }
                            
                            // Проверяем сохранение свойства SPECIFICATIONS_TEXT
                            $dbSpecsProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "SPECIFICATIONS_TEXT"]);
                            $hasSpecText = false;
                            while($specProp = $dbSpecsProps->Fetch()) {
                                if (!empty($specProp["VALUE"])) {
                                    $hasSpecText = true;
                                }
                            }
                        }
                    }
                } else {
                   
                    $newElementId = $el->Add($arLoad);
                    if(!$newElementId) {
                        trigger_error(
                            "Ошибка добавления товара {$name}: ".$el->LAST_ERROR,
                            E_USER_WARNING
                        );
                    } else {
                        
                        // Если есть свойства для нового элемента - устанавливаем их напрямую
                        if (!empty($allProperties)) {
                            echo "<hr>Явно устанавливаем свойства для нового товара ID: {$newElementId}<br>";
                            
                            // Проверяем соединение с БД перед операциями с файлами
                            checkDBConnection();
                            
                            // Устанавливаем свойства напрямую
                            CIBlockElement::SetPropertyValuesEx($newElementId, $iblockId, $allProperties);
                            
                            // Проверяем, сохранились ли документы
                            echo "<hr>Проверка сохранения документов для нового товара:<br>";
                            $dbProps = CIBlockElement::GetProperty($iblockId, $newElementId, [], ["CODE" => "DOCS"]);
                            $docsCount = 0;
                            while($prop = $dbProps->Fetch()) {
                                if($prop["VALUE"]) {
                                    $docsCount++;
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    if($fileInfo) {
                                        echo "Документ {$docsCount}: ID {$prop["VALUE"]}, Имя: {$fileInfo['ORIGINAL_NAME']}, Описание: {$prop["DESCRIPTION"]}<br>";
                                    } else {
                                        echo "Документ {$docsCount}: ID {$prop["VALUE"]} - информация о файле не найдена<br>";
                                    }
                                }
                            }
                            
                            if ($docsCount === 0) {
                                echo "Документы не найдены в свойствах нового товара<br>";
                            }
                            
                            // Проверяем, сохранились ли сертификаты
                            echo "<hr>Проверка сохранения сертификатов для нового товара:<br>";
                            $dbProps = CIBlockElement::GetProperty($iblockId, $newElementId, [], ["CODE" => "SERT"]);
                            $certsCount = 0;
                            while($prop = $dbProps->Fetch()) {
                                if($prop["VALUE"]) {
                                    $certsCount++;
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    if($fileInfo) {
                                        echo "Сертификат {$certsCount}: ID {$prop["VALUE"]}, Имя: {$fileInfo['ORIGINAL_NAME']}, Описание: {$prop["DESCRIPTION"]}<br>";
                                    } else {
                                        echo "Сертификат {$certsCount}: ID {$prop["VALUE"]} - информация о файле не найдена<br>";
                                    }
                                }
                            }
                            
                            if ($certsCount === 0) {
                                echo "Сертификаты не найдены в свойствах нового товара<br>";
                            }
                        }
                    }
                }
            }
        }
    }
}


// 4. Запускаем
importSections($xml->categories->category);
importProducts();

echo "Импорт завершён.";