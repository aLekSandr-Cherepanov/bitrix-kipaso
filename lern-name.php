<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$docRoot = rtrim($docRoot, '/');

if ($docRoot === '') {
    $docRoot = realpath(__DIR__);
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

$productIdsInXml = []; // массив для хранения ID товаров из XML
$limit = 50; // ограничение на количество товаров для выборки

foreach ($xml->xpath('//products/product[id]') as $productNode) { // проходим по каждому узлу product, у которого есть id
    $idProduct = trim((string)$productNode->id);
    if ($idProduct === '') {
        continue; // пропускаем, если ID пустой
    }

    $productIdsInXml[] = $idProduct;
    
    if (count($productIdsInXml) >= $limit) {
        break; // остановка, если достигнуто ограничение
    }
}



$iblockid = 16; // ID инфоблока с товарами

$res = ElementTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $iblockid,
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID', 'NAME'],
    'order' => ['ID' => 'ASC'],
    'limit' => 50,
]);

$items = [];

while ($item = $res->fetch()) {
    $items[] = $item;
}
print_r($items);

