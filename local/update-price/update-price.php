<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Catalog\Model\Price;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\GroupTable;

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    exit("CLI only\n");
}

@set_time_limit(0);

$logDir = __DIR__ . '/log';
$logFile = $logDir . '/update-price.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$log = function (string $message) use ($logFile): void {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$ts}] {$message}\n", FILE_APPEND);
};

register_shutdown_function(static function () use ($log) {
    $e = error_get_last();
    if (is_array($e) && !empty($e['type'])) {
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array((int)$e['type'], $fatalTypes, true)) {
            $log('FATAL: ' . ($e['message'] ?? '') . ' in ' . ($e['file'] ?? '') . ':' . ($e['line'] ?? ''));
        }
    }
    $log('--- finish ---');
});

$log('--- start ---');
$log('PHP_SAPI=' . PHP_SAPI);
$log('argv=' . (isset($_SERVER['argv']) ? implode(' ', (array)$_SERVER['argv']) : ''));

if ($isCli) {
    $envRoot = getenv('DOCUMENT_ROOT');
    if (!empty($envRoot)) {
        $_SERVER['DOCUMENT_ROOT'] = $envRoot;
    }
}

$log('DOCUMENT_ROOT(env)=' . (getenv('DOCUMENT_ROOT') !== false ? (string)getenv('DOCUMENT_ROOT') : ''));
$log('DOCUMENT_ROOT(server)=' . (isset($_SERVER['DOCUMENT_ROOT']) ? (string)$_SERVER['DOCUMENT_ROOT'] : ''));

if (!defined('SITE_ID')) {
    define('SITE_ID', 's1');
}

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'); 
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../..');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$log('docRoot(final)=' . (string)$docRoot);

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

$log('prologPath=' . $prologPath);

if (!file_exists($prologPath)) {
    $log('ERROR: prolog_before.php not found');
    exit("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}
require $prologPath;
$log('prolog_before.php included');

global $USER;

if (!is_object($USER)) {
    $USER = new \CUser();
}

$adminIdEnv = getenv('UPDATE_PRICE_ADMIN_ID'); 

$adminId = 1;


if ($adminIdEnv !== false && $adminIdEnv !== '') {
    $adminId = (int)$adminIdEnv;
}

if ($adminId > 0) {
    if (!$USER->IsAuthorized()) {
        $USER->Authorize($adminId);
    }
}

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    $log('ERROR: failed include iblock/catalog');
    exit("Не удалось подключить модуль iblock или catalog\n");
}

$log('includeModule iblock/catalog OK');

$dwDeluxeLoaded = Loader::includeModule('dw.deluxe');
if (!$dwDeluxeLoaded) {
    exit("модуль dw.deluxe не подключен\n");
}

$needRecalcMinMax = $dwDeluxeLoaded && class_exists('DwProductEvents');
if (!$needRecalcMinMax) {
    exit("не найден класс DwProductEvents (dw.deluxe)\n");
}

$current = Option::get('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'N', SITE_ID);
if ($current !== 'Y') {
    Option::set('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'Y', SITE_ID);
}

function resolveParentProductForRecalc(int $elementId): array
{
    $parentId = $elementId;
    $parentIblockId = 0;

    if (class_exists('CCatalogSku')) {
        $skuInfo = \CCatalogSku::GetProductInfo($elementId);
        if (is_array($skuInfo) && !empty($skuInfo['ID']) && !empty($skuInfo['IBLOCK_ID'])) {
            $parentId = (int)$skuInfo['ID'];
            $parentIblockId = (int)$skuInfo['IBLOCK_ID'];
        }
    }

    if ($parentIblockId <= 0) {
        $row = ElementTable::getList([
            'filter' => ['=ID' => $parentId],
            'select' => ['IBLOCK_ID'],
            'limit' => 1,
        ])->fetch();
        if ($row && !empty($row['IBLOCK_ID'])) {
            $parentIblockId = (int)$row['IBLOCK_ID'];
        }
    }

    return [$parentId, $parentIblockId];
}

$xmlPath = $docRoot . '/catalogOven.xml';

if (!file_exists($xmlPath)) {
    exit("Не найден файл XML по пути: {$xmlPath}\n");
}

$log('xmlPath=' . $xmlPath);

$xml = @simplexml_load_file($xmlPath);

if ($xml === false) {
    exit("Не удалось загрузить XML файл: {$xmlPath}\n");
}

$pricesByIzd  = []; 

foreach ($xml->xpath('//price[izd_code]') as $priceNode) {
    $izdCode = trim((string)$priceNode->izd_code);
    $priceStr = trim((string)$priceNode->price);

    if ($izdCode === '' || $priceStr === '') {
        continue;
    }

    $price = (float)str_replace([' ', ','], ['', '.'], $priceStr);

    $pricesByIzd[$izdCode] = $price;
}

$log('xml prices parsed=' . count($pricesByIzd));

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

function upsertBasePrice_Model(int $productId, float $price, string $currency = 'RUB'): void
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

    $fields = [
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $baseType,
        'PRICE' => $price,
        'CURRENCY' => $currency,
        'QUANTITY_FROM' => null,
        'QUANTITY_TO' => null,
    ];

    if ($existing && !empty($existing['ID'])) {
        $priceId = (int)$existing['ID'];
        $result = Price::update($priceId, $fields);
    } else {
        $result = Price::add($fields);
    }

    if (!$result->isSuccess()) {
        $errors = $result->getErrorMessages();
        throw new \RuntimeException('Ошибка сохранения цены: ' . implode('; ', $errors));
    }
}

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

$updated = 0;
$missed  = 0;
$errors  = 0;
$recalcParents = [];

$logEachEnv = getenv('UPDATE_PRICE_LOG_EACH');
$logEach = 500;
if ($logEachEnv !== false && $logEachEnv !== '') {
    $logEach = max(1, (int)$logEachEnv);
}

$total = count($pricesByIzd);
$log('loop start: total=' . $total . ', log_each=' . $logEach);

$targetIblockId = 17;

foreach ($pricesByIzd as $izd => $price) {
    $processed = $updated + $missed + $errors + 1;
    if (($processed % $logEach) === 0) {
        $log('progress: processed=' . $processed . '/' . $total . ', updated=' . $updated . ', missed=' . $missed . ', errors=' . $errors);
    }

    $elementId = findElementIdByCode($izd, $targetIblockId);
    if (!$elementId) {
        $missed++;
        continue;
    } 
    try {
        upsertBasePrice_Model($elementId, $price);
        $updated++;

        if ($needRecalcMinMax) {
            [$parentId, $parentIblockId] = resolveParentProductForRecalc($elementId);
            if ($parentId > 0 && $parentIblockId > 0) {
                $recalcParents[$parentId] = $parentIblockId;
            }
        }
    } catch (\Throwable $e) {
        $errors++;
        $log('ERROR update: izd=' . $izd . ', elementId=' . $elementId . ', message=' . $e->getMessage());
        echo "Ошибка: {$izd} -> {$elementId}: {$e->getMessage()}\n";
    }
}

$log('loop done: updated=' . $updated . ', missed=' . $missed . ', errors=' . $errors . ', parents_for_recalc=' . count($recalcParents));

if ($needRecalcMinMax && !empty($recalcParents)) {
    global $USER;
    if (!is_object($USER)) {
        $USER = new \CUser();
    }

    foreach ($recalcParents as $parentId => $parentIblockId) {
        try {
            DwProductEvents::productAfterSave([
                'ID' => (int)$parentId,
                'IBLOCK_ID' => (int)$parentIblockId,
            ]);
        } catch (\Throwable $e) {
            $errors++;
            $log('ERROR recalc: product_id=' . $parentId . ', message=' . $e->getMessage());
            echo "Ошибка пересчёта MIN/MAX: product_id={$parentId}: {$e->getMessage()}\n";
        }
    }
}

$log('done: updated=' . $updated . ', missed=' . $missed . ', errors=' . $errors);

echo "Итог: обновлено {$updated}, не найдено элементов: {$missed}, ошибок: {$errors}\n";