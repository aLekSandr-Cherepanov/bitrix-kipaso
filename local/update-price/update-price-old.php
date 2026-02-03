<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\GroupTable;

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'); //пытаемся определить DOCUMENT_ROOT ,если его нет то ищем сами
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../../..');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    exit("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}
require $prologPath;

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    exit("Не удалось подключить модуль iblock\n");
}


$xmlPath = $docRoot . '/catalogOven.xml';

if (!file_exists($xmlPath)) {
    exit("Не найден файл XML по пути: {$xmlPath}\n");
}

$xml = @simplexml_load_file($xmlPath);

if ($xml === false) {
    exit("Не удалось загрузить XML файл: {$xmlPath}\n");
}

$pricesByIzd  = []; // массив с ценами из XML, ключ - izd_code, значение - цена

foreach ($xml->xpath('//price[izd_code]') as $priceNode) {
    $izdCode = trim((string)$priceNode->izd_code);
    $priceStr = trim((string)$priceNode->price);

    //пропустим если что то не так
    if ($izdCode === '' || $priceStr === '') {
        continue;
    }

    //преобразуем цену к float убираем пробелы и заменяем запятую на точку
    $price = (float)str_replace([' ', ','], ['', '.'], $priceStr);

    //сохраняем в массив
    $pricesByIzd[$izdCode] = $price;
}

echo "Всего цен в XML: " . count($pricesByIzd) . "\n";

function findElementIdByCode(string $code, int $iblockId): ?int
{
    $row = ElementTable::getList([
        'filter' => [
            '=IBLOCK_ID' => $iblockId,
            '=CODE' => $code,
            'ACTIVE' => 'Y',
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    return $row ? (int)$row['ID'] : null;
    
}


$targetIblockId = 17;

function getBasePriceTypeId(): int
{
    static $baseId = null;
    if ($baseId !== null) {
        return $baseId;
    }

    $row = GroupTable::getList([
        'filter' => [
            '=BASE' => 'Y'
        ],
        'select' => ['ID'],
        'limit'  => 1,
    ])->fetch();

    $baseId = $row ? (int)$row['ID'] : 1;

    return $baseId;
}

function upsertBasePrice (int $productId, float $price, string $currency = 'RUB'): void
{
    $baseType = getBasePriceTypeId();

     // Ищем существующую цену
    $existing = PriceTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=CATALOG_GROUP_ID' => $baseType,
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    if ($existing) {
        // Обновляем
        PriceTable::update($existing['ID'], [
            'PRICE' => $price,
            'CURRENCY' => $currency,
        ]);
    } else {
        PriceTable::add([
            'PRODUCT_ID' => $productId,
            'CATALOG_GROUP_ID' => $baseType,
            'PRICE' => $price,
            'CURRENCY' => $currency,
        ]);
    }
}

$updated = 0;
$missed  = 0;

foreach ($pricesByIzd as $izd => $price) {
    $elementId = findElementIdByCode($izd, $targetIblockId);
    if (!$elementId) {
        $missed++;
        continue;
    } 
    upsertBasePrice($elementId, $price);
    $updated++;
}

echo "Итог: обновлено {$updated}, не найдено элементов: {$missed}\n";