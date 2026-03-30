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
    exit;
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

$sectionId = 111; // ID раздела "Измерители-регуляторы"
$productIds = [];

$res = SectionElementTable::getList([
    'filter' => [
        '=IBLOCK_SECTION_ID' => $sectionId,

    ], 
    'select' => ['IBLOCK_ELEMENT_ID'],
    'order' => ['IBLOCK_ELEMENT_ID' => 'ASC'],
    'limit' => 5,
]);

while ($item = $res->fetch()) {
    $productIds[] = $item['IBLOCK_ELEMENT_ID'];
}

// Проверяем, были ли найдены товары в разделе
if(empty($productIds)) {
    echo "В разделе не найдено товаров.\n";
    exit;
}


$products = [];
$activeProduct = ElementTable::getList([
    'filter' => [
        '=ID' => $productIds,
    ], 
    'select' => ['ID', 'NAME', 'CODE'],
]);

while ($item = $activeProduct->fetch()) {
    $products[] = $item;
}

$xmlProducts = [];
foreach ($xml->xpath('//product') as $xmlProduct) {
    $productId = trim((string)$xmlProduct->id);
    $productName = trim((string)$xmlProduct->name);

    $xmlProducts[$productId] = $productName;
}

$el = new CIBlockElement;

foreach ($products as $product) {
    $code = $product['CODE'];
    if (isset($xmlProducts[$code])) { // есть ли в массиве элемент с ключом равным символьномму коду товара
        $newName = $xmlProducts[$code];// берем имя из xml по ключу равному символьному коду товара
        if(!$el->Update($product['ID'], ['NAME' => $newName])) {
            echo "Ошибка при обновлении товара CODE {$product['CODE']}: " . $el->LAST_ERROR . "\n";
        } else {
            echo "Товар CODE {$product['CODE']} успешно обновлен. Новое имя: {$newName}\n";
        }
    }
}

$pricesByIzd  = []; // массив с ценами из XML, ключ - izd_code, значение - цена

// Проходим по всем узлам <price> в XML и извлекаем код изделия и цену и складываем в массив $pricesByIzd
foreach ($xml->xpath('//price') as $priceNode) {
    $izdCode = trim((string)$priceNode->izd_code);
    $priceStr = trim((string)$priceNode->price);

    if ($izdCode === '' || $priceStr === '') {
        continue;
    }

    $price = (float)str_replace([' ', ','], ['', '.'], $priceStr);

    $pricesByIzd[$izdCode] = $price;
}

print_r($products);