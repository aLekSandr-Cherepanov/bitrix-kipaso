<?php
/**
 * Скрипт для ручного пересчета MINIMUM_PRICE и MAXIMUM_PRICE для товаров с торговыми предложениями
 * Запуск: php recalc_min_max_prices.php
 */

// Включаем буферизацию вывода для предотвращения ошибок с заголовками
ob_start();

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../../');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    die("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}

// Для CLI отключаем старт сессии и другие проверки
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SERVER_NAME"] = "localhost";
$_SERVER["REQUEST_METHOD"] = "CLI";

// Отключаем все что связано с сессиями и статистикой
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_NO_ACCELERATOR_RESET', true);
define("DisableEventsCheck", true);
define("STATISTIC_SKIP_ACTIVITY_CHECK", true);
define("BX_CRONTAB_SUPPORT", true);
define("BX_WITH_ON_AFTER_EPILOG", true);
define("BX_SECURITY_SESSION_VIRTUAL", true);

// PHP настройки для отключения сессий
@ini_set("session.use_cookies", "0");
@ini_set("session.use_trans_sid", "0");
@ini_set("session.cache_limiter", "");
@session_write_close();

require_once $prologPath;

// Принудительно закрываем сессию после подключения
if (!Loader::includeModule('iblock')) {
    die("Ошибка: Не удалось загрузить модуль 'iblock'.\n");
}

if (!Loader::includeModule('catalog')) {
    die("Ошибка: Не удалось загрузить модуль 'catalog'.\n");
}

if (!Loader::includeModule('dw.deluxe')) {
    die("Ошибка: Не удалось загрузить модуль 'dw.deluxe'.\n");
}

if (!class_exists('DwProductEvents')) {
    die("Ошибка: Класс DwProductEvents не найден.\n");
}

define('SITE_ID', 's1');
$autoSavePriceEnabled = Option::get('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'N', SITE_ID);

if ($autoSavePriceEnabled !== 'Y') {
    Option::set('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'Y', SITE_ID);
}

$limit = false;
$catalogIblockId = 16;

$arOffers = CCatalogSKU::GetInfoByProductIBlock($catalogIblockId);
$offersIblockId = !empty($arOffers['IBLOCK_ID']) ? $arOffers['IBLOCK_ID'] : null;

// Счетчики
$totalProcessed = 0;
$successCount = 0;
$errorCount = 0;

// Получаем все активные товары с торговыми предложениями
$filter = [
    'IBLOCK_ID' => $catalogIblockId,
    'ACTIVE' => 'Y',
];

// Если есть ТП, фильтруем только товары с предложениями
if ($offersIblockId) {
    // Получаем ID товаров, у которых есть торговые предложения
    $resOffers = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $offersIblockId,
            'ACTIVE' => 'Y'
        ],
        false,
        false,
        ['ID', 'PROPERTY_CML2_LINK']
    );
    
    $arProductsWithOffers = [];
    while ($offer = $resOffers->Fetch()) {
        if (!empty($offer['PROPERTY_CML2_LINK_VALUE'])) {
            $arProductsWithOffers[$offer['PROPERTY_CML2_LINK_VALUE']] = $offer['PROPERTY_CML2_LINK_VALUE'];
        }
    }
    
    // Подсчет товаров для обработки
    
    if (empty($arProductsWithOffers)) {
        echo "Не найдено товаров с торговыми предложениями.\n";
        exit;
    }
    
    $filter['ID'] = array_keys($arProductsWithOffers);
}

$resProducts = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    $filter,
    false,
    $limit ? ['nTopCount' => $limit] : false,
    ['ID', 'IBLOCK_ID', 'NAME', 'CODE']
);

// Начало обработки

while ($product = $resProducts->Fetch()) {
    $totalProcessed++;
    
    try {
        // Вызываем метод пересчета цен
        DwProductEvents::productAfterSave([
            'ID' => (int)$product['ID'],
            'IBLOCK_ID' => (int)$product['IBLOCK_ID']
        ]);
        
        // Проверяем, обновились ли значения
        $resCheckMin = CIBlockElement::GetProperty(
            $product['IBLOCK_ID'],
            $product['ID'],
            [],
            ['CODE' => 'MINIMUM_PRICE']
        );
        
        $resCheckMax = CIBlockElement::GetProperty(
            $product['IBLOCK_ID'],
            $product['ID'],
            [],
            ['CODE' => 'MAXIMUM_PRICE']
        );
        
        $minPrice = null;
        $maxPrice = null;
        
        if ($propCheck = $resCheckMin->Fetch()) {
            $minPrice = $propCheck['VALUE'];
        }
        
        if ($propCheck = $resCheckMax->Fetch()) {
            $maxPrice = $propCheck['VALUE'];
        }
        
        if (!empty($minPrice) && !empty($maxPrice)) {
            $successCount++;
        } else {
            $errorCount++;
        }
        
    } catch (Exception $e) {
        $errorCount++;
    }
    
    // Пауза, чтобы не перегружать сервер
    if ($totalProcessed % 10 === 0) {
        usleep(100000); // 0.1 секунды
    }
}

echo "\nОбработка завершена!\n";
echo "Всего обработано: {$totalProcessed}\n";
echo "Успешно: {$successCount}\n";
echo "С ошибками/без цен: {$errorCount}\n";
