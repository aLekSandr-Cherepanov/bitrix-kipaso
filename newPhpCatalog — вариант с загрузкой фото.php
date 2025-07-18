<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3) Включаем логирование
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'].'/upload/owen_import_log.txt');
ini_set('log_errors', 1);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if(!\Bitrix\Main\Loader::includeModule("iblock")) {
    die("Не удалось подключить модуль iblock");
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
                trigger_error("Ошибка создания раздела $name: ".$secObj->LAST_ERROR, E_USER_WARNING);
                continue;
            }
        }

        
        if(isset($node->items->item)) {
            importSections($node->items->item, $sectionId);
        }
    }
}

function importProducts() {
    global $xml, $iblockId;
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
                $previewText= (string)$p->desc;   
                $imgUrl     = (string)$p->image;

                // 3) Загружаем картинку
                $fileArray = \CFile::MakeFileArray($imgUrl);
                $fileArray["MODULE_ID"] = "iblock";

                // допфото
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


                $arLoad = [
                    "IBLOCK_ID"         => $iblockId,
                    "XML_ID"            => $xmlId,
                    "NAME"              => $name,
                    "CODE"              => $xmlId,
                    "ACTIVE"            => "Y",
                    "IBLOCK_SECTION_ID" => $sectionId,
                    "PREVIEW_TEXT"      => $previewText,
                    "PREVIEW_PICTURE"   => $fileArray,
                    "DETAIL_PICTURE"    => $fileArray,
                    "PROPERTY_VALUES"   => [
                        "MORE_PHOTO"    => $morePhoto
                    ],
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
                    }
                } else {
                   
                    if(!$el->Add($arLoad)) {
                        trigger_error(
                            "Ошибка добавления товара {$name}: ".$el->LAST_ERROR,
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