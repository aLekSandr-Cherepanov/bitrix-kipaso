<?php

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionElementTable;

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $resolved = realpath(__DIR__ . '/../..');
    if ($resolved === false || !is_dir($resolved)) {
        exit("Не удалось определить DOCUMENT_ROOT (realpath вернул некорректный путь)\n");
    }
    $docRoot = $resolved;
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    exit("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}
require $prologPath;

if(!Loader::includeModule('iblock')) {
    exit("Ошибка: не удалось подключить модуль iblock\n");
}

if(!Loader::includeModule('catalog')) {
    exit("Ошибка: не удалось подключить модуль catalog\n");
}


$xmlPath = $docRoot . '/catalogOven.xml';
if (!file_exists($xmlPath)) {
    exit("Не найден файл catalogOven.xml по пути: {$xmlPath}\n");
}

$xml = simplexml_load_file($xmlPath);
if ($xml === false) {
    exit("Ошибка при загрузке XML файла: {$xmlPath}\n");
}

$blockId = 111;
$productId = [];

$res = SectionElementTable::getList([
    'filter' => [
        '=IBLOCK_SECTION_ID' => $blockId,
    ],
    'select' => ['IBLOCK_ELEMENT_ID'],
    'limit' => 5, // ограничение на количество товаров для выборки
]);

while ($item = $res->fetch()) {
    $productId[] = $item['IBLOCK_ELEMENT_ID'];
}

$nameProduct = [];
$activeProducts = ElementTable::getList([
    'filter' => [
        '=ID' => $productId,
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID', 'NAME', 'CODE'],
]);

while ($product = $activeProducts->fetch()) {
    $nameProduct[] = $product;
}


$xmlProducts = [];

foreach ($xml->xpath('//product') as $xmlProduct) {
    $productCode = trim((string)$xmlProduct->id);
    $productName = trim((string)$xmlProduct->name);

    $xmlProducts[$productCode] = $productName;
}


$el = new CIBlockElement;

foreach ($nameProduct as $product) {
    $code = $product['CODE'];
    if (isset($xmlProducts[$code])) {
        $newName = $xmlProducts[$code];
        if(!$el->Update($product['ID'], ['NAME' => $newName])) {
            echo "Ошибка при обновлении товара ID {$product['ID']}: " . $el->LAST_ERROR . "\n";
        } else {
            echo "Товар ID {$product['ID']} успешно обновлен. Новое имя: {$newName}\n";
        }
    } 
}




print_r($nameProduct);