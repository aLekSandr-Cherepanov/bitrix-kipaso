<?php

use Bitrix\Main\Loader;
use Bitrix\Catalog\PriceTable;
use Bitrix\Iblock\SectionElementTable;
use Bitrix\Iblock\ElementTable;

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../../');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    echo "Не найден файл prolog_before.php по пути: {$prologPath}\n";
}

require_once $prologPath;

if (!Loader::includeModule('catalog')) {
    echo "Не удалось загрузить модуль 'catalog'.\n";
    exit;
}

if (!Loader::includeModule('iblock')) {
    echo "Не удалось загрузить модуль 'iblock'.\n";
    exit;
}

$xmlFilePath = $docRoot . '/catalogOven.xml';

if (!file_exists($xmlFilePath)) {
    echo "Не найден файл XML по пути: {$xmlFilePath}\n";
    exit;
}

$xml = simplexml_load_file($xmlFilePath);

if ($xml === false) {
    echo "Ошибка при загрузке XML файла: {$xmlFilePath}\n";
    exit;
}   

$sectionId = 111; // ID инфоблока, в котором находятся товары
$productId = [];

$res = SectionElementTable::getList([
    'filter' => [
        '=IBLOCK_SECTION_ID' => $sectionId,

    ], 
    'select' => ['IBLOCK_ELEMENT_ID'],
    'limit' => 5,
]);

while ($item = $res->fetch()) {
    $productId[] = $item['IBLOCK_ELEMENT_ID'];
}


$nameProduct = [];
$activeProduct = ElementTable::getList([
    'filter' => [
        '=ID' => $productId,
    ], 
    'select' => ['ID', 'NAME'],
]);

while ($item = $activeProduct->fetch()) {
    $nameProduct[] = [
    'ID' => $item['ID'],
    'NAME' => $item['NAME'],
];
}



print_r($nameProduct);