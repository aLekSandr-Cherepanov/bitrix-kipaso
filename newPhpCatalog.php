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

var_dump(ini_get('allow_url_fopen'));


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
    
    echo "<p>Всего товаров для обработки: {$totalProducts}</p>";

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
                echo "<br>Значение тега specs для товара '{$name}': ";
                if ($specificText !== '') {
                    echo "<pre>" . htmlspecialchars($specificText) . "</pre>";
                } else {
                    echo "<i>пусто</i><br>";
                }
                
                $imgUrl     = (string)$p->image;

                
                $fileArray = \CFile::MakeFileArray($imgUrl);
                $fileArray["MODULE_ID"] = "iblock";

                $propertyValues = [];

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
                        echo "<div style='color:green'>Обнаружены HTML-теги, сохраняем как HTML</div>";
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
                        echo "<div style='color:blue'>Простой текст преобразован в HTML с параграфами</div>";
                    }
                }

                
                $arProps = [];
                
                echo "<hr>Проверка документов для товара: {$name}<br>";
                
                /* обработка документов
                if(isset($p->docs)) {
                    echo "Секция docs существует.<br>";
                    
                    $docsCount = count($p->docs->children());
                    echo "Количество дочерних элементов в docs: {$docsCount}<br>";
                    $docsArray = [];
                    $certsArray = [];
                    
                    foreach($p->docs->children() as $childName => $child) {
                        echo "Найден дочерний элемент: {$childName}<br>";
                    }
                    
 
                    foreach($p->docs->doc as $docGroup) {
                        echo "Обработка группы документов: " . (string)$docGroup->name . "<br>";
                        $groupName = (string)$docGroup->name;
                        

                        if(isset($docGroup->items)) {
                            $itemsCount = count($docGroup->items->children());
                            echo "Количество элементов items: {$itemsCount}<br>";
                            foreach($docGroup->items->item as $docItem) {
                                $docName = (string)$docItem->name;
                                $docLink = (string)$docItem->link;
                                
                                
                                echo "Загрузка файла: {$docLink}, название: {$docName}<br>";
                                $fileArray = downloadFile($docLink, $docName);
                                
                                
                                if ($fileArray) {
                                
                                    
                                    if(mb_strtolower($groupName) === 'документация') {
                                        $docsArray[] = $fileArray;
                                    } elseif(mb_strtolower($groupName) === 'сертификаты') {
                                        $certsArray[] = $fileArray;
                                    }
                                    
                                }
                            }
                        }
                    }
                        
                    
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
                */

                // Добавляем свойства из $propertyValues в $arProps
                if (!empty($propertyValues)) {
                    $arProps = array_merge($arProps, $propertyValues);
                    echo "<pre>Добавлены характеристики из тега specs:</pre>";
                    echo "<pre>";
                    print_r($propertyValues);
                    echo "</pre>";
                }

                // Делаем финальную проверку данных перед подготовкой $arLoad
                // Явно проверяем наличие характеристик в свойствах
                if (isset($arProps['SPECIFICATIONS_TEXT'])) {
                    echo "<div style='color:green; font-weight:bold;'>Характеристики уже добавлены в свойства</div>";
                } 
                
                // Ещё одна проверка для уверенности - добавляем характеристики напрямую в $arLoad
                $propertyValuesForLoad = $arProps;
                
                // Добавляем SPECIFICATIONS_TEXT напрямую, если он не был добавлен ранее
                if (!empty($propertyValues['SPECIFICATIONS_TEXT']) && !isset($propertyValuesForLoad['SPECIFICATIONS_TEXT'])) {
                    $propertyValuesForLoad['SPECIFICATIONS_TEXT'] = $propertyValues['SPECIFICATIONS_TEXT'];
                    echo "<div style='color:purple; font-weight:bold;'>Характеристики добавлены напрямую в arLoad</div>";
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
                    "PROPERTY_VALUES"   => $propertyValuesForLoad, 
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
                        
                        if(!empty($arProps)) {
                            echo "<div style='padding: 10px; background: #f0f8ff; margin: 10px 0; border-left: 5px solid #4682b4;'>";
                            echo "Устанавливаем свойства для элемента ID: {$resE["ID"]}<br>";
                            echo "<pre style='background: #f8f8f8; padding: 5px; border: 1px solid #ddd;'>Свойства: ";
                            print_r($arProps);
                            echo "</pre>";
                            echo "</div>";
                            
                            CIBlockElement::SetPropertyValuesEx($resE["ID"], $iblockId, $arProps);
                            
                            
                            echo "Проверка сохранения свойств:<br>";
                            
                           
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "DOCS"]);
                            echo "Свойство DOCS:<br>";
                            while($prop = $dbProps->Fetch()) {
                             
                                if($prop["VALUE"]) {
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    echo "ID: {$prop["ID"]}, VALUE: {$prop["VALUE"]}, DESCRIPTION: {$prop["DESCRIPTION"]}, FILE DESCRIPTION: {$fileInfo["DESCRIPTION"]}<br>";
                                } else {
                                    echo "ID: {$prop["ID"]}, VALUE: {$prop["VALUE"]}, DESCRIPTION: {$prop["DESCRIPTION"]}<br>";
                                }
                            }
                            
                            
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "CERTIFICATE"]);
                            echo "Свойство CERTIFICATE:<br>";
                            while($prop = $dbProps->Fetch()) {
                                
                                if($prop["VALUE"]) {
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                    echo "ID: {$prop["ID"]}, VALUE: {$prop["VALUE"]}, DESCRIPTION: {$prop["DESCRIPTION"]}, FILE DESCRIPTION: {$fileInfo["DESCRIPTION"]}<br>";
                                } else {
                                    echo "ID: {$prop["ID"]}, VALUE: {$prop["VALUE"]}, DESCRIPTION: {$prop["DESCRIPTION"]}<br>";
                                }
                            }
                            
                            // Проверяем сохранение свойства SPECIFICATIONS_TEXT
                            $dbSpecsProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "SPECIFICATIONS_TEXT"]);
                            echo "<h3>Свойство SPECIFICATIONS_TEXT:</h3>";
                            while($specProp = $dbSpecsProps->Fetch()) {
                                echo "ID: {$specProp["ID"]}, VALUE: ";
                                if (is_array($specProp["VALUE"])) {
                                    echo "<pre>";
                                    print_r($specProp["VALUE"]);
                                    echo "</pre>";
                                } else {
                                    echo $specProp["VALUE"] ? htmlspecialchars($specProp["VALUE"]) : '<i>пусто</i>';
                                }
                                echo "<br>";
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
                        echo "<div style='padding: 10px; background: #f0fff0; margin: 10px 0; border-left: 5px solid #2e8b57;'>";
                        echo "Товар успешно добавлен с ID: {$newElementId}<br>";
                        
                        // Если у нас есть свойства, устанавливаем их и для новых элементов
                        if (!empty($arProps)) {
                            echo "<pre style='background: #f8f8f8; padding: 5px; border: 1px solid #ddd;'>Свойства для нового товара: ";
                            print_r($arProps);
                            echo "</pre>";
                            
                            // Устанавливаем свойства для нового элемента
                            CIBlockElement::SetPropertyValuesEx($newElementId, $iblockId, $arProps);
                            
                            // Проверяем сохранение свойства SPECIFICATIONS_TEXT для нового элемента
                            $dbSpecsProps = CIBlockElement::GetProperty($iblockId, $newElementId, [], ["CODE" => "SPECIFICATIONS_TEXT"]);
                            echo "<h3>Свойство SPECIFICATIONS_TEXT для нового элемента:</h3>";
                            while($specProp = $dbSpecsProps->Fetch()) {
                                echo "ID: {$specProp["ID"]}, VALUE: ";
                                if (is_array($specProp["VALUE"])) {
                                    echo "<pre>";
                                    print_r($specProp["VALUE"]);
                                    echo "</pre>";
                                } else {
                                    echo $specProp["VALUE"] ? htmlspecialchars($specProp["VALUE"]) : '<i>пусто</i>';
                                }
                                echo "<br>";
                            }
                        }
                        echo "</div>";
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