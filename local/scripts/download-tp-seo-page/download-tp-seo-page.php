<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\VatTable;

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

$docsPropRow = CIBlockProperty::GetList(
    [], 
    [
        'IBLOCK_ID' => $iblockId, 
        '=CODE' => 'DOCS'
    ]
)->Fetch();
$docsPropId = (int)($docsPropRow['ID'] ?? 0);

$iblockVersion = (int)CIBlock::GetArrayByID($iblockId, 'VERSION');

$manufacturerElementId = 55675;
$manufacturerPropCode = 'ATT_BRAND';
$manufacturerPropRow = CIBlockProperty::GetList(
    [],
    [
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        '=CODE' => $manufacturerPropCode,
    ]
)->Fetch();

$manufacturerPropId = (int)($manufacturerPropRow['ID'] ?? 0);

$vatId = 3;
$vatRow = VatTable::getById($vatId)->fetch();
if (!$vatRow) {
    $vatId = 0;
} else {
    //
}

$targetCategoryId = 'izmeriteli_regulyatori';// фильтруем по категории

$xmlPath = $docRoot . '/catalogOven.xml';
if (!file_exists($xmlPath)) {
    exit("Не найден файл XML по пути: {$xmlPath}\n");
}

$xml = @simplexml_load_file($xmlPath);
if ($xml === false) {
    exit("Не удалось загрузить XML файл: {$xmlPath}\n");
}

$supplierByArticle = [];

$productNodes = $xml->xpath(sprintf(
    '//categories//item[id="%s"]/products/product[prices/price/izd_code]'// было product[prices/price/izd_code]
    , $targetCategoryId
));

if (!$productNodes) {
    exit("В catalogOven.xml не найдены <product> в категории {$targetCategoryId} с prices/price/izd_code. Проверить структуру.\n");
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
            'parent_id' => trim((string)$productNode->id),
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
    exit("В owenkomplekt_izmeriteli_regulyatory.xml не найдены <product> с article. Проверить структуру.\n");
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
        'parent_id' => (string)($supplier['parent_id'] ?? ''),
    ];

    $matched++;
}

if (empty($readyByArticle)) {
    exit("readyByArticle пустой — нечего импортировать.\n");
}

$sectionId = 111;

$quiet = true;

$GLOBALS['IMPORT_STATS'] = [
    'processed' => 0,
    'added' => 0,
    'updated' => 0,
    'skipped_empty_code' => 0,
    'errors' => 0,
    'price_errors' => 0,
    'vat_errors' => 0,
    'img_download_errors' => 0,
    'img_update_errors' => 0,
];

$GLOBALS['IMPORT_ERRORS'] = [];

function importAddError(string $message, string $bucket = 'errors'): void
{
    if (!isset($GLOBALS['IMPORT_STATS'])) {
        return;
    }
    $GLOBALS['IMPORT_STATS']['errors'] = (int)($GLOBALS['IMPORT_STATS']['errors'] ?? 0) + 1;
    if (isset($GLOBALS['IMPORT_STATS'][$bucket])) {
        $GLOBALS['IMPORT_STATS'][$bucket] = (int)$GLOBALS['IMPORT_STATS'][$bucket] + 1;
    }
    if (isset($GLOBALS['IMPORT_ERRORS']) && count($GLOBALS['IMPORT_ERRORS']) < 50) {
        $GLOBALS['IMPORT_ERRORS'][] = $message;
    }
}

const PROP_SPECS = 'SPECIFICATIONS_TEXT';

function downloadToTemp(string $url): ?array {
    $url = trim($url);
    if ($url === '') return null;
    // имя файла из URL (чтобы был .webp)
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $baseName = basename($path);
    if ($baseName === '' || $baseName === '/' || $baseName === '.') {
        $baseName = 'image.webp';
    }

    if (!preg_match('~\.(webp|jpg|jpeg|png|gif)$~i', $baseName)) {
        $baseName .= '.webp';
    }

    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

    $tmp = tempnam(sys_get_temp_dir(), 'img_');
    if ($tmp === false) return null;

    $tmpWithExt = $tmp . '.' . $ext;
    rename($tmp, $tmpWithExt);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'BitrixSeoOfferImporter/1.0',
    ]);

    $data = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($data === false || $http < 200 || $http >= 300) {
        @unlink($tmpWithExt);
        importAddError("КАРТИНКА: ошибка загрузки: {$url} | http={$http} | err={$err}", 'img_download_errors');
        return null;
    }

    file_put_contents($tmpWithExt, $data);

    return [
        'path' => $tmpWithExt,
        'name' => $baseName, 
    ];
}

function makeElementCode(string $name): string
{
    $name = trim($name);
    if ($name === '') return '';

    return \CUtil::translit($name, 'ru', [
        'max_len' => 200,
        'change_case' => 'L',
        'replace_space' => '_',
        'replace_other' => '_',
        'delete_repeat_replace' => true,
    ]);
}

function findElementByXmlId(int $iblockId, string $xmlId): array
{
    $row = ElementTable::getList([
        'filter' => [
            '=IBLOCK_ID' => $iblockId,
            '=XML_ID' => $xmlId,
        ],
        'select' => [
            'ID',
            'CODE',
            'DETAIL_PICTURE',
            'PREVIEW_PICTURE',
        ],
        'limit' => 1,
    ])->fetch();

    if ($row) {
        return [
            'ID' => (int)$row['ID'],
            'CODE' => (string)$row['CODE'],
            'DETAIL_PICTURE' => $row['DETAIL_PICTURE'],
            'PREVIEW_PICTURE' => $row['PREVIEW_PICTURE'],
        ];
    }

    return ['ID' => 0, 'CODE' => '', 'DETAIL_PICTURE' => null, 'PREVIEW_PICTURE' => null];
}

function ensureProductVat(int $productId, int $vatId): void
{
    if (ProductTable::getById($productId)->fetch() === false) {
        $r = ProductTable::add([
            'ID' => $productId,
            'QUANTITY' => 0,
            'CAN_BUY_ZERO' => 'Y',
            'QUANTITY_TRACE' => 'Y',
            'AVAILABLE' => 'Y',
            'VAT_ID' => $vatId,
            'VAT_INCLUDED' => 'Y',
        ]);
        if (!$r->isSuccess()) {
            throw new \RuntimeException('ProductTable::add failed: ' . implode('; ', $r->getErrorMessages()));
        }
    } else {
        $r = ProductTable::update($productId, [
            'CAN_BUY_ZERO' => 'Y',
            'QUANTITY_TRACE' => 'Y',
            'AVAILABLE' => 'Y',
            'VAT_ID' => $vatId,
            'VAT_INCLUDED' => 'Y',
        ]);
        if (!$r->isSuccess()) {
            throw new \RuntimeException('ProductTable::update failed: ' . implode('; ', $r->getErrorMessages()));
        }
    }
}

function setBasePrice(int $productId, float $price, string $currency = 'RUB', int $priceGroupId = 1, int $vatId = 0): void
{
    if (!Loader::includeModule('catalog')) {
        throw new \RuntimeException('catalog module not loaded');
    }

    ensureProductVat($productId, $vatId);

    $row = PriceTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=CATALOG_GROUP_ID' => $priceGroupId,
        ],
        'select' => ['ID'],
        'limit' => 1,
    ])->fetch();

    $fields = [
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $priceGroupId,
        'PRICE' => $price,
        'PRICE_SCALE' => $price, 
        'CURRENCY' => $currency,
    ];

    if ($row && (int)$row['ID'] > 0) {
        $r = PriceTable::update((int)$row['ID'], $fields);
        if (!$r->isSuccess()) {
            throw new \RuntimeException('PriceTable::update failed: ' . implode('; ', $r->getErrorMessages()));
        }
    } else {
        $r = PriceTable::add($fields);
        if (!$r->isSuccess()) {
            throw new \RuntimeException('PriceTable::add failed: ' . implode('; ', $r->getErrorMessages()));
        }
    }
}

function findElementIdByCodeInIblock(int $iblockId, string $code): int
{
    $code = trim($code);
    if ($code === '') return 0;

    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, '=CODE' => $code],
        false,
        ['nTopCount' => 1],
        ['ID']
    );

    if ($row = $res->Fetch()) {
        return (int)$row['ID'];
    }

    return 0;
}

function getDocsFileIdsFromElement(int $iblockId, int $elementId): array
{
    $items = [];
    $res = CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => 'DOCS']);
    while ($row = $res->Fetch()) {
        $fileId = (int)($row['VALUE'] ?? 0);
        if ($fileId > 0) {
            $items[] = [
                'VALUE' => $fileId,
                'DESCRIPTION' => (string)($row['DESCRIPTION'] ?? ''),
            ];
        }
    }

    if (!$items) {
        return [];
    }

    $byId = [];
    foreach ($items as $it) {
        $id = (int)$it['VALUE'];
        if ($id <= 0) {
            continue;
        }
        if (!isset($byId[$id])) {
            $byId[$id] = $it;
            continue;
        }
        if ($byId[$id]['DESCRIPTION'] === '' && $it['DESCRIPTION'] !== '') {
            $byId[$id]['DESCRIPTION'] = $it['DESCRIPTION'];
        }
    }

    return array_values($byId);
}


$el = new CIBlockElement();

foreach ($readyByArticle as $key => $data) {
    $GLOBALS['IMPORT_STATS']['processed']++;
    $xmlId = (string)$data['xml_id'];
    $name  = (string)$data['name'];

    $sectionId = 111;
    $code = makeElementCode($name) . '_' . $xmlId;

    if ($code === '') {
        $GLOBALS['IMPORT_STATS']['skipped_empty_code']++;
        continue;
    }
        $fields = [
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $sectionId,
        'NAME' => $name,
        'CODE' => $code,
        'XML_ID' => $xmlId,
        'ACTIVE' => 'Y',
        'DETAIL_TEXT' => (string)$data['detail_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];

    $props = [
        // Характеристики (HTML/текст)
        PROP_SPECS => (string)$data['specifications_html'],
        // Свойство "XML_ID элемента"
        'XML_ID' => $xmlId,
    ];

    if ($manufacturerPropId > 0) {
        $props[$manufacturerPropCode] = $manufacturerElementId;
    }

    $found = findElementByXmlId($iblockId, $xmlId);

    if ($found['ID'] > 0) {
        // зафиксируем текущий символьный код (URL) — чтобы не перезаписался артикулом
        $fields['CODE'] = $found['CODE'];

        $ok = $el->Update($found['ID'], $fields);
        if (!$ok) {
            importAddError("ОБНОВЛЕНИЕ: ошибка xml_id={$xmlId} | {$el->LAST_ERROR}");
            continue;
        }

        CIBlockElement::SetPropertyValuesEx($found['ID'], $iblockId, $props);

        $elementId = $found['ID'];
        $hasPreview = !empty($found['PREVIEW_PICTURE']);
        $hasDetail  = !empty($found['DETAIL_PICTURE']);
        $GLOBALS['IMPORT_STATS']['updated']++;
    } else {
        $newId = $el->Add($fields);
        if (!$newId) {
            importAddError("ДОБАВЛЕНИЕ: ошибка xml_id={$xmlId} | {$el->LAST_ERROR}");
            continue;
        }

        CIBlockElement::SetPropertyValuesEx((int)$newId, $iblockId, $props);

        $elementId = (int)$newId;
        $hasPreview = false;
        $hasDetail  = false;
        $GLOBALS['IMPORT_STATS']['added']++;
    }

    if ($vatId > 0) {
        try {
            ensureProductVat($elementId, $vatId);
        } catch (\Throwable $e) {
            importAddError('НДС: ошибка: ' . $e->getMessage(), 'vat_errors');
        }
    }

    //  DOCS
    $parentCode = trim((string)($data['parent_id'] ?? '')); 

    if ($parentCode !== '') {
        $parentElementId = findElementIdByCodeInIblock($iblockId, $parentCode);

        if ($parentElementId > 0) {
            $parentDocs = getDocsFileIdsFromElement($iblockId, $parentElementId);

            if (!empty($parentDocs)) {
                if ($docsPropId > 0) {
                    $db = $GLOBALS['DB'];
                    $pvids = [];

                    $res = CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => 'DOCS']);
                    while ($row = $res->Fetch()) {
                        $pvid = (int)($row['PROPERTY_VALUE_ID'] ?? 0);
                        if ($pvid > 0) {
                            $pvids[$pvid] = $pvid;
                        }
                    }

                    if ($pvids) {
                        $idList = implode(',', array_values($pvids));
                        if ($iblockVersion === 1) {
                            $db->Query('DELETE FROM b_iblock_element_property WHERE ID IN (' . $idList . ')', true);
                        } else {
                            $mTable = 'b_iblock_element_prop_m' . (int)$iblockId;
                            $db->Query('DELETE FROM ' . $mTable . ' WHERE ID IN (' . $idList . ')', true);
                        }
                    }
                }

                $docsForSet = [];
                foreach ($parentDocs as $it) {
                    $docsForSet[] = [
                        'VALUE' => (int)($it['VALUE'] ?? 0),
                        'DESCRIPTION' => (string)($it['DESCRIPTION'] ?? ''),
                    ];
                }

                CIBlockElement::SetPropertyValueCode($elementId, 'DOCS', $docsForSet);
            }
        }
    }

    // БАЗОВАЯ ЦЕНА (Торговый каталог)
    $priceStr = (string)($data['price'] ?? '');
    $priceNum = (float)str_replace(',', '.', $priceStr);

    if ($priceNum > 0) {
        try {
            setBasePrice($elementId, $priceNum, 'RUB', 1, $vatId);
        } catch (\Throwable $e) {
            importAddError('ЦЕНА: ошибка: ' . $e->getMessage(), 'price_errors');
        }
    }

    // КАРТИНКА (DETAIL_PICTURE) 
    $imgUrl = trim((string)($data['image_url'] ?? ''));
    if ((!$hasPreview || !$hasDetail) && $imgUrl !== '') {
        $tmpFile = downloadToTemp($imgUrl);
        if ($tmpFile) {
            $fileArr = CFile::MakeFileArray($tmpFile['path']);
            $fileArr['name'] = $tmpFile['name'];

            if (!empty($fileArr)) {
                $okImg = $el->Update($elementId, [
                    'PREVIEW_PICTURE' => $fileArr,
                    'DETAIL_PICTURE'  => $fileArr,
                ]);

                if (!$okImg) {
                    importAddError('КАРТИНКА: ошибка обновления: ' . $el->LAST_ERROR, 'img_update_errors');
                }
            }

            @unlink($tmpFile['path']);
        }
    }
}

$st = $GLOBALS['IMPORT_STATS'];

if (!$quiet || (int)($st['errors'] ?? 0) > 0) {
    echo "ГОТОВО: обработано={$st['processed']} | добавлено={$st['added']} | обновлено={$st['updated']} | пропущено(пустой CODE)={$st['skipped_empty_code']} | ошибок={$st['errors']}" . PHP_EOL;

    if (!empty($GLOBALS['IMPORT_ERRORS'])) {
        echo "ПРИМЕРЫ ОШИБОК (первые " . count($GLOBALS['IMPORT_ERRORS']) . "):" . PHP_EOL;
        foreach ($GLOBALS['IMPORT_ERRORS'] as $msg) {
            echo $msg . PHP_EOL;
        }
    }
}