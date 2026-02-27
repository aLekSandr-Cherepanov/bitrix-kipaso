<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Main\Web\HttpClient;

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $resolved = realpath(__DIR__ . '/../..');
    if ($resolved === false || !is_dir($resolved)) {
        exit("Не удалось определить DOCUMENT_ROOT\n");
    }
    $docRoot = $resolved;
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';
if (!file_exists($prologPath)) {
    exit("Не найден prolog_before.php по пути: {$prologPath}\n");
}
require $prologPath;

header('Content-Type: text/plain; charset=utf-8');

if (!Loader::includeModule('iblock')) {
    exit("Не удалось подключить модуль iblock\n");
}

$run = isset($_GET['run']) ? (int)$_GET['run'] : 0;
$dryRun = isset($_GET['dry']) ? (int)$_GET['dry'] : 1;
$targetIblockId = isset($_GET['iblock']) ? (int)$_GET['iblock'] : 0;
$targetSectionId = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 5;
$updateExisting = isset($_GET['update']) ? (int)$_GET['update'] : 0;
$applyCatalogPrice = isset($_GET['price']) ? (int)$_GET['price'] : 1;

if (PHP_SAPI === 'cli') {
    global $argv;
    $args = $argv ?? [];
    if (!empty($args)) {
        array_shift($args);
    }
    foreach ($args as $a) {
        $a = trim((string)$a);
        if ($a === 'run=1' || $a === '--run') {
            $run = 1;
        } elseif ($a === 'dry=0' || $a === '--apply') {
            $dryRun = 0;
        } elseif (preg_match('/^iblock=(\d+)$/', $a, $m)) {
            $targetIblockId = (int)$m[1];
        } elseif (preg_match('/^section=(\d+)$/', $a, $m)) {
            $targetSectionId = (int)$m[1];
        } elseif (preg_match('/^limit=(\d+)$/', $a, $m)) {
            $limit = max(1, (int)$m[1]);
        } elseif ($a === 'update=1') {
            $updateExisting = 1;
        } elseif ($a === 'price=1') {
            $applyCatalogPrice = 1;
        } elseif ($a === 'price=0') {
            $applyCatalogPrice = 0;
        }
    }
}

if (!$run) {
    echo "Скрипт готов. По умолчанию dry=1 (без изменений).\n";
    echo "Запуск (через браузер): /local/scripts/import_landing_pages_from_two_xml.php?run=1&iblock=XX&dry=1\n";
    echo "Применить изменения: ...&dry=0\n";
    echo "Параметры: iblock, section (обязателен для dry=0, если авто-подбор не сработает), limit (по умолчанию 5), update=1 (разрешить обновление существующих), price=0|1 (по умолчанию 1 — писать базовую цену в каталог).\n";
    exit;
}

if ($targetIblockId <= 0) {
    exit("Не указан iblock. Пример: ?run=1&iblock=16&dry=1\n");
}

$owenkomplektXmlPath = $docRoot . '/local/owenkomplekt_izmeriteli_regulyatory.xml';
$catalogOvenXmlPath = $docRoot . '/catalogOven.xml';

$docsDir = $docRoot . '/upload/doc/owen_landing/';
if (!$dryRun && !is_dir($docsDir)) {
    @mkdir($docsDir, 0755, true);
}

$docsCacheFile = rtrim($docsDir, '/') . '/url_to_fileid.json';
$docsUrlToFileId = [];
if (!$dryRun && file_exists($docsCacheFile)) {
    $raw = @file_get_contents($docsCacheFile);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $docsUrlToFileId = $decoded;
        }
    }
}

$docsPropMultiple = true;
$docsPropIsFile = false;
$propRes = CIBlockProperty::GetList([], [
    'IBLOCK_ID' => $targetIblockId,
    '=CODE' => 'DOCS',
]);
$prop = $propRes ? $propRes->Fetch() : false;
if (is_array($prop)) {
    $docsPropMultiple = ((string)$prop['MULTIPLE'] === 'Y');
    $docsPropIsFile = ((string)$prop['PROPERTY_TYPE'] === 'F');
    if (!$docsPropIsFile) {
        echo "WARN: свойство DOCS найдено, но оно не FILE (PROPERTY_TYPE={$prop['PROPERTY_TYPE']}). DOCS пропущен.\n";
    }
} else {
    echo "WARN: не найдено свойство DOCS в инфоблоке {$targetIblockId}\n";
}

if (!file_exists($owenkomplektXmlPath)) {
    exit("Не найден файл: {$owenkomplektXmlPath}\n");
}
if (!file_exists($catalogOvenXmlPath)) {
    exit("Не найден файл: {$catalogOvenXmlPath}\n");
}

function readFirstProductsFromOwenkomplekt(string $xmlPath, int $limit): array
{
    $reader = new XMLReader();
    if (!$reader->open($xmlPath)) {
        return [];
    }

    $result = [];
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'product') {
            continue;
        }

        $xml = $reader->readOuterXML();
        if ($xml === '') {
            continue;
        }

        try {
            $node = new SimpleXMLElement($xml);
        } catch (Throwable $e) {
            continue;
        }

        $article = trim((string)$node->article);
        if ($article === '') {
            continue;
        }

        $imageUrl = '';
        if (isset($node->images) && isset($node->images->image[0])) {
            $imageUrl = trim((string)$node->images->image[0]);
        }

        $categoryName = '';
        if (isset($node->categories) && isset($node->categories->category[0]) && isset($node->categories->category[0]->name)) {
            $categoryName = trim((string)$node->categories->category[0]->name);
        }

        $result[] = [
            'name' => trim((string)$node->name),
            'article' => $article,
            'short_description' => trim((string)$node->short_description),
            'image' => $imageUrl,
            'category_name' => $categoryName,
        ];

        if (count($result) >= $limit) {
            break;
        }
    }

    $reader->close();
    return $result;
}

function loadCatalogOvenDataByIzdCodes(string $xmlPath, array $izdCodes): array
{
    $wanted = [];
    foreach ($izdCodes as $c) {
        $c = trim((string)$c);
        if ($c !== '') {
            $wanted[$c] = true;
        }
    }

    if (!$wanted) {
        return [];
    }

    $found = [];

    $reader = new XMLReader();
    if (!$reader->open($xmlPath)) {
        return [];
    }

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'product') {
            continue;
        }

        $xml = $reader->readOuterXML();
        if ($xml === '') {
            continue;
        }

        try {
            $product = new SimpleXMLElement($xml);
        } catch (Throwable $e) {
            continue;
        }

        if (!isset($product->prices) || !isset($product->prices->price)) {
            continue;
        }

        foreach ($product->prices->price as $priceNode) {
            $izd = trim((string)$priceNode->izd_code);
            if ($izd === '' || !isset($wanted[$izd]) || isset($found[$izd])) {
                continue;
            }

            $priceStr = trim((string)$priceNode->price);
            $priceVal = $priceStr !== '' ? (float)str_replace([' ', ','], ['', '.'], $priceStr) : 0.0;

            $docsXml = '';
            if (isset($product->docs)) {
                $docsXml = $product->docs->asXML() ?: '';
            }

            $found[$izd] = [
                'desc' => (string)$product->desc,
                'specs' => (string)$product->specs,
                'docs_xml' => $docsXml,
                'price' => $priceVal,
                'price_name' => trim((string)$priceNode->name),
            ];

            if (count($found) >= count($wanted)) {
                break 2;
            }
        }
    }

    $reader->close();
    return $found;
}

function buildDetailHtml(string $shortDescription, string $desc, string $specs): string
{
    $parts = [];

    $shortDescription = trim($shortDescription);
    if ($shortDescription !== '') {
        $parts[] = '<p>' . nl2br(htmlspecialchars($shortDescription, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $desc = trim($desc);
    if ($desc !== '') {
        $parts[] = $desc;
    }

    $specs = trim($specs);
    if ($specs !== '') {
        $parts[] = $specs;
    }

    return implode("\n", $parts);
}

function parseDocsXmlToItems(string $docsXml): array
{
    $docsXml = trim($docsXml);
    if ($docsXml === '') {
        return [];
    }

    try {
        $docs = new SimpleXMLElement($docsXml);
    } catch (Throwable $e) {
        return [];
    }

    $items = [];
    foreach ($docs->doc as $group) {
        if (!isset($group->items) || !isset($group->items->item)) {
            continue;
        }

        foreach ($group->items->item as $item) {
            $name = trim((string)$item->name);
            $link = trim((string)$item->link);
            if ($link === '') {
                continue;
            }
            if ($name === '') {
                $name = basename(parse_url($link, PHP_URL_PATH) ?: $link);
            }
            $items[] = [
                'name' => $name,
                'link' => $link,
            ];
        }
    }

    return $items;
}

function getOrCreateFileIdByUrl(string $url, string $description, string $docsDir, array &$docsUrlToFileId): ?int
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (isset($docsUrlToFileId[$url])) {
        $fileId = (int)$docsUrlToFileId[$url];
        if ($fileId > 0) {
            $existing = CFile::GetByID($fileId)->Fetch();
            if (is_array($existing) && (int)$existing['ID'] === $fileId) {
                return $fileId;
            }
        }
        unset($docsUrlToFileId[$url]);
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    $base = basename($path);
    if ($base === '' || $base === '/' || $base === '.' || $base === '..') {
        $base = md5($url);
    }

    $safeName = md5($url) . '_' . $base;
    $localPath = rtrim($docsDir, '/') . '/' . $safeName;

    if (!file_exists($localPath)) {
        $http = new HttpClient([
            'socketTimeout' => 60,
            'streamTimeout' => 600,
            'disableSslVerification' => true,
            'redirect' => true,
            'redirectMax' => 5,
        ]);

        $ok = $http->download($url, $localPath);
        if (!$ok || !file_exists($localPath) || filesize($localPath) === 0) {
            @unlink($localPath);
            return null;
        }
    }

    $fa = CFile::MakeFileArray($localPath);
    if (!is_array($fa)) {
        return null;
    }

    $fa['MODULE_ID'] = 'iblock';
    $fa['description'] = $description !== '' ? $description : $base;

    $fileId = (int)CFile::SaveFile($fa, 'iblock');
    if ($fileId <= 0) {
        return null;
    }

    // Локальный файл нужен был только как промежуточный буфер для скачивания.
    // После сохранения в Bitrix можно удалить, чтобы не хранить дубль на диске.
    if (file_exists($localPath)) {
        @unlink($localPath);
    }

    $docsUrlToFileId[$url] = $fileId;
    return $fileId;
}

function collectDocsPropertyValue(string $docsXml, string $docsDir, array &$docsUrlToFileId): array
{
    $items = parseDocsXmlToItems($docsXml);
    if (!$items) {
        return [];
    }

    $values = [];
    foreach ($items as $it) {
        $link = (string)$it['link'];
        $name = (string)$it['name'];
        $fileId = getOrCreateFileIdByUrl($link, $name, $docsDir, $docsUrlToFileId);
        if ($fileId && $fileId > 0) {
            $values[] = (int)$fileId;
        }
    }

    return $values;
}

function findElementIdByXmlId(string $xmlId, int $iblockId): ?int
{
    $row = ElementTable::getList([
        'filter' => [
            '=IBLOCK_ID' => $iblockId,
            '=XML_ID' => $xmlId,
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    return $row ? (int)$row['ID'] : null;
}

function getBasePriceTypeId(): int
{
    static $baseId = null;
    if ($baseId !== null) {
        return $baseId;
    }

    $row = GroupTable::getList([
        'filter' => ['=BASE' => 'Y'],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    $baseId = $row ? (int)$row['ID'] : 1;
    return $baseId;
}

function ensureCatalogProductRow(int $elementId): void
{
    if (!class_exists('CCatalogProduct')) {
        return;
    }

    $row = CCatalogProduct::GetByID($elementId);
    if (is_array($row) && (int)$row['ID'] > 0) {
        return;
    }

    CCatalogProduct::Add([
        'ID' => $elementId,
        'QUANTITY' => 0,
    ]);
}

function upsertBasePrice(int $productId, float $price, string $currency = 'RUB'): void
{
    $baseType = getBasePriceTypeId();

    $existing = PriceTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=CATALOG_GROUP_ID' => $baseType,
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    if ($existing) {
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

$items = readFirstProductsFromOwenkomplekt($owenkomplektXmlPath, $limit);
if (!$items) {
    exit("Не удалось прочитать товары из {$owenkomplektXmlPath}\n");
}

// В некоторых инфоблоках привязка к разделу обязательна.
// Если section не задан — пробуем подобрать раздел по названию категории из owenkomplekt XML.
if ($targetSectionId <= 0) {
    $catNames = [];
    foreach ($items as $it) {
        $cn = trim((string)($it['category_name'] ?? ''));
        if ($cn !== '') {
            $catNames[$cn] = true;
        }
    }

    if (count($catNames) === 1) {
        $cn = array_key_first($catNames);
        $secRes = CIBlockSection::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $targetIblockId, '=NAME' => $cn],
            false,
            ['ID', 'NAME']
        );

        $matches = [];
        while ($row = $secRes->Fetch()) {
            $matches[] = (int)$row['ID'];
            if (count($matches) > 1) {
                break;
            }
        }

        if (count($matches) === 1) {
            $targetSectionId = $matches[0];
            echo "AUTO SECTION: '{$cn}' -> section_id={$targetSectionId}\n";
        } elseif (!$dryRun) {
            exit("Не удалось однозначно подобрать раздел по NAME='{$cn}'. Укажите section=ID вручную.\n");
        } else {
            echo "WARN: не удалось однозначно подобрать раздел по NAME='{$cn}'. Для dry=0 укажите section=ID вручную.\n";
        }
    } elseif (!$dryRun) {
        exit("Не указан section=ID и не удалось подобрать автоматически (категорий найдено: " . count($catNames) . "). Укажите section=ID.\n");
    }
}

$articles = array_map(static fn(array $i) => $i['article'], $items);
$catalogDataByIzd = loadCatalogOvenDataByIzdCodes($catalogOvenXmlPath, $articles);

if (!$dryRun) {
    if (!Loader::includeModule('catalog')) {
        $applyCatalogPrice = 0;
    }
}

$el = new CIBlockElement();

$created = 0;
$updated = 0;
$missedInCatalogOven = 0;

foreach ($items as $item) {
    $article = $item['article'];
    $name = $item['name'] !== '' ? $item['name'] : $article;

    $catalog = $catalogDataByIzd[$article] ?? null;
    if ($catalog === null) {
        $missedInCatalogOven++;
        $catalog = [
            'desc' => '',
            'specs' => '',
            'docs_xml' => '',
            'price' => 0.0,
            'price_name' => '',
        ];
    }

    $detailHtml = buildDetailHtml(
        $item['short_description'],
        (string)$catalog['desc'],
        (string)$catalog['specs']
    );

    $docsPropValue = [];
    if (!$dryRun && (string)$catalog['docs_xml'] !== '') {
        $docsPropValue = collectDocsPropertyValue((string)$catalog['docs_xml'], $docsDir, $docsUrlToFileId);
    }

    $fileArray = null;
    if (!$dryRun && $item['image'] !== '') {
        $fileArray = CFile::MakeFileArray($item['image']);
        if (is_array($fileArray)) {
            $fileArray['MODULE_ID'] = 'iblock';
        } else {
            $fileArray = null;
        }
    }

    $fields = [
        'IBLOCK_ID' => $targetIblockId,
        'ACTIVE' => 'Y',
        'NAME' => $name,
        'XML_ID' => $article,
        'CODE' => $article,
        'DETAIL_TEXT' => $detailHtml,
        'DETAIL_TEXT_TYPE' => 'html',
        'PREVIEW_TEXT' => $item['short_description'],
        'PREVIEW_TEXT_TYPE' => 'text',
    ];

    if ($targetSectionId > 0) {
        $fields['IBLOCK_SECTION_ID'] = $targetSectionId;
    }

    if ($fileArray !== null) {
        $fields['PREVIEW_PICTURE'] = $fileArray;
        $fields['DETAIL_PICTURE'] = $fileArray;
    }

    $existingId = findElementIdByXmlId($article, $targetIblockId);
    if ($existingId) {
        if (!$updateExisting) {
            echo "SKIP EXISTS: article={$article} | name={$name} -> element_id={$existingId} (update=0)\n";
            continue;
        }
        if ($dryRun) {
            echo "DRY UPDATE: article={$article} | name={$name} -> element_id={$existingId}\n";
            $elementId = $existingId;
        } else {
            $ok = $el->Update($existingId, $fields, false, false, true);
            if (!$ok) {
                echo "ERR UPDATE: article={$article} | " . $el->LAST_ERROR . "\n";
                continue;
            }
            $updated++;
            $elementId = $existingId;
            echo "OK UPDATE: article={$article} | name={$name} -> element_id={$elementId}\n";
        }
    } else {
        if ($dryRun) {
            echo "DRY ADD: article={$article} | name={$name}\n";
            continue;
        }
        $newId = (int)$el->Add($fields);
        if ($newId <= 0) {
            echo "ERR ADD: article={$article} | " . $el->LAST_ERROR . "\n";
            continue;
        }
        $created++;
        $elementId = $newId;
        echo "OK ADD: article={$article} | name={$name} -> element_id={$elementId}\n";
    }

    if (!$dryRun && $docsPropIsFile && isset($elementId) && $docsPropValue) {
        $docsValue = $docsPropMultiple ? $docsPropValue : (isset($docsPropValue[0]) ? (int)$docsPropValue[0] : null);
        if ($docsValue) {
            try {
                CIBlockElement::SetPropertyValuesEx($elementId, $targetIblockId, [
                    'DOCS' => $docsValue,
                ]);
            } catch (Throwable $e) {
                echo "ERR DOCS: article={$article} | " . $e->getMessage() . "\n";
            }
        }
    }

    if ($applyCatalogPrice && !$dryRun && isset($elementId) && (float)$catalog['price'] > 0) {
        try {
            ensureCatalogProductRow($elementId);
            upsertBasePrice($elementId, (float)$catalog['price']);
        } catch (Throwable $e) {
            echo "ERR PRICE: article={$article} | " . $e->getMessage() . "\n";
        }
    }
}

if (!$dryRun) {
    @file_put_contents($docsCacheFile, json_encode($docsUrlToFileId, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo "DONE: created={$created}, updated={$updated}, missed_in_catalogOven={$missedInCatalogOven}\n";
