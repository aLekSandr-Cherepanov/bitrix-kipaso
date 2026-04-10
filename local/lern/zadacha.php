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

// ищем товары по разделу
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



// ищем товары по коду
$code = 'itp16';
$productsByCode = [];
$newName = 'TEST';

$resCode = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    [
        'IBLOCK_ID' => $iblockId, 
        '=CODE' => $code,
        'ACTIVE' => 'Y'
    ],
    false,
    ['nTopCount' => 1],
    ['ID', 'NAME', 'CODE']
);
while($item = $resCode->Fetch()) {
    $productsByCode[] = $item;
    $productId = $item['ID'];
}

$resBrand = CIBlockElement::GetProperty(
    $iblockId,
    $productId,
    ['sort' => 'asc'],
    ['CODE' => 'ATT_BRAND']
);
while($brand = $resBrand->Fetch()) {
    echo "Бренд: " . $brand['VALUE'] . "\n";
}

$newBrand = 11111;

CIBlockElement::SetPropertyValuesEx(
    $productId,
    $iblockId,
    ['ATT_BRAND' => $newBrand]
);


/*if($element = $resCode->Fetch()) {
    $elementId = $element['ID'];
    $oldName = $element['NAME'];
    $newNameProd = $oldName . ' ' . $newName;

    $el = new CIBlockElement;
    $updateName = $el->Update($elementId, ['NAME' => $newNameProd]);

    if($updateName) {
        echo "Название элемента с кодом '{$code}' успешно обновлено на '{$newNameProd}'.\n";
    } else {
        echo "Ошибка при обновлении названия элемента с кодом '{$code}': " . $el->LAST_ERROR . "\n";   
    }
} else {
    echo "Элемент с кодом '{$code}' не найден.\n";
}*/












