<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;

class MyShopCategoryFilterComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule("iblock")) {
            ShowError("Модуль инфоблоков не подключен");
            return;
        }

        $iblockId = $this->arParams['IBLOCK_ID'];

        // Загружаем разделы
        $sections = [];
        $res = CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DEPTH_LEVEL']
        );

        while ($section = $res->Fetch()) {
            $sections[] = $section;
        }

        $this->arResult['SECTIONS'] = $sections;

        // Если выбрана категория — показываем товары
        if ($_GET['SECTION_ID']) {
            $products = [];

            $res = CIBlockElement::GetList(
                ['SORT' => 'ASC'],
                [
                    'IBLOCK_ID' => $iblockId,
                    'SECTION_ID' => intval($_GET['SECTION_ID']),
                    'ACTIVE' => 'Y'
                ],
                false,
                false,
                ['ID', 'NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PAGE_URL', 'PROPERTY_PRICE']
            );

            while ($el = $res->GetNext()) {
                $el['PREVIEW_PICTURE_SRC'] = CFile::GetPath($el['PREVIEW_PICTURE']);
                $products[] = $el;
            }

            $this->arResult['PRODUCTS'] = $products;
        }

        $this->includeComponentTemplate();
    }
}
