<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3) Включаем расширенное логирование
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'].'/upload/owen_import_log.txt');
ini_set('log_errors', 1);

// Убираем лимит времени 
// На большинстве хостингов эти настройки не работают, поэтому делаем пошаговый импорт
ini_set('max_execution_time', 300); // Пытаемся увеличить до 5 минут
set_time_limit(300); // То же самое, дублируем для надежности
ini_set('memory_limit', '1024M'); 

// Параметры пошагового импорта
$step_size = 10; // Количество товаров для обработки за один запуск
$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

// Путь для сохранения прогресса
$progress_file = $_SERVER['DOCUMENT_ROOT'] . '/upload/import_progress.txt';

// Функция для сохранения прогресса
function saveProgress($step, $totalProducts, $processed) {
    global $progress_file;
    $data = json_encode([
        'step' => $step,
        'total' => $totalProducts,
        'processed' => $processed,
        'timestamp' => time()
    ]);
    file_put_contents($progress_file, $data);
}

// Функция для загрузки прогресса
function loadProgress() {
    global $progress_file;
    if (file_exists($progress_file)) {
        $data = file_get_contents($progress_file);
        return json_decode($data, true);
    }
    return null;
}

// Убираем лимит времени 
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

function downloadFile($url, $description = '') {
    global $docDir, $iblockId;
    
    // Получаем имя файла из URL
    $fileName = basename($url);
    $localPath = $docDir . $fileName;
    
    // Сначала проверяем, есть ли такой файл уже в базе Битрикс
    $existingFile = false;
    
    // Ищем файл в базе по имени
    $rsFiles = CFile::GetList([], ["ORIGINAL_NAME" => $fileName]);
    if ($fileItem = $rsFiles->Fetch()) {
        error_log("Файл уже существует в базе Битрикс: {$fileName}, ID: {$fileItem['ID']}");
        
        // Используем существующий файл
        $existingFile = CFile::GetFileArray($fileItem['ID']);
        if ($existingFile) {
            $fileArray = [
                'name' => $existingFile['FILE_NAME'],
                'size' => $existingFile['FILE_SIZE'],
                'tmp_name' => $existingFile['SRC'],
                'type' => $existingFile['CONTENT_TYPE'],
                'old_file' => $fileItem['ID'],
                'MODULE_ID' => 'iblock'
            ];
            
            if (!empty($description)) {
                $fileArray['description'] = $description;
            } else {
                $fileArray['description'] = $fileName;
            }
            
            return $fileArray;
        }
    }
    
    // Если файл существует локально, но не в базе
    if (file_exists($localPath)) {
        error_log("Файл уже существует локально: {$fileName}, используем локальную копию");
        
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
    
    global $xml, $iblockId, $docDir, $step_size, $current_step, $progress_file;
    $el = new CIBlockElement;
    
    // Загружаем прогресс, если есть
    $progress = loadProgress();
    $processedCount = $progress ? $progress['processed'] : 0;
    
    // Получаем общее количество товаров
    $totalProducts = 0;
    foreach($xml->categories->category as $cat) {
        foreach($cat->items->item as $sub) {
            foreach($sub->products->product as $p) {
                $totalProducts++;
            }
        }
    }
    
    // Если уже все обработано, сообщаем об этом
    if ($processedCount >= $totalProducts) {
        echo "<div style='background: #dff0d8; padding: 15px; border-radius: 5px;'>
            <h2>Импорт завершен!</h2>
            <p>Всего обработано товаров: {$processedCount} из {$totalProducts}</p>
            <p><a href='/newPhpCatalog.php?reset=1' class='btn btn-warning'>Начать импорт заново</a></p>
        </div>";
        return;
    }
    
    // Если пользователь запросил сброс прогресса
    if (isset($_GET['reset']) && $_GET['reset'] == 1) {
        if (file_exists($progress_file)) {
            unlink($progress_file);
        }
        $current_step = 0;
        $processedCount = 0;
    }

    // Счетчики для пошаговой обработки
    $currentProductIndex = 0;
    $processed_this_run = 0;
    $start_index = $current_step * $step_size;
    $max_index = $start_index + $step_size;
    
    // Выводим информацию о прогрессе
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
        <h2>Процесс импорта</h2>
        <p>Всего товаров: {$totalProducts}</p>
        <p>Обработано: {$processedCount}</p>
        <p>Текущий шаг: {$current_step}</p>
        <div class='progress' style='height: 20px; background-color: #f5f5f5; border-radius: 4px; margin-bottom: 20px;'>
            <div class='progress-bar' role='progressbar' style='width: " . (($processedCount / $totalProducts) * 100) . "%; background-color: #5bc0de; height: 100%; border-radius: 4px;'></div>
        </div>
    </div>";

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
                // Пропускаем товары, которые не входят в текущий шаг
                if ($currentProductIndex < $start_index) {
                    $currentProductIndex++;
                    continue;
                }
                
                // Останавливаемся, если достигли максимума для текущего шага
                if ($currentProductIndex >= $max_index) {
                    break 3; // Выходим из всех циклов
                }
                
                $currentProductIndex++;
                $processed_this_run++;
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

                
                $arProps = [];
                
                // Обработка документов и сертификатов
                if(isset($p->docs)) {
                    
                    $docsArray = [];
                    $certsArray = [];
                    
                    // Обрабатываем каждую группу документов
                    foreach($p->docs->doc as $docGroup) {
                        $groupName = (string)$docGroup->name;
                        
                        if(isset($docGroup->items)) {
                            foreach($docGroup->items->item as $docItem) {
                                $docName = (string)$docItem->name;
                                $docLink = (string)$docItem->link;
                                
                                // Проверяем валидность URL перед загрузкой
                                if (filter_var($docLink, FILTER_VALIDATE_URL)) {
                                    // Загружаем файл с проверкой существования
                                    $fileArray = downloadFile($docLink, $docName);
                                    
                                    if ($fileArray) {
                                        // Распределяем файл по категориям в зависимости от группы
                                        $groupNameLower = mb_strtolower($groupName);
                                        if($groupNameLower === 'документация') {
                                            $docsArray[] = $fileArray;
                                        } elseif($groupNameLower === 'сертификаты') {
                                            $certsArray[] = $fileArray;
                                        }
                                    }
                                } else {
                                    error_log("Некорректный URL документа: {$docLink} для {$docName}");
                                }
                            }
                        }
                    }
                    
                    // Добавляем документацию в свойства товара
                    if(!empty($docsArray)) {
                        $arProps['DOCS'] = $docsArray;
                    }
                    
                    // Добавляем сертификаты в свойства товара
                    if(!empty($certsArray)) {
                        $arProps['CERTIFICATE'] = $certsArray;
                    }
                }

                // Добавляем свойства из $propertyValues в $arProps
                if (!empty($propertyValues)) {
                    $arProps = array_merge($arProps, $propertyValues);
                }

                // Делаем финальную проверку данных перед подготовкой $arLoad
                // Явно проверяем наличие характеристик в свойствах
                if (isset($arProps['SPECIFICATIONS_TEXT'])) {
                } 
                
                // Ещё одна проверка для уверенности - добавляем характеристики напрямую в $arLoad
                $propertyValuesForLoad = $arProps;
                
                // Добавляем SPECIFICATIONS_TEXT напрямую, если он не был добавлен ранее
                if (!empty($propertyValues['SPECIFICATIONS_TEXT']) && !isset($propertyValuesForLoad['SPECIFICATIONS_TEXT'])) {
                    $propertyValuesForLoad['SPECIFICATIONS_TEXT'] = $propertyValues['SPECIFICATIONS_TEXT'];
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
                            
                            CIBlockElement::SetPropertyValuesEx($resE["ID"], $iblockId, $arProps);
                            
                            
                            
                           
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "DOCS"]);
                            while($prop = $dbProps->Fetch()) {
                             
                                if($prop["VALUE"]) {
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                } else {
                                }
                            }
                            
                            
                            $dbProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "CERTIFICATE"]);
                            while($prop = $dbProps->Fetch()) {
                                
                                if($prop["VALUE"]) {
                                    $fileInfo = CFile::GetByID($prop["VALUE"])->Fetch();
                                } else {
                                }
                            }
                            
                            // Проверяем сохранение свойства SPECIFICATIONS_TEXT
                            $dbSpecsProps = CIBlockElement::GetProperty($iblockId, $resE["ID"], [], ["CODE" => "SPECIFICATIONS_TEXT"]);
                            while($specProp = $dbSpecsProps->Fetch()) {
                                if (is_array($specProp["VALUE"])) {
                                } else {
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
                        
                        // Если у нас есть свойства, устанавливаем их и для новых элементов
                        if (!empty($arProps)) {
                            
                            // Устанавливаем свойства для нового элемента
                            CIBlockElement::SetPropertyValuesEx($newElementId, $iblockId, $arProps);
                            
                            // Проверяем сохранение свойства SPECIFICATIONS_TEXT для нового элемента
                            $dbSpecsProps = CIBlockElement::GetProperty($iblockId, $newElementId, [], ["CODE" => "SPECIFICATIONS_TEXT"]);
                            while($specProp = $dbSpecsProps->Fetch()) {
                                if (is_array($specProp["VALUE"])) {
                                } else {
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Увеличиваем счетчик обработанных товаров
    $processedCount++;
    
    // Сохраняем прогресс
    $new_step = $current_step + 1;
    saveProgress($new_step, $totalProducts, $processedCount);
    
    // Проверяем, все ли товары обработаны
    if ($processedCount >= $totalProducts) {
        echo "<div style='background: #dff0d8; padding: 15px; border-radius: 5px; margin-top: 20px;'>
            <h2>Импорт успешно завершен!</h2>
            <p>Всего обработано товаров: {$processedCount} из {$totalProducts}</p>
            <p><a href='/newPhpCatalog.php?reset=1' class='btn btn-primary'>Запустить импорт заново</a></p>
        </div>";
    } else {
        // Показываем ссылку на следующий шаг
        $next_url = $_SERVER['PHP_SELF'] . '?step=' . $new_step;
        echo "<div style='margin-top: 20px;'>
            <h3>Обработано товаров в текущем шаге: {$processed_this_run}</h3>
            <p>Всего обработано: {$processedCount} из {$totalProducts} (" . round(($processedCount/$totalProducts)*100, 1) . "%)</p>
            <p>
                <a href='{$next_url}' class='btn btn-success' style='background-color: #5cb85c; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Продолжить импорт (Шаг {$new_step})
                </a>
            </p>
            <script>
                // Автоматический переход к следующему шагу через 2 секунды
                setTimeout(function() {
                    window.location.href = '{$next_url}';
                }, 2000);
            </script>
        </div>";
    }
}


// 4. Запускаем

// Если это первый запуск, сначала создаем разделы
if ($current_step === 0 || isset($_GET['reset'])) {
    // Создаем разделы
    importSections($xml->categories->category);
    echo "<div style='background: #d9edf7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
        <h3>Разделы успешно созданы</h3>
        <p>Начинаем импорт товаров...</p>
    </div>";
}

// Запускаем импорт товаров
importProducts();

// Дополнительные стили для красивого отображения
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .btn { display: inline-block; padding: 10px 15px; text-decoration: none; border-radius: 4px; }
    .btn-primary { background-color: #337ab7; color: white; }
    .btn-warning { background-color: #f0ad4e; color: white; }
</style>";

// Конец скрипта