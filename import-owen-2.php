<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
$prolog = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
if(!file_exists($prolog)) die("Не найден prolog: $prolog");
require_once $prolog;

echo "=== START IMPORT ===<br>";

// подключаем модуль iblock
CModule::IncludeModule('iblock');

// проверяем инфоблок
$ib = CIBlock::GetList([], ['CODE'=>'owen_products'])->Fetch();
if(!$ib) die("Инфоблок owen_products не найден");
$iblockId = $ib['ID'];

// проверяем JSON
$jsonFile = $_SERVER['DOCUMENT_ROOT'].'/upload/json/jsonOwen.json';
echo "JSON path: $jsonFile<br>";
if(!file_exists($jsonFile)) die("Файл не найден по указанному пути.");
$json = file_get_contents($jsonFile);
$data = json_decode($json, true);
if(json_last_error() !== JSON_ERROR_NONE){
    die("Ошибка JSON: ".json_last_error_msg());
}
echo "Найдено элементов: ".count($data)."<br><br>";

// импорт
$el = new CIBlockElement;
foreach($data as $i => $item){
    echo "[$i] id={$item['id']}… ";
    $fields = [
        'IBLOCK_ID' => $iblockId,
        'NAME'      => $item['name'] ?: 'Без названия',
        'XML_ID'    => $item['id'],
        // … остальные поля …
    ];
    $newId = $el->Add($fields);
    if($newId){
        echo "OK (ID=$newId)<br>";
    } else {
        echo "ERR: ".$el->LAST_ERROR."<br>";
    }
}
echo "<br>=== END IMPORT ===";
