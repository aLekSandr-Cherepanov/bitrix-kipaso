<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    return;
}

// Получение типов инфоблоков
$arIBlockType = array();
$rsIBlockType = CIBlockType::GetList(array("sort"=>"asc"), array("ACTIVE"=>"Y"));
while ($arr=$rsIBlockType->Fetch()) {
    if ($ar=CIBlockType::GetByIDLang($arr["ID"], LANGUAGE_ID)) {
        $arIBlockType[$arr["ID"]] = "[".$arr["ID"]."] ".$ar["NAME"];
    }
}

// Получение списка инфоблоков выбранного типа
$arIBlock = array();
if (!empty($arCurrentValues["IBLOCK_TYPE"])) {
    $rsIBlock = CIBlock::GetList(array("sort"=>"asc"), array("TYPE" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE"=>"Y"));
    while($arr=$rsIBlock->Fetch()) {
        $arIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];
    }
}

// Получение всех типов инфоблоков для брендов
$arAllIBlockType = array();
$rsAllIBlockType = CIBlockType::GetList(array("sort"=>"asc"), array("ACTIVE"=>"Y"));
while ($arr=$rsAllIBlockType->Fetch()) {
    if ($ar=CIBlockType::GetByIDLang($arr["ID"], LANGUAGE_ID)) {
        $arAllIBlockType[$arr["ID"]] = "[".$arr["ID"]."] ".$ar["NAME"];
    }
}

// Получение всех инфоблоков для брендов
$arAllIBlock = array("" => "Не выбран");
$rsAllIBlock = CIBlock::GetList(array("sort"=>"asc"), array("ACTIVE"=>"Y"));
while($arr=$rsAllIBlock->Fetch()) {
    $arAllIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];
}

$arComponentParameters = array(
    "GROUPS" => array(
        "SETTINGS" => array(
            "NAME" => "Настройки компонента",
            "SORT" => 100,
        ),
        "BRANDS_SETTINGS" => array(
            "NAME" => "Настройки брендов",
            "SORT" => 200,
        ),
    ),
    "PARAMETERS" => array(
        "CACHE_TIME" => array("DEFAULT" => 36000000),
        "IBLOCK_TYPE" => array(
            "PARENT" => "SETTINGS",
            "NAME" => "Тип инфоблока",
            "TYPE" => "LIST",
            "VALUES" => $arIBlockType,
            "REFRESH" => "Y",
        ),
        "IBLOCK_ID" => array(
            "PARENT" => "SETTINGS",
            "NAME" => "ID инфоблока каталога",
            "TYPE" => "LIST",
            "VALUES" => $arIBlock,
            "REFRESH" => "Y",
        ),
        "SECTIONS_TOP_DEPTH" => array(
            "PARENT" => "SETTINGS",
            "NAME" => "Уровень родительских разделов",
            "TYPE" => "STRING",
            "DEFAULT" => "1",
        ),
        "USE_BRANDS_IBLOCK" => array(
            "PARENT" => "BRANDS_SETTINGS",
            "NAME" => "Использовать инфоблок брендов",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "N",
            "REFRESH" => "Y",
        ),
        "BRANDS_IBLOCK_ID" => array(
            "PARENT" => "BRANDS_SETTINGS",
            "NAME" => "ID инфоблока брендов",
            "TYPE" => "LIST",
            "VALUES" => $arAllIBlock,
            "DEFAULT" => "",
        ),
        "BRAND_PROPERTY_CODE" => array(
            "PARENT" => "BRANDS_SETTINGS",
            "NAME" => "Код свойства товара с идентификатором бренда",
            "TYPE" => "STRING",
            "DEFAULT" => "BRAND",
        ),
        "BRAND_CODE_PROPERTY" => array(
            "PARENT" => "BRANDS_SETTINGS",
            "NAME" => "Код свойства бренда для сопоставления (обычно CODE)",
            "TYPE" => "STRING",
            "DEFAULT" => "CODE",
        ),
        "CACHE_GROUPS" => array(
            "PARENT" => "CACHE_SETTINGS",
            "NAME" => "Учитывать права доступа",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ),
    ),
);
