<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Context;
use Bitrix\Iblock;

global $USER, $APPLICATION;

if (!isset($arParams["CACHE_TIME"])) {
	$arParams["CACHE_TIME"] = 36000000;
}

$arParams["DISPLAY_FORMAT_PROPERTIES"] = !empty($arParams["DISPLAY_FORMAT_PROPERTIES"]) ? $arParams["DISPLAY_FORMAT_PROPERTIES"] : "N";
$arParams["DISPLAY_MORE_PICTURES"] = !empty($arParams["DISPLAY_MORE_PICTURES"]) ? $arParams["DISPLAY_MORE_PICTURES"] : "N";
$arParams["DISPLAY_LAST_SECTION"] = !empty($arParams["DISPLAY_LAST_SECTION"]) ? $arParams["DISPLAY_LAST_SECTION"] : "N";
$arParams["DISPLAY_OFFERS_TABLE"] = !empty($arParams["DISPLAY_OFFERS_TABLE"]) ? $arParams["DISPLAY_OFFERS_TABLE"] : "N";
$arParams["DISPLAY_FILES_VIDEO"] = !empty($arParams["DISPLAY_FILES_VIDEO"]) ? $arParams["DISPLAY_FILES_VIDEO"] : "N";
$arParams["LAZY_LOAD_PICTURES"] = !empty($arParams["LAZY_LOAD_PICTURES"]) ? $arParams["LAZY_LOAD_PICTURES"] : "N";
$arParams["SET_CANONICAL_URL"] = !empty($arParams["SET_CANONICAL_URL"]) ? $arParams["SET_CANONICAL_URL"] : "N";
$arParams["SHOW_DEACTIVATED"] = !empty($arParams["SHOW_DEACTIVATED"]) ? $arParams["SHOW_DEACTIVATED"] : "N";
$arParams["DISPLAY_RELATED"] = !empty($arParams["DISPLAY_RELATED"]) ? $arParams["DISPLAY_RELATED"] : "N";
$arParams["DISPLAY_SIMILAR"] = !empty($arParams["DISPLAY_SIMILAR"]) ? $arParams["DISPLAY_SIMILAR"] : "N";
$arParams["DISPLAY_BRAND"] = !empty($arParams["DISPLAY_BRAND"]) ? $arParams["DISPLAY_BRAND"] : "N";

foreach ($arParams as $inx => $paramValue) {

	if (is_array($paramValue)) {
		$paramValue = $paramValue[0];
	}

	if ($paramValue == "undefined") {
		unset($arParams[$inx]);
	}

}

if ($arParams["CONVERT_CURRENCY"] != "Y") {
	if (isset($arParams["CURRENCY_ID"])) {
		unset($arParams["CURRENCY_ID"]);
	}
}

$arParams["PRODUCT_PRICE_CODE"] = empty($arParams["PRODUCT_PRICE_CODE"]) ? array() : $arParams["PRODUCT_PRICE_CODE"];
$arParams["AVAILABLE_OFFERS"] = empty($arParams["AVAILABLE_OFFERS"]) ? array() : $arParams["AVAILABLE_OFFERS"];
$arParams["PICTURE_HEIGHT"] = empty($arParams["PICTURE_HEIGHT"]) ? "200" : $arParams["PICTURE_HEIGHT"];
$arParams["PICTURE_WIDTH"] = empty($arParams["PICTURE_WIDTH"]) ? "220" : $arParams["PICTURE_WIDTH"];
$arParams["IMAGE_QUALITY"] = empty($arParams["IMAGE_QUALITY"]) ? "80" : $arParams["IMAGE_QUALITY"];
$arParams["IBLOCK_ID"] = empty($arParams["IBLOCK_ID"]) ?: $arParams["IBLOCK_ID"];

if (empty($arParams["PRODUCT_ID"])) {
	ShowError("product id not set!");
	return 0;
}

if (empty($arParams["IBLOCK_ID"])) {
	ShowError("iblock id not set!");
	return 0;
}

$cacheID = array(
	"NAME" => "ELEMENT_FULL_LIST",
	"PRODUCT_PRICE_CODE" => implode(",", $arParams["PRODUCT_PRICE_CODE"]),
	"PICTURE_HEIGHT" => floatval($arParams["PICTURE_HEIGHT"]),
	"HIDE_NOT_AVAILABLE" => $arParams["HIDE_NOT_AVAILABLE"],
	"PICTURE_WIDTH" => floatval($arParams["PICTURE_WIDTH"]),
	"AVAILABLE_OFFERS" => $arParams["AVAILABLE_OFFERS"],
	"CONVERT_CURRENCY" => $arParams["CONVERT_CURRENCY"],
	"PRODUCT_ID" => floatval($arParams["PRODUCT_ID"]),
	"CURRENCY_ID" => $arParams["CURRENCY_ID"],
	"USER_GROUPS" => $USER->GetGroups(),
	"SITE_ID" => SITE_ID
);

$cacheDir = implode(
	"/",
	[
		SITE_ID,
		'dresscode',
		'catalog.item'
	]
);

$obExtraCache = new CPHPCache();

if (
	$arParams["CACHE_TYPE"] != "N" &&
	$obExtraCache->InitCache($arParams["CACHE_TIME"], serialize($cacheID), $cacheDir)
) {
	$arResult = $obExtraCache->GetVars();
	$arResult["FROM_CACHE"] = "Y";
} elseif ($obExtraCache->StartDataCache()) {

	if (
		!\Bitrix\Main\Loader::includeModule("dw.deluxe")
		|| !\Bitrix\Main\Loader::includeModule("iblock")
		|| !\Bitrix\Main\Loader::includeModule("highloadblock")
		|| !\Bitrix\Main\Loader::includeModule("catalog")
		|| !\Bitrix\Main\Loader::includeModule("sale")
		|| !\Bitrix\Main\Loader::includeModule("currency")
	) {

		$obExtraCache->AbortDataCache();
		ShowError("modules not installed!");
		return 0;

	}

	$opCurrency = ($arParams["CONVERT_CURRENCY"] == "Y" && !empty($arParams["CURRENCY_ID"])) ? $arParams["CURRENCY_ID"] : NULL;

	$arElement = array();
	$arResult = array();
	$arElement["FROM_CACHE"] = "N";

	$skuParentProduct = CCatalogSku::GetProductInfo($arParams["PRODUCT_ID"]);
	$arContainOffers = CCatalogSKU::getExistOffers($arParams["PRODUCT_ID"], $arParams["IBLOCK_ID"]);
	$productContainOffers = !empty($arContainOffers) ? !empty($arContainOffers[$arParams["PRODUCT_ID"]]) : false;

	if (!empty($skuParentProduct)) {
		$arElement["PARENT_PRODUCT_ID"] = $skuParentProduct["ID"];
		$arElement["PARENT_PRODUCT_IBLOCK_ID"] = $skuParentProduct["IBLOCK_ID"];
	}

	$opIblockId = empty($skuParentProduct) ? $arParams["IBLOCK_ID"] : $arElement["PARENT_PRODUCT_IBLOCK_ID"];
	$opProductId = empty($skuParentProduct) ? $arParams["PRODUCT_ID"] : $arElement["PARENT_PRODUCT_ID"];

	$arSelect = array(
		"ID",
		"NAME",
		"CODE",
		"TAGS",
		"ACTIVE",
		"TIMESTAMP_X",
		"PREVIEW_TEXT",
		"PREVIEW_TEXT_TYPE",
		"DETAIL_TEXT",
		"DETAIL_TEXT_TYPE",
		"DATE_CREATE",
		"IBLOCK_ID",
		"IBLOCK_TYPE",
		"DATE_MODIFY",
		"DATE_ACTIVE_TO",
		"DETAIL_PICTURE",
		"DATE_ACTIVE_FROM",
		"CATALOG_QUANTITY",
		"DETAIL_PAGE_URL",
		"IBLOCK_SECTION_ID",
		"CATALOG_MEASURE",
		"CATALOG_AVAILABLE",
		"CATALOG_SUBSCRIBE",
		"CATALOG_QUANTITY_TRACE",
		"CATALOG_CAN_BUY_ZERO",
		"CANONICAL_PAGE_URL"
	);

	if (!empty($skuParentProduct) || !empty($productContainOffers)) {

		$arFilter = array(
			"ID" => $opProductId,
			"ACTIVE_DATE" => "Y",
			"ACTIVE" => "Y"
		);

		if (!empty($arParams["SHOW_DEACTIVATED"]) && $arParams["SHOW_DEACTIVATED"] == "Y") {
			$arFilter["ACTIVE_DATE"] = "";
			$arFilter["ACTIVE"] = "";
		}

		$rsBaseProduct = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		if ($oBaseProduct = $rsBaseProduct->GetNextElement()) {

			$arElement["PARENT_PRODUCT"] = $oBaseProduct->GetFields();
			$arElement["PARENT_PRODUCT"]["PROPERTIES"] = $oBaseProduct->GetProperties(array("sort" => "asc", "name" => "asc"), array("EMPTY" => "N"));

			$seoValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($arElement["PARENT_PRODUCT"]["IBLOCK_ID"], $arElement["PARENT_PRODUCT"]["ID"]);
			$arElement["PARENT_PRODUCT"]["IPROPERTY_VALUES"] = $seoValues->getValues();

			$arElement["IBLOCK_ID"] = $arElement["PARENT_PRODUCT"]["IBLOCK_ID"];
			$arElement["IBLOCK_SECTION_ID"] = $arElement["PARENT_PRODUCT"]["IBLOCK_SECTION_ID"];
			$arElement["NAME"] = $arElement["PARENT_PRODUCT"]["NAME"];
			$arElement["DETAIL_PAGE_URL"] = $arElement["PARENT_PRODUCT"]["DETAIL_PAGE_URL"];
			$arElement["PREVIEW_TEXT"] = $arElement["PARENT_PRODUCT"]["PREVIEW_TEXT"];
			$arElement["~PREVIEW_TEXT"] = $arElement["PARENT_PRODUCT"]["~PREVIEW_TEXT"];
			$arElement["PREVIEW_TEXT_TYPE"] = $arElement["PARENT_PRODUCT"]["PREVIEW_TEXT"];
			$arElement["DETAIL_TEXT"] = $arElement["PARENT_PRODUCT"]["DETAIL_TEXT"];
			$arElement["~DETAIL_TEXT"] = $arElement["PARENT_PRODUCT"]["~DETAIL_TEXT"];
			$arElement["DETAIL_TEXT_TYPE"] = $arElement["PARENT_PRODUCT"]["DETAIL_TEXT_TYPE"];

			if (!empty($arElement["PARENT_PRODUCT"]["DETAIL_PICTURE"])) {
				$arElement["PICTURE"] = CFile::ResizeImageGet($arElement["PARENT_PRODUCT"]["DETAIL_PICTURE"], array("width" => $arParams["PICTURE_WIDTH"], "height" => $arParams["PICTURE_HEIGHT"]), BX_RESIZE_IMAGE_PROPORTIONAL, false, false, false, $arParams["IMAGE_QUALITY"]);
			}

			if (!empty($arElement["PARENT_PRODUCT"]["CANONICAL_PAGE_URL"])) {
				$arElement["CANONICAL_PAGE_URL"] = $arElement["PARENT_PRODUCT"]["CANONICAL_PAGE_URL"];
			}

			$arButtons = CIBlock::GetPanelButtons(
				$arElement["PARENT_PRODUCT"]["IBLOCK_ID"],
				$arElement["PARENT_PRODUCT"]["ID"],
				$arElement["PARENT_PRODUCT"]["IBLOCK_SECTION_ID"],
				[
					"RETURN_URL" => $arElement["DETAIL_PAGE_URL"],
					"SECTION_BUTTONS" => true,
					"SESSID" => true,
					"CATALOG" => true
				]
			);

			$arElement["PARENT_PRODUCT"]["EDIT_LINK"] = $arButtons["edit"]["edit_element"]["ACTION_URL"];
			$arElement["PARENT_PRODUCT"]["DELETE_LINK"] = $arButtons["edit"]["delete_element"]["ACTION_URL"];

		}

	}

	if (!empty($skuParentProduct) || !empty($productContainOffers)) {

		$arOffersSkuInfo = CCatalogSKU::GetInfoByProductIBlock($opIblockId);
		$opFirstOfferId = !empty($skuParentProduct) ? $arParams["PRODUCT_ID"] : false;
		$opOffersFilterId = !empty($arParams["AVAILABLE_OFFERS"]) ? $arParams["AVAILABLE_OFFERS"] : false;

		$arSkuParams = array(
			"PRODUCT_PRICE_CODE" => $arParams["PRODUCT_PRICE_CODE"],
			"HIDE_NOT_AVAILABLE" => $arParams["HIDE_NOT_AVAILABLE"],
			"SHOW_DEACTIVATED" => $arParams["SHOW_DEACTIVATED"],
			"PICTURE_HEIGHT" => $arParams["PICTURE_HEIGHT"],
			"PICTURE_WIDTH" => $arParams["PICTURE_WIDTH"],
			"IMAGE_QUALITY" => $arParams["IMAGE_QUALITY"]
		);

		if ($arElement["PARENT_PRODUCT"]["ACTIVE"] == "Y") {
			$arParams["SHOW_DEACTIVATED"] = "N";
			$arSkuParams["SHOW_DEACTIVATED"] = "N";
		}

		if (!empty($arParams["PRODUCT_SKU_FILTER"])) {

			if (isset($arParams["PRODUCT_SKU_FILTER"]["SECTION_ID"])) {
				unset($arParams["PRODUCT_SKU_FILTER"]["SECTION_ID"]);
			}

			if (isset($arParams["PRODUCT_SKU_FILTER"]["PROPERTY_ATT_BRAND"])) {
				unset($arParams["PRODUCT_SKU_FILTER"]["PROPERTY_ATT_BRAND"]);
			}

			if (isset($arParams["PRODUCT_SKU_FILTER"]["?TAGS"])) {
				unset($arParams["PRODUCT_SKU_FILTER"]["?TAGS"]);
			}

			if (isset($arParams["PRODUCT_SKU_FILTER"]["IBLOCK_ID"])) {
				unset($arParams["PRODUCT_SKU_FILTER"]["IBLOCK_ID"]);
			}

			$arSkuParams["FILTER"] = $arParams["PRODUCT_SKU_FILTER"];

		}

		$arSkuOffersFromProduct = DwSkuOffers::getSkuFromProduct(
			$opProductId,
			$opIblockId,
			$opOffersFilterId,
			$opFirstOfferId,
			$arOffersSkuInfo,
			$arSkuParams,
			$opCurrency
		);

		if (!empty($arSkuOffersFromProduct)) {

			if (!empty($arElement)) {

				if (empty($arElement["PARENT_PRODUCT"]["PROPERTIES"])) {
					$arElement["PARENT_PRODUCT"]["PROPERTIES"] = [];
				}

				if (empty($arSkuOffersFromProduct["PROPERTIES"])) {
					$arSkuOffersFromProduct["PROPERTIES"] = [];
				}

				$arSkuOffersFromProduct["PROPERTIES"] = array_merge($arElement["PARENT_PRODUCT"]["PROPERTIES"], $arSkuOffersFromProduct["PROPERTIES"]);
				$arElement = array_merge($arElement, $arSkuOffersFromProduct);

			}

			$arElement["SKU_INFO"] = $arOffersSkuInfo;

			if (empty($arElement["PICTURE"])) {
				$arElement["PICTURE"]["src"] = SITE_TEMPLATE_PATH . "/images/empty.svg";
			}

			global $CACHE_MANAGER;

			$CACHE_MANAGER->StartTagCache($cacheDir);
			$CACHE_MANAGER->RegisterTag("iblock_id_" . $arElement["IBLOCK_ID"]);

			if (!empty($arElement["PARENT_PRODUCT_IBLOCK_ID"])) {
				$CACHE_MANAGER->RegisterTag("iblock_id_" . $arElement["PARENT_PRODUCT_IBLOCK_ID"]);
			}

			$CACHE_MANAGER->EndTagCache();

		}

		else {

			$obExtraCache->AbortDataCache();
			$arElement = array();

			if ($arParams["DETAIL_ELEMENT"] == "Y") {
				Iblock\Component\Tools::process404(
					trim($arParams["MESSAGE_404"]) ?: GetMessage("CATALOG_ITEM_NOT_FOUND"),
					true,
					$arParams["SET_STATUS_404"] === "Y",
					$arParams["SHOW_404"] === "Y",
					$arParams["FILE_404"]
				);
			}

		}

	}

	else {

		$arFilter = array(
			"ID" => $arParams["PRODUCT_ID"],
			"ACTIVE_DATE" => "Y",
			"ACTIVE" => "Y"
		);

		if (!empty($arParams["SHOW_DEACTIVATED"]) && $arParams["SHOW_DEACTIVATED"] == "Y") {
			$arFilter["ACTIVE_DATE"] = "";
			$arFilter["ACTIVE"] = "";
		}

		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		if ($ob = $res->GetNextElement()) {

			$arElement = array_merge($arElement, $ob->GetFields());
			$arElement["PROPERTIES"] = $ob->GetProperties(array("sort" => "asc", "name" => "asc"), array("EMPTY" => "N"));
			$arElement["DISPLAY_PROPERTIES"] = array();

			if (!empty($skuParentProduct)) {
				$arElement["PROPERTIES"] = array_merge($arElement["PARENT_PRODUCT"]["PROPERTIES"], $arElement["PROPERTIES"]);
			}

			$mainIblockId = !empty($arElement["PARENT_PRODUCT_IBLOCK_ID"]) ? $arElement["PARENT_PRODUCT_IBLOCK_ID"] : $arElement["IBLOCK_ID"];
			$arElement["SKU_INFO"] = CCatalogSKU::GetInfoByProductIBlock($mainIblockId);

			if (!empty($arElement["DETAIL_PICTURE"])) {
				$arElement["PICTURE"] = CFile::ResizeImageGet(
					$arElement["DETAIL_PICTURE"],
					array(
						"width" => $arParams["PICTURE_WIDTH"],
						"height" => $arParams["PICTURE_HEIGHT"]
					),
					BX_RESIZE_IMAGE_PROPORTIONAL,
					false,
					false,
					false,
					$arParams["IMAGE_QUALITY"]
				);
			} else {
				if (empty($arElement["PICTURE"])) {
					$arElement["PICTURE"]["src"] = SITE_TEMPLATE_PATH . "/images/empty.svg";
				}
			}

			$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW"] = array();
			$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW_FILTER"] = array();

			if (!empty($arParams["PRODUCT_PRICE_CODE"])) {

				$arPricesInfo = DwPrices::getPriceInfo($arParams["PRODUCT_PRICE_CODE"], $arElement["IBLOCK_ID"]);
				if (!empty($arPricesInfo)) {
					$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW"] = $arPricesInfo["ALLOW"];
					$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW_FILTER"] = $arPricesInfo["ALLOW_FILTER"];
				}

			}

			$arElement["PRICE"] = DwPrices::getPricesByProductId(
				$arElement["ID"],
				$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW"],
				$arElement["EXTRA_SETTINGS"]["PRODUCT_PRICE_ALLOW_FILTER"],
				$arParams["PRODUCT_PRICE_CODE"],
				$arElement["IBLOCK_ID"],
				$opCurrency
			);

			$arElement["EXTRA_SETTINGS"]["COUNT_PRICES"] = $arElement["PRICE"]["COUNT_PRICES"];
			$arElement["EXTRA_SETTINGS"]["CURRENCY"] = empty($opCurrency) ? $arElement["PRICE"]["RESULT_PRICE"]["CURRENCY"] : $opCurrency;

			$rsComplect = CCatalogProductSet::getList(
				array("SORT" => "ASC"),
				array(
					"TYPE" => 1,
					"OWNER_ID" => $arElement["ID"],
					"!ITEM_ID" => $arElement["ID"]
				),
				false,
				false,
				array("*")
			);

			if (!$arComplectItem = $rsComplect->Fetch()) {
				$arElement["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] = 0;
				$rsStore = CCatalogStoreProduct::GetList(array(), array("PRODUCT_ID" => $arElement["ID"]), false, false, array("ID", "AMOUNT"));
				while ($arNextStore = $rsStore->GetNext()) {
					$arElement["EXTRA_SETTINGS"]["STORES"][] = $arNextStore;
					if ($arNextStore["AMOUNT"] > $arElement["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"]) {
						$arElement["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] = $arNextStore["AMOUNT"];
					}
				}
			}

			$rsMeasure = CCatalogMeasure::getList(
				array(),
				array(
					"ID" => $arElement["CATALOG_MEASURE"]
				),
				false,
				false,
				false
			);

			while ($arNextMeasure = $rsMeasure->Fetch()) {
				$arElement["EXTRA_SETTINGS"]["MEASURES"][$arNextMeasure["ID"]] = $arNextMeasure;
			}

			$arElement["EXTRA_SETTINGS"]["BASKET_STEP"] = 1;

			$rsMeasureRatio = CCatalogMeasureRatio::getList(
				array(),
				array("PRODUCT_ID" => floatval($arElement["ID"])),
				false,
				false,
				array()
			);

			if ($arProductMeasureRatio = $rsMeasureRatio->Fetch()) {
				if (!empty($arProductMeasureRatio["RATIO"])) {
					$arElement["EXTRA_SETTINGS"]["BASKET_STEP"] = $arProductMeasureRatio["RATIO"];
				}
			}

			$seoValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($arElement["IBLOCK_ID"], $arElement["ID"]);
			$arElement["IPROPERTY_VALUES"] = $seoValues->getValues();

			$arButtons = CIBlock::GetPanelButtons(
				$arElement["IBLOCK_ID"],
				$arElement["ID"],
				$arElement["IBLOCK_SECTION_ID"],
				[
					"RETURN_URL" => $arElement["DETAIL_PAGE_URL"],
					"SECTION_BUTTONS" => true,
					"SESSID" => true,
					"CATALOG" => true
				]
			);

			$arElement["EDIT_LINK"] = $arButtons["edit"]["edit_element"]["ACTION_URL"];
			$arElement["DELETE_LINK"] = $arButtons["edit"]["delete_element"]["ACTION_URL"];

			global $CACHE_MANAGER;
			$CACHE_MANAGER->StartTagCache($cacheDir);
			$CACHE_MANAGER->RegisterTag("iblock_id_" . $arElement["IBLOCK_ID"]);
			$CACHE_MANAGER->EndTagCache();

		}

		else {

			if ($arParams["DETAIL_ELEMENT"] == "Y") {
				Iblock\Component\Tools::process404(
					trim($arParams["MESSAGE_404"]) ?: GetMessage("CATALOG_ITEM_NOT_FOUND"),
					true,
					$arParams["SET_STATUS_404"] === "Y",
					$arParams["SHOW_404"] === "Y",
					$arParams["FILE_404"]
				);
			}

			$obExtraCache->AbortDataCache();

		}

	}

	if (!empty($arElement)) {

		$arElement["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"] = $this->randString();
		if (!empty($arElement["PROPERTIES"]["TIMER_DATE"]["VALUE"])) {
			$dateDiff = MakeTimeStamp($arElement["PROPERTIES"]["TIMER_DATE"]["VALUE"], "DD.MM.YYYY HH:MI:SS") - time();
			$arElement["EXTRA_SETTINGS"]["SHOW_TIMER"] = $dateDiff > 0;
		} elseif (!empty($arElement["PROPERTIES"]["TIMER_LOOP"]["VALUE"])) {
			$arElement["EXTRA_SETTINGS"]["SHOW_TIMER"] = true;
		} else {
			$arElement["EXTRA_SETTINGS"]["SHOW_TIMER"] = false;
		}

		$obExtraCache->EndDataCache($arElement);

		unset($obExtraCache);

		$arResult = $arElement;
		unset($arElement);

	}

}

if (
	!\Bitrix\Main\Loader::includeModule("dw.deluxe") ||
	!\Bitrix\Main\Loader::includeModule("sale") ||
	!\Bitrix\Main\Loader::includeModule("iblock")
) {
	ShowError("modules not installed!");
	return 0;
}

$extraParams = array(
	"DISPLAY_FORMAT_PROPERTIES" => $arParams["DISPLAY_FORMAT_PROPERTIES"],
	"DISPLAY_MORE_PICTURES" => $arParams["DISPLAY_MORE_PICTURES"],
	"DISPLAY_OFFERS_TABLE" => $arParams["DISPLAY_OFFERS_TABLE"],
	"DISPLAY_FILES_VIDEO" => $arParams["DISPLAY_FILES_VIDEO"],
	"DISPLAY_RELATED" => $arParams["DISPLAY_RELATED"],
	"DISPLAY_SIMILAR" => $arParams["DISPLAY_SIMILAR"],
	"DISPLAY_BRAND" => $arParams["DISPLAY_BRAND"]
);

$extraContent = DwItemInfo::get_extra_content($arParams["CACHE_TIME"], $arParams["CACHE_TYPE"], $cacheID, $cacheDir, $extraParams, $arParams, $arResult, $opCurrency);

if (!empty($extraContent)) {
	$arResult = $extraContent;
}

if (!empty($arResult)) {

	$elementTitle = (!empty($arResult["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"]) ? $arResult["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"] : (!empty($arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"]) ? $arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"] : $arResult["NAME"]));
	$elementBrowserTitle = (!empty($arResult["IPROPERTY_VALUES"]["ELEMENT_META_TITLE"]) ? $arResult["IPROPERTY_VALUES"]["ELEMENT_META_TITLE"] : (!empty($arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_TITLE"]) ? $arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_TITLE"] : $arResult["NAME"]));
	$elementMetaKeywords = (!empty($arResult["IPROPERTY_VALUES"]["ELEMENT_META_KEYWORDS"]) ? $arResult["IPROPERTY_VALUES"]["ELEMENT_META_KEYWORDS"] : (!empty($arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_KEYWORDS"]) ? $arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_KEYWORDS"] : ""));
	$elementMetaDescription = (!empty($arResult["IPROPERTY_VALUES"]["ELEMENT_META_DESCRIPTION"]) ? $arResult["IPROPERTY_VALUES"]["ELEMENT_META_DESCRIPTION"] : (!empty($arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_DESCRIPTION"]) ? $arResult["PARENT_PRODUCT"]["IPROPERTY_VALUES"]["ELEMENT_META_DESCRIPTION"] : ""));

	if (!empty($arParams["SET_TITLE"]) && $arParams["SET_TITLE"] == "Y") {
		$arTitleOptions = array(
			"ADMIN_EDIT_LINK" => $arButtons["submenu"]["edit_element"]["ACTION"],
			"PUBLIC_EDIT_LINK" => $arButtons["edit"]["edit_element"]["ACTION"],
			"COMPONENT_NAME" => $this->getName(),
		);
	}

	if (!empty($arParams["SET_TITLE"]) && $arParams["SET_TITLE"] == "Y" && !empty($elementTitle)) {
		$APPLICATION->SetTitle($elementTitle, $arTitleOptions);
	}

	if ($arParams["SET_BROWSER_TITLE"] == "Y") {
		if (!empty($elementBrowserTitle)) {
			$APPLICATION->SetPageProperty("title", $elementBrowserTitle, $arTitleOptions);
		}
	}

	if ($arParams["SET_META_KEYWORDS"] == "Y") {
		$APPLICATION->SetPageProperty("keywords", $elementMetaKeywords, $arTitleOptions);
	}

	if ($arParams["SET_META_DESCRIPTION"] == "Y") {
		$APPLICATION->SetPageProperty("description", $elementMetaDescription, $arTitleOptions);
	}

	if ($arParams["ADD_SECTIONS_CHAIN"] == "Y") {

		if (empty($arParams["SECTION_ID"]) && !empty($arParams["SECTION_CODE"])) {
			$dbSection = CIBlockSection::GetList(array(), array("CODE" => $arParams["SECTION_CODE"]), false);
			if ($arSection = $dbSection->GetNext()) {
				$arResult["SECTION"] = $arSection;
				$arParams["SECTION_ID"] = $arResult["SECTION"]["ID"];
			}
		}

		if (!empty($arResult["SECTION_PATH_LIST"])) {
			foreach ($arResult["SECTION_PATH_LIST"] as $arPath) {

				if (!empty($arPath["UF_SHOW_SKU_TABLE"])) {
					$arResult["SHOW_SKU_TABLE"] = $arPath["UF_SHOW_SKU_TABLE"];
				}

				$ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($arParams["IBLOCK_ID"], $arPath["ID"]);
				$arPath["IPROPERTY_VALUES"] = $ipropValues->getValues();

				if (!empty($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"])) {
					$APPLICATION->AddChainItem($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"], $arPath["~SECTION_PAGE_URL"]);
				} else {
					$APPLICATION->AddChainItem($arPath["NAME"], $arPath["~SECTION_PAGE_URL"]);
				}

			}
		}

	}

	if ($arParams["SET_CANONICAL_URL"] == "Y") {

		if (!empty($arResult["PARENT_PRODUCT"]["CANONICAL_PAGE_URL"])) {
			$arResult["CANONICAL_PAGE_URL"] = $arResult["PARENT_PRODUCT"]["CANONICAL_PAGE_URL"];
		}

		if (!empty($arResult["CANONICAL_PAGE_URL"])) {
			$APPLICATION->AddHeadString('<link href="' . $arResult["CANONICAL_PAGE_URL"] . '" rel="canonical" />', true);
		}

	}

	if ($arParams["ADD_OPEN_GRAPH"] == "Y") {
		$APPLICATION->AddHeadString('<meta property="og:title" content="' . $arResult["NAME"] . '" />');
		$APPLICATION->AddHeadString('<meta property="og:description" content="' . htmlspecialcharsbx($arResult["PREVIEW_TEXT"]) . '" />');
		$APPLICATION->AddHeadString('<meta property="og:url" content="' . (CMain::IsHTTPS() ? "https://" : "http://") . SITE_SERVER_NAME . $arResult["DETAIL_PAGE_URL"] . '" />');
		$APPLICATION->AddHeadString('<meta property="og:type" content="website" />');
		if (!empty($arResult["IMAGES"][0]["LARGE_IMAGE"]["SRC"])) {
			$APPLICATION->AddHeadString('<meta property="og:image" content="' . (CMain::IsHTTPS() ? "https://" : "http://") . SITE_SERVER_NAME . $arResult["IMAGES"][0]["LARGE_IMAGE"]["SRC"] . '" />');
		}
	}

	if ($arParams["ADD_ELEMENT_CHAIN"]) {
		$APPLICATION->AddChainItem($elementTitle);
	}

	if ($arParams["SET_LAST_MODIFIED"] && $arResult["TIMESTAMP_X"]) {
		Context::getCurrent()->getResponse()->setLastModified(DateTime::createFromUserTime($arResult["TIMESTAMP_X"]));
	}

	if ($arParams["SET_VIEWED_IN_COMPONENT"] == "Y") {

		$productId = !empty($arResult["PARENT_PRODUCT"]["ID"]) ? $arResult["PARENT_PRODUCT"]["ID"] : $arResult["ID"];

		CIBlockElement::CounterInc($productId);

		$_SESSION["VIEWED_ENABLE"] = "Y";

		$arFields = array(
			"IBLOCK_ID" => $arParams["IBLOCK_ID"],
			"PRODUCT_ID" => $arResult["ID"],
			"MODULE" => "catalog",
			"LID" => SITE_ID
		);

		CSaleViewedProduct::Add($arFields);

		\Bitrix\Catalog\CatalogViewedProductTable::refresh(
			$arResult["ID"],
			CSaleBasket::GetBasketUserID(),
			SITE_ID,
			$arResult["PARENT_PRODUCT"]["ID"]
		);

	}

	$this->setResultCacheKeys(array("CANONICAL_PAGE_URL"));
	$this->IncludeComponentTemplate();
}
