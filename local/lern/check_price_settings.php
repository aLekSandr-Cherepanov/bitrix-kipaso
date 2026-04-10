<?php
/**
 * Проверка настройки автоматического расчета цен
 */

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if (empty($docRoot) || !file_exists($docRoot . '/bitrix/modules/main/include/prolog_before.php')) {
    $docRoot = realpath(__DIR__ . '/../../');
}
$_SERVER['DOCUMENT_ROOT'] = $docRoot;

require($docRoot . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');

$siteId = 's1'; // или ваш ID сайта

// Проверяем настройку
$setting = Option::get('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'N', $siteId);

echo "Сайт: {$siteId}\n";
echo "TEMPLATE_USE_AUTO_SAVE_PRICE: {$setting}\n\n";

if ($setting !== 'Y') {
    echo "⚠️ НАСТРОЙКА ВЫКЛЮЧЕНА!\n";
    echo "Для включения выполните:\n";
    echo "Option::set('dw.deluxe', 'TEMPLATE_USE_AUTO_SAVE_PRICE', 'Y', '{$siteId}');\n\n";
} else {
    echo "✓ Настройка включена\n\n";
}

// Проверяем наличие свойств MINIMUM_PRICE и MAXIMUM_PRICE
$iblockId = 16;
$rsProperty = CIBlockProperty::GetList(
    [],
    ['IBLOCK_ID' => $iblockId, 'CODE' => 'MINIMUM_PRICE']
);

if ($prop = $rsProperty->Fetch()) {
    echo "✓ Свойство MINIMUM_PRICE найдено (ID: {$prop['ID']})\n";
} else {
    echo "✗ Свойство MINIMUM_PRICE НЕ НАЙДЕНО!\n";
}

$rsProperty = CIBlockProperty::GetList(
    [],
    ['IBLOCK_ID' => $iblockId, 'CODE' => 'MAXIMUM_PRICE']
);

if ($prop = $rsProperty->Fetch()) {
    echo "✓ Свойство MAXIMUM_PRICE найдено (ID: {$prop['ID']})\n";
} else {
    echo "✗ Свойство MAXIMUM_PRICE НЕ НАЙДЕНО!\n";
}
