<?php

use Bitrix\Main\Loader;


$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $docRoot = realpath(__DIR__ . '/../../');
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    echo "Не найден файл prolog_before.php по пути: {$prologPath}\n";
    exit;
}

require_once $prologPath;

if (!Loader::includeModule('iblock')) {
    echo "Не удалось загрузить модуль 'iblock'.\n";
    exit;
}

$iblockId = 16; // ID инфоблока
$sectionId = 111;

$products = [];

$res = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    [
        'IBLOCK_ID' => $iblockId, 
        'IBLOCK_SECTION_ID' => $sectionId,
        'ACTIVE' => 'Y'
    ],
    false,
    ['nTopCount' => 5],
    ['ID', 'NAME', 'CODE', 'XML_ID']
);

while ($item = $res->Fetch()) {
    $products[] = $item;
}






echo '<pre>';
print_r($products);
echo '</pre>';
