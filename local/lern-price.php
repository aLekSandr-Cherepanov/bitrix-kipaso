<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\CatalogIblockTable;

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (PHP_SAPI === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..');
}

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/..');//пытаемся определить DOCUMENT_ROOT ,если его нет то ищем сами
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../../..');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    die("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}
require $prologPath;

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    die ("Не удалось подключить модуль iblock или catalog\n");
}

$limit = (int)($argv[1] ?? 200);
$offset = (int)($argv[2] ?? 0);
echo "limit={$limit}, offset={$offset}\n";

$iblockId = 110;




