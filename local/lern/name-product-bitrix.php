<?php

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;

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

if(!Loader::includeModule('iblock')) {
    exit("Ошибка: не удалось подключить модуль iblock\n");
}

if(!Loader::includeModule('catalog')) {
    exit("Ошибка: не удалось подключить модуль catalog\n");
}

$rootBlockId = 111; // ID инфоблока с товарами

$res = ElementTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $rootBlockId,
        '=ACTIVE' => 'Y',
    ],
    'select' => ['ID', 'NAME'],
    'limit' => 50, // ограничение на количество товаров для выборки
]);
 
while ($item = $res->fetch()) {
    $items[] = $item;
}
print_r($items);