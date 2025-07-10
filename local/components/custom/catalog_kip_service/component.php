<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;

// Подключаем необходимые модули
if (!Loader::includeModule('iblock')) {
    ShowError('Модуль "Информационные блоки" не установлен');
    return;
}

// Проверка и подготовка входных параметров
if (!isset($arParams["CACHE_TYPE"])) $arParams["CACHE_TYPE"] = "A";
if (!isset($arParams["CACHE_TIME"])) $arParams["CACHE_TIME"] = 36000000;
if (!isset($arParams["SECTIONS_TOP_DEPTH"])) $arParams["SECTIONS_TOP_DEPTH"] = 1;
if (!isset($arParams["BRAND_PROPERTY_CODE"])) $arParams["BRAND_PROPERTY_CODE"] = "BRAND";

// Проверка обязательных входных параметров
if (empty($arParams["IBLOCK_ID"])) {
    ShowError('Не указан ID инфоблока');
    return;
}

// Подготовка кеша
$cacheId = serialize(array(
    $arParams["IBLOCK_ID"],
    $arParams["SECTIONS_TOP_DEPTH"],
    $arParams["BRAND_PROPERTY_CODE"],
    ($arParams["CACHE_GROUPS"] === "N" ? false : $USER->GetGroups())
));

// Параметры кеширования
if ($this->startResultCache(false, $cacheId))
{
    try
    {
        $arResult = array();

        // Получаем верхние категории (родительские разделы)
        $topSectionDepth = intval($arParams["SECTIONS_TOP_DEPTH"]) > 0 ? intval($arParams["SECTIONS_TOP_DEPTH"]) : 1;
        
        $arFilter = array(
            "IBLOCK_ID" => $arParams["IBLOCK_ID"],
            "ACTIVE" => "Y",
            "DEPTH_LEVEL" => $topSectionDepth,
            "GLOBAL_ACTIVE" => "Y"
        );
        
        $arOrder = array("SORT" => "ASC", "NAME" => "ASC");
        $arSelect = array("ID", "NAME", "DESCRIPTION", "PICTURE", "SECTION_PAGE_URL");
        
        $rsSections = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
        $topSections = array();
        
        while ($arSection = $rsSections->GetNext())
        {
            $sectionId = $arSection["ID"];
            $topSections[$sectionId] = array(
                "ID" => $sectionId,
                "NAME" => $arSection["NAME"],
                "DESCRIPTION" => $arSection["DESCRIPTION"],
                "PICTURE" => !empty($arSection["PICTURE"]) ? CFile::GetFileArray($arSection["PICTURE"]) : false,
                "SECTION_PAGE_URL" => $arSection["SECTION_PAGE_URL"],
                "SUBSECTIONS" => array(),
                "BRANDS" => array()
            );
            
            // Получаем подразделы для каждой верхней категории
            $subSectionFilter = array(
                "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                "ACTIVE" => "Y",
                "SECTION_ID" => $sectionId,
                "GLOBAL_ACTIVE" => "Y"
            );
            
            $rsSubSections = CIBlockSection::GetList($arOrder, $subSectionFilter, false, $arSelect);
            
            while ($arSubSection = $rsSubSections->GetNext())
            {
                $topSections[$sectionId]["SUBSECTIONS"][] = array(
                    "ID" => $arSubSection["ID"],
                    "NAME" => $arSubSection["NAME"],
                    "DESCRIPTION" => $arSubSection["DESCRIPTION"],
                    "PICTURE" => !empty($arSubSection["PICTURE"]) ? CFile::GetFileArray($arSubSection["PICTURE"]) : false,
                    "SECTION_PAGE_URL" => $arSubSection["SECTION_PAGE_URL"]
                );
            }
            
            // Получаем бренды для каждой категории
            if (!empty($arParams["BRAND_PROPERTY_CODE"]))
            {
                // Проверяем, используется ли инфоблок брендов
                if ($arParams["USE_BRANDS_IBLOCK"] === "Y" && !empty($arParams["BRANDS_IBLOCK_ID"]))
                {
                    // Код для работы с инфоблоком брендов отсутствует или был удален
                } 
                else 
                {
                    // Старый способ - просто собираем значения брендов из свойств товаров
                    $brandFilter = array(
                        "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                        "ACTIVE" => "Y",
                        "SECTION_ID" => $sectionId,
                        "INCLUDE_SUBSECTIONS" => "Y"
                    );
                    
                    $brandSelect = array("ID", "IBLOCK_ID", "PROPERTY_" . $arParams["BRAND_PROPERTY_CODE"]);
                    
                    // Добавляем проверку на существование свойства
                    $rsProperty = CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $arParams["IBLOCK_ID"], "CODE" => $arParams["BRAND_PROPERTY_CODE"]));
                    if ($rsProperty->SelectedRowsCount() > 0) 
                    {
                        $rsElements = CIBlockElement::GetList(array(), $brandFilter, array("PROPERTY_" . $arParams["BRAND_PROPERTY_CODE"]), false, $brandSelect);
                        
                        $brands = array();
                        while ($arElement = $rsElements->GetNext()) 
                        {
                            $brandValue = $arElement["PROPERTY_" . $arParams["BRAND_PROPERTY_CODE"] . "_VALUE"];
                            if (!empty($brandValue) && !isset($brands[$brandValue])) 
                            {
                                $brands[$brandValue] = array(
                                    "CODE" => $brandValue,
                                    "NAME" => $brandValue,
                                    "PICTURE" => false
                                );
                            }
                        }
                        
                        $topSections[$sectionId]["BRANDS"] = $brands;
                    }
                }
            }
        }
        
        $arResult["SECTIONS"] = $topSections;
        
        $this->SetResultCacheKeys(array());
        $this->IncludeComponentTemplate();
    }
    catch (Exception $e)
    {
        $this->abortResultCache();
        ShowError($e->getMessage());
    }
}
