<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;

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

if (!Loader::includeModule('iblock')) {
    exit("Ошибка: не удалось подключить модуль iblock\n");
}

if (!Loader::includeModule('catalog')) {
    exit("Ошибка: не удалось подключить модуль catalog\n");
}

$iblockId = 16; // ID инфоблока с товарами

$xmlPath = $docRoot . '/catalogOven.xml';
if (!file_exists($xmlPath)) {
    exit("Не найден файл XML по пути: {$xmlPath}\n");
}

$xml = @simplexml_load_file($xmlPath);
if ($xml === false) {
    exit("Не удалось загрузить XML файл: {$xmlPath}\n");
}

$supplierByArticle = [];

$productNodes = $xml->xpath('//product[prices/price/izd_code]');

if (!$productNodes) {
    exit("В catalogOven.xml не найдены <product> с prices/price/izd_code. Проверь структуру.\n");
}

foreach ($productNodes as $productNode) {

    $desc  = (string)$productNode->desc;   // CDATA HTML
    $specs = (string)$productNode->specs;  // CDATA HTML
    $docs  = $productNode->docs;           // узел, позже решим формат

    // проход по модификациям
    foreach ($productNode->prices->price as $priceNode) {
        $izdCode  = trim((string)$priceNode->izd_code);
        if ($izdCode === '') continue;

        $priceVal = trim((string)$priceNode->price);

        $supplierByArticle[$izdCode] = [
            'price' => $priceVal,
            'desc'  => $desc,
            'specs' => $specs,
            'docs'  => $docs, // пока SimpleXMLElement
            // опционально: имя модификации из catalogOven
            // 'mod_name' => trim((string)$priceNode->name),
        ];
    }
}

echo "catalogOven.xml: собрано модификаций по izd_code = " . count($supplierByArticle) . PHP_EOL;

// (необязательно) Быстрый тест: вывести 3 ключа
$keys = array_slice(array_keys($supplierByArticle), 0, 3);
echo "Примеры izd_code: " . implode(', ', $keys) . PHP_EOL;