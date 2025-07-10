<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Включаем расширенное логирование
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

// Стандартная функция загрузки файлов/
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
            trigger_error("Не удалось скачать файл: {$url}, ошибка: " . $http->getError(), E_USER_WARNING);
            return false;
        }
    } catch (\Exception $e) {
        trigger_error("Исключение при загрузке файла {$url}: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

function importProducts() {
    global $xml, $iblockId;
    $el = new CIBlockElement;

    foreach($xml->categories->category as $cat) {
        foreach($cat->items->item as $sub) {
            // Находим ID секции
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

            // Пробегаем товары
            foreach($sub->products->product as $p) {
                $xmlId      = (string)$p->id;
                $name       = (string)$p->name;
                $previewText= (string)$p->desc;
                $detailText = (string)$p->desc;   
                $imgUrl     = (string)$p->image;
                
                $fileArray = [];
                if ($imgUrl) {
                    $fileArray = \CFile::MakeFileArray($imgUrl);
                    if ($fileArray) {
                        $fileArray["MODULE_ID"] = "iblock";
                    }
                }
                
                // Основные свойства
                $arProps = [];
                if (isset($p->props)) {
                    // Характеристики товара
                    $specification = [];
                    foreach ($p->props->children() as $propName => $propVal) {
                        $propValue = (string)$propVal;
                        if (!empty($propValue)) {
                            $specification[] = [
                                "NAME" => (string)$propName,
                                "VALUE" => $propValue
                            ];
                        }
                    }
                    
                    if (!empty($specification)) {
                        $arProps["SPECIFICATIONS_TEXT"] = $specification;
                    }
                }
                
                /* 
                // ЗАКОММЕНТИРОВАННЫЙ КОД ДЛЯ ЗАГРУЗКИ ФОТО
                // Загрузка дополнительных фотографий
                $morePhoto = [];
                if (isset($p->images->image)) {
                    foreach ($p->images->image as $imgNode) {
                        $url = (string)$imgNode->src;
                        if ($url) {
                            $f = \CFile::MakeFileArray($url);
                            $f["MODULE_ID"] = "iblock";
                            $morePhoto[] = $f;
                        }
                    }
                }
                if (!empty($morePhoto)) {
                    $arProps["MORE_PHOTO"] = $morePhoto;
                }
                */

                /*
                // ЗАКОММЕНТИРОВАННЫЙ КОД ДЛЯ ЗАГРУЗКИ ДОКУМЕНТОВ
                // Загрузка документов
                if (isset($p->docs->doc)) {
                    $docFiles = [];
                    foreach ($p->docs->doc as $doc) {
                        $docUrl = (string)$doc->url;
                        $docDescription = (string)$doc->name;
                        
                        if ($docUrl) {
                            $docFile = downloadFile($docUrl, $docDescription);
                            if ($docFile) {
                                $docFiles[] = $docFile;
                            }
                        }
                    }
                    if (!empty($docFiles)) {
                        $arProps["DOCS"] = $docFiles;
                    }
                }
                
                // Загрузка сертификатов
                if (isset($p->certificates->cert)) {
                    $certFiles = [];
                    foreach ($p->certificates->cert as $cert) {
                        $certUrl = (string)$cert->url;
                        $certDescription = (string)$cert->name;
                        
                        if ($certUrl) {
                            $certFile = downloadFile($certUrl, $certDescription);
                            if ($certFile) {
                                $certFiles[] = $certFile;
                            }
                        }
                    }
                    if (!empty($certFiles)) {
                        $arProps["CERTIFICATE"] = $certFiles;
                    }
                }
                */

                // Массив для загрузки/обновления товара
                $arLoad = [
                    "IBLOCK_ID"         => $iblockId,
                    "XML_ID"            => $xmlId,
                    "NAME"              => $name,
                    "CODE"              => $xmlId,
                    "ACTIVE"            => "Y",
                    "IBLOCK_SECTION_ID" => $sectionId,
                    "PREVIEW_TEXT"      => $previewText,
                    "DETAIL_TEXT"       => $detailText,
                ];
                
                // Добавляем изображения только если они есть
                if (!empty($fileArray)) {
                    $arLoad["PREVIEW_PICTURE"] = $fileArray;
                    $arLoad["DETAIL_PICTURE"] = $fileArray;
                }
                
                // Проверяем существование элемента
                $resE = CIBlockElement::GetList(
                    [], 
                    ["IBLOCK_ID" => $iblockId, "XML_ID" => $xmlId], 
                    false, 
                    false, 
                    ["ID"]
                )->Fetch();

                if($resE) {
                    // Обновление существующего элемента
                    $current = CIBlockElement::GetByID($resE["ID"])->GetNext();
                    if ($current && $current["DETAIL_PICTURE"] && !empty($imgUrl)) {
                        $existingFile = CFile::GetByID($current["DETAIL_PICTURE"])->Fetch();
                        
                        if ($existingFile["ORIGINAL_NAME"] === basename($imgUrl)) {
                            unset($arLoad["PREVIEW_PICTURE"], $arLoad["DETAIL_PICTURE"]);
                        }
                    }
                    
                    // Если у нас есть свойства, добавляем их
                    if(!empty($arProps)) {
                        $arLoad["PROPERTY_VALUES"] = $arProps;
                    }
                   
                    if(!$el->Update($resE["ID"], $arLoad)) {
                        trigger_error(
                            "Ошибка обновления товара {$name}: ".$el->LAST_ERROR,
                            E_USER_WARNING
                        );
                    } else {
                        if(!empty($arProps)) {
                            CIBlockElement::SetPropertyValuesEx($resE["ID"], $iblockId, $arProps);
                        }
                    }
                } else {
                    // Добавление нового элемента
                    if(!empty($arProps)) {
                        $arLoad["PROPERTY_VALUES"] = $arProps;
                    }
                    
                    $newElementId = $el->Add($arLoad);
                    if(!$newElementId) {
                        trigger_error(
                            "Ошибка добавления товара {$name}: ".$el->LAST_ERROR,
                            E_USER_WARNING
                        );
                    } else {
                        if (!empty($arProps)) {
                            CIBlockElement::SetPropertyValuesEx($newElementId, $iblockId, $arProps);
                        }
                    }
                }
            }
        }
    }
}

// Запускаем
importSections($xml->categories->category);
importProducts();

echo "Импорт завершён.";
