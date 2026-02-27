<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

if ($docRoot === '') {
    $resolved = realpath(__DIR__ . '/../../..');
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

    $desc  = (string)$productNode->desc;  
    $specs = (string)$productNode->specs;  
    $docs  = $productNode->docs;          

    // проход по модификациям
    foreach ($productNode->prices->price as $priceNode) {
        $izdCode  = trim((string)$priceNode->izd_code);
        if ($izdCode === '') continue;

        $priceVal = trim((string)$priceNode->price);

        $supplierByArticle[$izdCode] = [
            'price' => $priceVal,
            'desc'  => $desc,
            'specs' => $specs,
            'docs'  => $docs,
        ];
    }
}

// второй xml с базой для сравнения
$xmlPathBase = $docRoot . '/local/owenkomplekt_izmeriteli_regulyatory.xml';

if (!file_exists($xmlPathBase)) {
    exit("Не найден файл XML по пути: {$xmlPathBase}\n");
}

$xmlBase = @simplexml_load_file($xmlPathBase);
if ($xmlBase === false) {
    exit("Не удалось загрузить XML файл: {$xmlPathBase}\n");
}

$baseByArticle = [];

$productBaseNodes = $xmlBase->xpath('//product[article]');

if (!$productBaseNodes) {
    exit("В owenkomplekt_izmeriteli_regulyatory.xml не найдены <product> с article. Проверь структуру.\n");
}

foreach ($productBaseNodes as $productBaseNode) {
    $article = trim((string)$productBaseNode->article);
    if ($article === '') continue;

    $name = trim((string)$productBaseNode->name);
    $short = trim((string)$productBaseNode->short_description);
    $imageUrl = '';
    if (isset($productBaseNode->images->image[0])) {
        $imageUrl = trim((string)$productBaseNode->images->image[0]);
    }

    $baseByArticle[$article] = [
        'name' => $name,
        'short_description' => $short,
        'image' => $imageUrl,
    ];
}


function normalizeArticleKey(string $article): string
{
    return trim($article);
}

function isNumericArticle(string $article): bool
{
    return (bool)preg_match('~^\d+$~', $article);
}

$matched = 0;
$missedNumeric = [];
$missedNonNumeric = [];
$readyByArticle = [];

foreach ($baseByArticle as $articleRaw => $base) {
    $article = normalizeArticleKey($articleRaw);

    // если артикул не числовой — пропускаем (в catalogOven izd_code числовые)
    if (!isNumericArticle($article)) {
        $missedNonNumeric[] = $articleRaw;
        continue;
    }

    // строгое совпадение "цифра в цифру"
    if (!isset($supplierByArticle[$article])) {
        $missedNumeric[] = $articleRaw;
        continue;
    }

    $supplier = $supplierByArticle[$article];

    // DETAIL_TEXT: short_description -> desc
    $short = trim($base['short_description'] ?? '');
    $desc  = trim($supplier['desc'] ?? '');

    $detailHtml = '';
    if ($short !== '') $detailHtml .= '<div class="seo-short">' . $short . '</div>' . PHP_EOL;
    if ($desc  !== '') $detailHtml .= '<div class="seo-desc">' . $desc . '</div>' . PHP_EOL;

    $readyByArticle[$article] = [
        'name'                => $base['name'] ?? '',
        'xml_id'              => (string)$article,
        'detail_html'         => $detailHtml,
        'specifications_html' => (string)($supplier['specs'] ?? ''),
        'price'               => (string)($supplier['price'] ?? ''),
        'docs'                => $supplier['docs'] ?? null,
        'image_url'           => $base['image'] ?? '',
    ];

    $matched++;
}

echo "совпадение: {$matched}\n";
echo "пропущенное числовое значение: " . count($missedNumeric) . "\n";
echo "пропущено нечисловое значение: " . count($missedNonNumeric) . "\n";

if (!empty($missedNumeric)) {
    echo "Примеры числовое значение без совпадения: " . implode(', ', array_slice($missedNumeric, 0, 20)) . "\n";
}
if (!empty($missedNonNumeric)) {
    echo "Примеры нечисловое значение (пропущены): " . implode(', ', array_slice($missedNonNumeric, 0, 20)) . "\n";
}




echo "owenkomplekt.xml: собрано товаров по article = " . count($baseByArticle) . PHP_EOL;

$keys2 = array_slice(array_keys($baseByArticle), 0, 3);
echo "Примеры article: " . implode(', ', $keys2) . PHP_EOL;