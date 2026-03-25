<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

global $USER;

if (!isset($arParams["CACHE_TIME"])) {
	$arParams["CACHE_TIME"] = 36000000;
}

$arParams["DISPLAY_PICTURE_COLUMN"] = (empty($arParams["DISPLAY_PICTURE_COLUMN"]) ? "Y" : $arParams["DISPLAY_PICTURE_COLUMN"]);
$arParams["PAGER_NAV_HEADING"] = (empty($arParams["PAGER_NAV_HEADING"]) ? GetMessage("PAGINATION_NAV_HEADING") : $arParams["PAGER_NAV_HEADING"]);
$arParams["NAV_COUNT_ELEMENTS"] = (empty($arParams["NAV_COUNT_ELEMENTS"]) ? 10 : $arParams["NAV_COUNT_ELEMENTS"]);
$arParams["PAGER_SHOW_ALWAYS"] = (empty($arParams["PAGER_SHOW_ALWAYS"]) ? "N" : $arParams["PAGER_SHOW_ALWAYS"]);
$arParams["PAGER_TEMPLATE"] = (empty($arParams["PAGER_TEMPLATE"]) ? ".default" : $arParams["PAGER_TEMPLATE"]);
$arParams["PAGER_NUM"] = (empty($arParams["PAGER_NUM"]) ? 1 : $arParams["PAGER_NUM"]);
$arParams["PICTURE_WIDTH"] = (empty($arParams["PICTURE_WIDTH"]) ? 100 : $arParams["PICTURE_WIDTH"]);
$arParams["PICTURE_HEIGHT"] = (empty($arParams["PICTURE_HEIGHT"]) ? 100 : $arParams["PICTURE_HEIGHT"]);
$arParams["PICTURE_QUALITY"] = (empty($arParams["PICTURE_QUALITY"]) ? 100 : $arParams["PICTURE_QUALITY"]);

if ($arParams["CONVERT_CURRENCY"] != "Y") {
	if (isset($arParams["CURRENCY_ID"])) {
		unset($arParams["CURRENCY_ID"]);
	}
}

$cacheID = array(
	"CONVERT_CURRENCY" => $arParams["CONVERT_CURRENCY"],
	"PRODUCT_ID" => intval($arParams["PRODUCT_ID"]),
	"CURRENCY_ID" => $arParams["CURRENCY_ID"],
	"PAGER_NUM" => $arParams["PAGER_NUM"],
	"USER_GROUPS" => $USER->GetGroups(),
	"SITE_ID" => SITE_ID,
	"COMPONENT_REV" => "offer_details_url_v2"
);

if ($this->StartResultCache($arParams["CACHE_TIME"], serialize($cacheID))) {

	if (
		!\Bitrix\Main\Loader::includeModule("dw.deluxe")
		|| !\Bitrix\Main\Loader::includeModule("iblock")
		|| !\Bitrix\Main\Loader::includeModule('highloadblock')
		|| !\Bitrix\Main\Loader::includeModule("catalog")
		|| !\Bitrix\Main\Loader::includeModule("sale")
	) {

		$this->AbortResultCache();
		ShowError("modules not installed!");
		return 0;

	}

	$arResult["ITEMS"] = array();
	$opCurrency = ($arParams["CONVERT_CURRENCY"] == "Y" && !empty($arParams["CURRENCY_ID"])) ? $arParams["CURRENCY_ID"] : NULL;
	$skuSortParams = 100;
	$arSkuPropNames = array();

	$arContainOffers = CCatalogSKU::getExistOffers($arParams["PRODUCT_ID"], $arParams["IBLOCK_ID"]);

	if (!empty($arContainOffers[$arParams["PRODUCT_ID"]])) {

		$rsProduct = CIBlockElement::GetList(
			array(),
			array(
				"IBLOCK_ID" => $arParams["IBLOCK_ID"],
				"ID" => $arParams["PRODUCT_ID"]
			),
			false,
			false,
			array("ID", "IBLOCK_ID", "NAME", "DETAIL_PICTURE", "DETAIL_PAGE_URL")
		);

		if ($arParentProduct = $rsProduct->GetNext()) {

			if (!empty($arParentProduct["DETAIL_PICTURE"])) {
				$arParentProduct["PICTURE"] = CFile::ResizeImageGet($arParentProduct["DETAIL_PICTURE"], array("width" => $arParams["PICTURE_WIDTH"], "height" => $arParams["PICTURE_HEIGHT"]), BX_RESIZE_IMAGE_PROPORTIONAL, false, false, false, $arParams["PICTURE_QUALITY"]);
			}

			$arResult["PARENT_PRODUCT"] = $arParentProduct;

		}

		$arOffersSkuInfo = CCatalogSKU::GetInfoByProductIBlock($arParams["IBLOCK_ID"]);

		$arResult["PRODUCT_PRICE_ALLOW"] = array();
		$arResult["PRODUCT_PRICE_ALLOW_FILTER"] = array();

		if (!empty($arParams["PRODUCT_PRICE_CODE"])) {

			$arPricesInfo = DwPrices::getPriceInfo($arParams["PRODUCT_PRICE_CODE"], $arOffersSkuInfo["IBLOCK_ID"]);
			if (!empty($arPricesInfo)) {
				$arResult["PRODUCT_PRICE_ALLOW"] = $arPricesInfo["ALLOW"];
				$arResult["PRODUCT_PRICE_ALLOW_FILTER"] = $arPriceType["ALLOW_FILTER"];
			}

		}

		$arSkuOffersSort = array(
			"SORT" => "ASC",
			"NAME" => "ASC"
		);

		$arSkuOffersFilter = array(
			"PROPERTY_" . $arOffersSkuInfo["SKU_PROPERTY_ID"] => $arParams["PRODUCT_ID"],
			"IBLOCK_ID" => $arOffersSkuInfo["IBLOCK_ID"],
			"INCLUDE_SUBSECTIONS" => "N",
			"ACTIVE" => "Y"
		);

		if ($arParams["HIDE_NOT_AVAILABLE"] == "Y") {
			$arSkuOffersFilter["CATALOG_AVAILABLE"] = "Y";
		}

		$arSkuOffersSelect = array(
			"ID",
			"IBLOCK_ID",
			"NAME",
			"CODE",
			"SORT",
			"DATE_CREATE",
			"DATE_MODIFY",
			"TIMESTAMP_X",
			"DATE_ACTIVE_TO",
			"DETAIL_PAGE_URL",
			"DETAIL_PICTURE",
			"PREVIEW_PICTURE",
			"DATE_ACTIVE_FROM",
			"CATALOG_QUANTITY",
			"CATALOG_MEASURE",
			"CATALOG_AVAILABLE",
			"CATALOG_SUBSCRIBE",
			"CATALOG_QUANTITY_TRACE",
			"CATALOG_CAN_BUY_ZERO",
			"CANONICAL_PAGE_URL"
		);

		$rsSkuOffers = CIBlockElement::GetList($arSkuOffersSort, $arSkuOffersFilter, false, false, $arSkuOffersSelect);

		$arResult["ROWS_ALL_COUNT"] = $rsSkuOffers->SelectedRowsCount();
		$arResult["PAGER_ENABLED"] = (($arResult["ROWS_ALL_COUNT"] - ($arParams["PAGER_NUM"] * $arParams["NAV_COUNT_ELEMENTS"])) > 0);

		$rsSkuOffers->NavStart($arParams["NAV_COUNT_ELEMENTS"], false, $arParams["PAGER_NUM"]);

		while ($arNextSkuOffer = $rsSkuOffers->GetNextElement()) {

			$arSkuFieldsMx = $arNextSkuOffer->GetFields();
			$arSkuPropertiesMx = $arNextSkuOffer->GetProperties(array("SORT" => "ASC"), array("ACTIVE" => "Y", "EMPTY" => "N"));

			$arSkuPropFiltred = array();

			foreach ($arSkuPropertiesMx as $ixt => $arNextSkuProperty) {
				if ($arNextSkuProperty["SORT"] <= $skuSortParams) {
					$arSkuPropNames[$arNextSkuProperty["NAME"]] = $arNextSkuProperty["NAME"];
					$arSkuPropFiltred[] = CIBlockFormatProperties::GetDisplayValue($arSkuFieldsMx, $arNextSkuProperty, "catalog_out");
				}
			}

			$arSkuFieldsMx["PRICE"] = DwPrices::getPricesByProductId($arSkuFieldsMx["ID"], $arResult["PRODUCT_PRICE_ALLOW"], $arResult["PRODUCT_PRICE_ALLOW_FILTER"], $arParams["PRODUCT_PRICE_CODE"], $arOffersSkuInfo["IBLOCK_ID"], $opCurrency);
			$arSkuFieldsMx["EXTRA_SETTINGS"]["COUNT_PRICES"] = $arSkuFieldsMx["PRICE"]["COUNT_PRICES"];

			if (!empty($arSkuFieldsMx["DETAIL_PICTURE"])) {
				$arSkuFieldsMx["PICTURE"] = CFile::ResizeImageGet($arSkuFieldsMx["DETAIL_PICTURE"], array("width" => $arParams["PICTURE_WIDTH"], "height" => $arParams["PICTURE_HEIGHT"]), BX_RESIZE_IMAGE_PROPORTIONAL, false, false, false, $arParams["PICTURE_QUALITY"]);
			} else {
				if (!empty($arResult["PARENT_PRODUCT"]["PICTURE"])) {
					$arSkuFieldsMx["PICTURE"] = $arResult["PARENT_PRODUCT"]["PICTURE"];
				} else {
					$arSkuFieldsMx["PICTURE"]["src"] = SITE_TEMPLATE_PATH . "/images/empty.svg";
				}
			}

			$arSkuFieldsMx["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] = 0;
			$rsStore = CCatalogStoreProduct::GetList(array(), array("PRODUCT_ID" => $arSkuFieldsMx["ID"]), false, false, array("ID", "AMOUNT"));
			while ($arNextStore = $rsStore->GetNext()) {
				$arSkuFieldsMx["EXTRA_SETTINGS"]["STORES"][] = $arNextStore;
				if ($arNextStore["AMOUNT"] > $arSkuFieldsMx["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"]) {
					$arSkuFieldsMx["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] = $arNextStore["AMOUNT"];
				}
			}

			$arSkuFieldsMx["EXTRA_SETTINGS"]["CURRENCY"] = empty($opCurrency) ? $arSkuFieldsMx["PRICE"]["RESULT_PRICE"]["CURRENCY"] : $opCurrency;

			$rsMeasure = CCatalogMeasure::getList(
				array(),
				array(
					"ID" => $arSkuFieldsMx["CATALOG_MEASURE"]
				),
				false,
				false,
				false
			);

			while ($arNextMeasure = $rsMeasure->Fetch()) {
				$arSkuFieldsMx["EXTRA_SETTINGS"]["MEASURES"][$arNextMeasure["ID"]] = $arNextMeasure;
			}

			$arSkuFieldsMx["EXTRA_SETTINGS"]["BASKET_STEP"] = 1;

			$rsMeasureRatio = CCatalogMeasureRatio::getList(
				array(),
				array("PRODUCT_ID" => intval($arSkuFieldsMx["ID"])),
				false,
				false,
				array()
			);

			if ($arProductMeasureRatio = $rsMeasureRatio->Fetch()) {
				if (!empty($arProductMeasureRatio["RATIO"])) {
					$arSkuFieldsMx["EXTRA_SETTINGS"]["BASKET_STEP"] = $arProductMeasureRatio["RATIO"];
				}
			}

			$arButtons = CIBlock::GetPanelButtons(
				$arSkuFieldsMx["IBLOCK_ID"],
				$arSkuFieldsMx["ID"],
				false,
				array(
					"SECTION_BUTTONS" => true,
					"SESSID" => true,
					"CATALOG" => true
				)
			);

			$arSkuFieldsMx["EDIT_LINK"] = $arButtons["edit"]["edit_element"]["ACTION_URL"];
			$arSkuFieldsMx["DELETE_LINK"] = $arButtons["edit"]["delete_element"]["ACTION_URL"];

			$arResult["ITEMS"][$arSkuFieldsMx["ID"]] = $arSkuFieldsMx;
			$arResult["ITEMS"][$arSkuFieldsMx["ID"]]["PROPERTIES"] = $arSkuPropertiesMx;
			$arResult["ITEMS"][$arSkuFieldsMx["ID"]]["PROPERTIES_FILTRED"] = $arSkuPropFiltred;

		}

		$uri = new \Bitrix\Main\Web\Uri($this->request->getRequestUri());
		$uri->deleteParams(
			array_merge(
				array(
					"PAGEN_" . $rsSkuOffers->NavNum,
					"SIZEN_" . $rsSkuOffers->NavNum,
					"SHOWALL_" . $rsSkuOffers->NavNum,
					"PHPSESSID",
					"clear_cache",
					"bitrix_include_areas"
				),
				\Bitrix\Main\HttpRequest::getSystemParameters()
			)
		);

		$navComponentParameters["BASE_LINK"] = $uri->getUri();

		$arResult["NAV_STRING"] = $rsSkuOffers->GetPageNavStringEx(
			$navComponentObject,
			$arParams["PAGER_NAV_HEADING"],
			$arParams["PAGER_TEMPLATE"],
			$arParams["PAGER_SHOW_ALWAYS"],
			$this,
			$navComponentParameters
		);

		if (!empty($arSkuPropNames)) {
			$arResult["PROPERTY_NAMES"] = $arSkuPropNames;
		}

	}

	$this->setResultCacheKeys(array());
	$this->IncludeComponentTemplate();

}
