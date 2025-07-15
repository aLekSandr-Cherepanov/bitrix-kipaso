<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3) Включаем логирование
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'].'/upload/owen_import_log.txt');
ini_set('log_errors', 1);

// Убираем лимит времени для выполнения скрипта
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');

// для работы с файлами включим модуль Битрикс
$_SERVER["DOCUMENT_ROOT"] = __DIR__;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/iblock/prolog.php');
// все необходимые классы и функции уже подключены

if(!CModule::IncludeModule('iblock')) {
    trigger_error('Модуль iblock не подключен', E_USER_ERROR);
}

// 4) Проверяем XML
$iblockId = 16;
$xmlPath  = $_SERVER["DOCUMENT_ROOT"]."/catalogOven.xml";
if(!file_exists($xmlPath)) {
    die("XML-файл не найден по пути $xmlPath");
}

var_dump(ini_get('allow_url_fopen'));

// 1. Загружаем XML
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

// перед импортом всех товаров, где-то в начале:
$docDir = $_SERVER['DOCUMENT_ROOT'].'/upload/doc/';
if (!is_dir($docDir)) {
    // создаём папку вместе со всеми необходимыми директориями
    mkdir($docDir, 0755, true);
}

// Функция для загрузки файла по URL
function downloadFile($url, $description = '') {
    global $docDir;

    // Получаем имя файла из URL
    $fileName  = basename($url);
    $localPath = $docDir . $fileName;

    // Если файл уже сохранён локально, просто используем его
    if (file_exists($localPath)) {
        $fileArray = CFile::MakeFileArray($localPath);
        $fileArray['MODULE_ID']  = 'iblock';
        $fileArray['description'] = $description ?: $fileName;
        return $fileArray;
    }

    // Проверяем, был ли файл уже загружен в Битрикс ранее
    $rsFiles = CFile::GetList([], [
        'ORIGINAL_NAME' => $fileName,
    ]);

    if ($fileItem = $rsFiles->Fetch()) {
        $fileArray = CFile::MakeFileArray($fileItem['ID']);
        $fileArray['MODULE_ID']  = 'iblock';
        $fileArray['description'] = $description ?: $fileName;
        return $fileArray;
    }

    // Скачиваем файл, если его нет на сервере
    $http = new HttpClient([
        'socketTimeout' => 600,
        'streamTimeout' => 1800,
        'disableSslVerification' => true,
    ]);

    if ($http->download($url, $localPath)) {
        $fileArray = CFile::MakeFileArray($localPath);
        $fileArray['MODULE_ID']  = 'iblock';
        $fileArray['description'] = $description ?: $fileName;

        return $fileArray;
    }

    trigger_error("Не удалось скачать файл: {$url}, ошибка: " . $http->getError(), E_USER_WARNING);
    return false;
}

function importProducts() {
    // Дополнительно устанавливаем параметры для загрузки файлов
    ini_set('default_socket_timeout', 600); // 10 минут
    
    global $xml, $iblockId, $docDir;
    $el = new CIBlockElement;

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

            // 2) Пробегаем товары
            foreach($sub->products->product as $p) {
                $xmlId      = (string)$p->id;
                $name       = (string)$p->name;
                $detailText = (string)$p->desc;   
                $imgUrl     = (string)$p->image;

                // 3) Загружаем картинку
                $imageFile = \CFile::MakeFileArray($imgUrl);
                $imageFile["MODULE_ID"] = "iblock";

                // 4) Подготовка массива свойств для элемента
                $arProps = [];
                
                // 5) Обработка документов и сертификатов
                // Отладка - проверяем наличие документов
                echo "<hr>Проверка документов для товара: {$name}<br>";
                
                if(isset($p->docs)) {
                    echo "Секция docs существует.<br>";
                    
                    // Используем count() для SimpleXML более безопасным способом
                    $docsCount = count($p->docs->children());
                    echo "Количество дочерних элементов в docs: {$docsCount}<br>";
                    // Массив для хранения документов
                    $docsArray = [];
                    $certsArray = [];
                    
                    // Отладка - выводим все дочерние элементы
                    foreach($p->docs->children() as $childName => $child) {
                        echo "Найден дочерний элемент: {$childName}<br>";
                    }
                    
                    // Перебираем все группы документов
                    foreach($p->docs->doc as $docGroup) {
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
                                $docFile = downloadFile($docLink, $docName);
                                
                                // Если файл успешно загружен, добавляем его в соответствующий массив
                                if ($docFile) {
                                
                                    // Распределяем по типам в зависимости от группы
                                    if(mb_strtolower($groupName) === 'документация') {
                                        $docsArray[] = $docFile;
                                    } elseif(mb_strtolower($groupName) === 'сертификаты') {
                                        $certsArray[] = $docFile;
                                    }
                                    // Пропускаем обработку ПО по запросу пользователя
                                }
                            }
                        }
                    }
                    
                    // Добавляем свойства в массив, используя коды свойств из скриншота
                    if(!empty($docsArray)) {
                        $arProps['DOCS'] = $docsArray;
                        echo "Добавлено " . count($docsArray) . " документов в свойство DOCS<br>";
                        echo "<pre>";
                        print_r($docsArray);
                        echo "</pre>";
                    } else {
                        echo "Массив документов пуст<br>";
                    }
                    
                    if(!empty($certsArray)) {
                        $arProps['CERTIFICATE'] = $certsArray;
                        echo "Добавлено " . count($certsArray) . " сертификатов в свойство CERTIFICATE<br>";
                        echo "<pre>";
                        print_r($certsArray);
                        echo "</pre>";
                    } else {
                        echo "Массив сертификатов пуст<br>";
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
                    "PREVIEW_PICTURE"   => $imageFile,
                    "DETAIL_PICTURE"    => $imageFile,
                    "PROPERTY_VALUES"   => $arProps, // Добавляем свойства
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
                    
                    // При обновлении товара нужно передать свойства через CIBlockElement::SetPropertyValuesEx
                    if(!$el->Update($resE["ID"], $arLoad)) {
                        trigger_error(
                            "Ошибка обновления товара {$name}: ".$el->LAST_ERROR,
                            E_USER_WARNING
                        );
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