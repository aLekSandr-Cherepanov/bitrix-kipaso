<?php
define("INDEX_PAGE", "Y");
define("MAIN_PAGE", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("keywords", "DELUXE");
$APPLICATION->SetPageProperty("description", "DELUXE");
$APPLICATION->SetTitle("DELUXE");

//include module
\Bitrix\Main\Loader::includeModule("dw.deluxe");

//vars
$catalogIblockId = null;
$arPriceCodes = array();

//get template settings
$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
if(!empty($arTemplateSettings)){
	$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
	$arPriceCodes = explode(", ", $arTemplateSettings["TEMPLATE_PRICE_CODES"]);
}

$APPLICATION->IncludeComponent(
	"dresscode:slider", 
	"promoSlider", 
	array(
		"IBLOCK_TYPE" => "slider",
		"IBLOCK_ID" => "8",
		"CACHE_TYPE" => "Y",
		"CACHE_TIME" => "3600000",
		"PICTURE_WIDTH" => "1920",
		"PICTURE_HEIGHT" => "1080",
		"COMPONENT_TEMPLATE" => ".default"
	),
	false
);

$APPLICATION->IncludeComponent("bitrix:news.list", "indexBanners", array(
	"ACTIVE_DATE_FORMAT" => "d.m.Y",
	"ADD_SECTIONS_CHAIN" => "N",
	"AJAX_MODE" => "N",
	"AJAX_OPTION_ADDITIONAL" => "",
	"AJAX_OPTION_HISTORY" => "N",
	"AJAX_OPTION_JUMP" => "N",
	"AJAX_OPTION_STYLE" => "Y",
	"CACHE_FILTER" => "N",
	"CACHE_GROUPS" => "Y",
	"CACHE_TIME" => "36000000",
	"CACHE_TYPE" => "A",
	"CHECK_DATES" => "Y",
	"DETAIL_URL" => "",
	"DISPLAY_BOTTOM_PAGER" => "N"
	),
	false
);

// Добавляем вызов нашего аккордеона категорий
$APPLICATION->IncludeComponent(
	"custom:accordion_catalog",
	"",
	Array(
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"IBLOCK_TYPE" => "catalog",
		"IBLOCK_ID" => $catalogIblockId,
		"SECTIONS_TOP_DEPTH" => "1",
		
		// Настройки брендов
		"USE_BRANDS_IBLOCK" => "Y",
		"BRANDS_IBLOCK_ID" => "18", // ID инфоблока с брендами
		"BRAND_PROPERTY_CODE" => "BRAND", 
		"BRAND_CODE_PROPERTY" => "CODE",
		
		"CACHE_GROUPS" => "Y"
	)
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
