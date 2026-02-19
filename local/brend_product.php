<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\SectionElementTable;


$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if($docRoot === '') {
    $resolved = realpath(__DIR__ . '/..');
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

if (!Loader::includeModule('iblock')) {
    exit("Ошибка: не удалось подключить модуль iblock\n");
}

if (!Loader::includeModule('catalog')) {
    exit("Ошибка: не удалось подключить модуль catalog\n");
}

$iblockIdProducts = 16; // ID инфоблока с товарами
$rootSectionId = 110; // ID инфоблока с разделами

// 1 - получаем границы дерева, то есть значения LEFT_MARGIN и RIGHT_MARGIN для раздела 110, чтобы потом получить все разделы внутри этой ветки
$rootSection = SectionTable::getRow([ // Получаем корневую категорию по ID
    'filter' => [
        '=IBLOCK_ID' => $iblockIdProducts,
        '=ID' => $rootSectionId,
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'],
]);

if (!$rootSection) {
    exit("Ошибка: раздел ID={$rootSectionId} не найден или неактивен\n");
}

// 2 - Получаем ID всех разделов внутри ветки 110 (включая сам 110)

$sectionIds = [];
$sectionRes = SectionTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $iblockIdProducts,
        '>=LEFT_MARGIN' => (int)$rootSection['LEFT_MARGIN'],
        '<=RIGHT_MARGIN' => (int)$rootSection['RIGHT_MARGIN'],
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID'],
    'order' => ['LEFT_MARGIN' => 'ASC'],
]);

while ($row = $sectionRes->fetch()) {
    $sectionIds[] = (int)$row['ID'];
}

// 3 - Получаем ID товаров, которые привязаны к разделам из пункта 2 
$productsIds = [];
$linksRes = SectionElementTable::getList([
    'filter' => [
        '=IBLOCK_SECTION_ID' => $sectionIds,
    ],
    'select' => ['IBLOCK_ELEMENT_ID'],
    'group' => ['IBLOCK_ELEMENT_ID'], // группируем по ID товара, чтобы получить уникальные ID
]);

while ($link = $linksRes->fetch()) {
    $productsIds[] = (int)$link['IBLOCK_ELEMENT_ID'];
}

if(empty($productsIds)) {
    exit("Ошибка: в ветке раздела ID={$rootSectionId} нет привязанных товаров\n");
}

// 4 - Фильтруем только активные товары нужного инфоблока

$activeProducts = [];
$productsRes = ElementTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $iblockIdProducts,
        '=ID' => $productsIds,
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID', 'NAME'],
    'order' => ['ID' => 'ASC'],
    'limit' => 10,
]);

while ($p = $productsRes->fetch()) {
    $activeProducts[] = [
        'ID' => (int)$p['ID'],
        'NAME' => (string)$p['NAME'],
    ];
}

if(empty($activeProducts)) {
    exit("После фильтра ACTIVE=Y товары не найдены\n");
}


$brandElementId = 55675;
$brandPropertyCode = 'ATT_BRAND';

$updated = 0;
$errors = [];

foreach ($activeProducts as $product) {
    $productId = (int)$product['ID'];
    $productName = (string)$product['NAME'];

    \CIBlockElement::SetPropertyValuesEx(
        $productId,
        $iblockIdProducts,
        [$brandPropertyCode => (int)$brandElementId]
    );

    $updated++;
    echo "OK: product_id={$productId} | {$productName} -> {$brandPropertyCode}={$brandElementId}\n";
}

echo "Готово. Обновлено: {$updated}, ошибок: " . count($errors) . "\n";
exit;
