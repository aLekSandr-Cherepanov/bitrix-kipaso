<?

	if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
		die();
	}

	if(!empty($arResult)){
		// Проверяем наличие свойства SPECIFICATIONS_TEXT
		if(!empty($arResult["PROPERTIES"]["SPECIFICATIONS_TEXT"]["VALUE"]["TEXT"])){
			// Если свойство SPECIFICATIONS_TEXT не пустое, то не показываем его в детальном описании
			// Проверим, есть ли в DETAIL_TEXT тот же контент, что и в SPECIFICATIONS_TEXT
			if(strpos($arResult["DETAIL_TEXT"], $arResult["PROPERTIES"]["SPECIFICATIONS_TEXT"]["VALUE"]["TEXT"]) !== false){
				// Удаляем спецификации из DETAIL_TEXT, чтобы избежать дублирования
				$arResult["DETAIL_TEXT"] = str_replace($arResult["PROPERTIES"]["SPECIFICATIONS_TEXT"]["VALUE"]["TEXT"], "", $arResult["DETAIL_TEXT"]);
				$arResult["~DETAIL_TEXT"] = str_replace($arResult["PROPERTIES"]["SPECIFICATIONS_TEXT"]["VALUE"]["TEXT"], "", $arResult["~DETAIL_TEXT"]);
			}
		}

		//include modules
		CModule::IncludeModule("catalog");
		CModule::IncludeModule("iblock");
		CModule::IncludeModule("sale");

		//global vars
		global $USER;

		// set vars
		$parentElementId = !empty($arResult["PARENT_PRODUCT"]) ? $arResult["PARENT_PRODUCT"]["ID"] : $arResult["ID"];
		$userId = $USER->GetID();

		// blocks

		//get complect for product

		$arComplectID = array();
		$arResult["COMPLECT"] = array();

		$rsComplect = CCatalogProductSet::getList(
			array("SORT" => "ASC"),
			array(
				"TYPE" => 1,
				"OWNER_ID" => $parentElementId,
				"!ITEM_ID" => $parentElementId
			),
			false,
			false,
			array("*")
		);

		while ($arComplectItem = $rsComplect->Fetch()) {
			$arResult["COMPLECT"]["ITEMS"][$arComplectItem["ITEM_ID"]] = $arComplectItem;
			$arComplectID[$arComplectItem["ITEM_ID"]] = $arComplectItem["ITEM_ID"];
		}

		if(!empty($arComplectID)){

			$arResult["COMPLECT"]["RESULT_PRICE"] = 0;
			$arResult["COMPLECT"]["RESULT_BASE_DIFF"] = 0;
			$arResult["COMPLECT"]["RESULT_BASE_PRICE"] = 0;

			$arSelect = Array("ID", "IBLOCK_ID", "NAME", "DETAIL_PICTURE", "DETAIL_PAGE_URL", "CATALOG_MEASURE");
			$arFilter = Array("ID" => $arComplectID, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");
			$rsComplectProducts = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
			while($obComplectProducts = $rsComplectProducts->GetNextElement()){

				$complectProductFields = $obComplectProducts->GetFields();
				if(!empty($arResult["PRODUCT_PRICE_ALLOW"])){
					$arPriceCodes = array();
					foreach($arResult["PRODUCT_PRICE_ALLOW"] as $ipc => $arNextAllowPrice){
						$dbPrice = CPrice::GetList(
					        array(),
					        array(
					            "PRODUCT_ID" => $complectProductFields["ID"],
					            "CATALOG_GROUP_ID" => $arNextAllowPrice["ID"]
					        )
					    );
						if($arPriceValues = $dbPrice->Fetch()){
							$arPriceCodes[] = array(
								"ID" => $arNextAllowPrice["ID"],
								"PRICE" => $arPriceValues["PRICE"],
								"CURRENCY" => $arPriceValues["CURRENCY"],
								"CATALOG_GROUP_ID" => $arNextAllowPrice["ID"]
							);
						}
					}
				}

				if(!empty($arResult["PRODUCT_PRICE_ALLOW"]) && !empty($arPriceCodes) || empty($arParams["PRICE_CODE"]))
					$complectProductFields["PRICE"] = CCatalogProduct::GetOptimalPrice($complectProductFields["ID"], 1, $USER->GetUserGroupArray(), "N", $arPriceCodes);

				$complectProductFields["PRICE"]["DISCOUNT_PRICE"] = $complectProductFields["PRICE"]["DISCOUNT_PRICE"] * $arResult["COMPLECT"]["ITEMS"][$complectProductFields["ID"]]["QUANTITY"];
				$complectProductFields["PRICE"]["DISCOUNT_PRICE"] -= $complectProductFields["PRICE"]["DISCOUNT_PRICE"] * $arResult["COMPLECT"]["ITEMS"][$complectProductFields["ID"]]["DISCOUNT_PERCENT"] / 100;
				$complectProductFields["PRICE"]["RESULT_PRICE"]["BASE_PRICE"] = $complectProductFields["PRICE"]["RESULT_PRICE"]["BASE_PRICE"] * $arResult["COMPLECT"]["ITEMS"][$complectProductFields["ID"]]["QUANTITY"];
				$complectProductFields["PRICE"]["PRICE_DIFF"] = $complectProductFields["PRICE"]["RESULT_PRICE"]["BASE_PRICE"] - $complectProductFields["PRICE"]["DISCOUNT_PRICE"];
				$complectProductFields["PRICE"]["BASE_PRICE_FORMATED"] = CurrencyFormat($complectProductFields["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"]);
				$complectProductFields["PRICE"]["PRICE_FORMATED"] = CurrencyFormat($complectProductFields["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"]);
				$complectProductFields["PICTURE"] = CFile::ResizeImageGet($complectProductFields["DETAIL_PICTURE"], array("width" => 250, "height" => 250), BX_RESIZE_IMAGE_PROPORTIONAL, false);
				if (isset($complectProductFields["CATALOG_MEASURE"])) {
					$arResult["CATALOG_MEASURE"][$complectProductFields["CATALOG_MEASURE"]] = $complectProductFields["CATALOG_MEASURE"];
				}
				$arResult["COMPLECT"]["RESULT_PRICE"] += $complectProductFields["PRICE"]["DISCOUNT_PRICE"];
				$arResult["COMPLECT"]["RESULT_BASE_PRICE"] += $complectProductFields["PRICE"]["RESULT_PRICE"]["BASE_PRICE"];
				$arResult["COMPLECT"]["RESULT_BASE_DIFF"] += $complectProductFields["PRICE"]["PRICE_DIFF"];

				$complectProductFields = array_merge(
					$arResult["COMPLECT"]["ITEMS"][$complectProductFields["ID"]],
					$complectProductFields
				);

				//get picture by parent sku product
				if(empty($complectProductFields["PICTURE"]["src"])){
					$skuProductInfo = CCatalogSKU::getProductList($complectProductFields["ID"]);
					if(!empty($skuProductInfo)){
						foreach ($skuProductInfo as $itx => $skuProductInfoElement) {
							$productBySku = CIBlockElement::GetByID($skuProductInfoElement["ID"]);
							if(!empty($productBySku)){
								if($arResProductSku = $productBySku->GetNextElement()){
									$arResProductSkuFields = $arResProductSku->GetFields();
									if(!empty($arResProductSkuFields["DETAIL_PICTURE"])){
										$complectProductFields["PICTURE"] = CFile::ResizeImageGet($arResProductSkuFields["DETAIL_PICTURE"], array("width" => 250, "height" => 250), BX_RESIZE_IMAGE_PROPORTIONAL, false);
									}
								}
							}
						}
					}
				}

				// set empty picture
				if(empty($complectProductFields["PICTURE"]["src"])){
					$complectProductFields["PICTURE"]["src"] = SITE_TEMPLATE_PATH."/images/empty.svg";
				}

				$arResult["COMPLECT"]["ITEMS"][$complectProductFields["ID"]] = $complectProductFields;

			}

			$arResult["COMPLECT"]["RESULT_PRICE_FORMATED"] = CurrencyFormat($arResult["COMPLECT"]["RESULT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"]);
			$arResult["COMPLECT"]["RESULT_BASE_DIFF_FORMATED"] = CurrencyFormat($arResult["COMPLECT"]["RESULT_BASE_DIFF"], $arResult["EXTRA_SETTINGS"]["CURRENCY"]);
			$arResult["COMPLECT"]["RESULT_BASE_PRICE_FORMATED"] = CurrencyFormat($arResult["COMPLECT"]["RESULT_BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"]);

		}

		//services
		if(!empty($arResult["PROPERTIES"]["SERVICES"]["VALUE"])){

			//globals
			global $servicesFilter;

			//set filter
			$servicesFilter = array("ID" => $arResult["PROPERTIES"]["SERVICES"]["VALUE"], "ACTIVE" => "Y");

		}

		// related products
		if (intval($arResult["RELATED_COUNT"]) > 0){

			//filter var for catalog.section
			global $relatedFilter;

			//set filter
			$relatedFilter = array("ID" => $arResult["PROPERTIES"]["RELATED_PRODUCT"]["VALUE"], "ACTIVE" => "Y");

			//show tab flag
			$arResult["SHOW_RELATED"] = "Y";

		}

		//reviews

		//show form for new review
		$arParams["SHOW_REVIEW_FORM"] = $arParams["USE_REVIEW"] == "Y";
		$reviewProductId = array($arResult["ID"]);
		if(!empty($arResult["PARENT_PRODUCT"])){
			$reviewProductId[] = $arResult["PARENT_PRODUCT"]["ID"];
		}

		if(!empty($arParams["REVIEW_IBLOCK_ID"])){

			$arSelect = Array("ID", "DATE_CREATE", "DETAIL_TEXT", "PROPERTY_DIGNITY", "PROPERTY_SHORTCOMINGS", "PROPERTY_EXPERIENCE", "PROPERTY_GOOD_REVIEW", "PROPERTY_BAD_REVIEW", "PROPERTY_NAME", "PROPERTY_RATING");
			$arFilter = Array("IBLOCK_ID" => $arParams["REVIEW_IBLOCK_ID"], "ACTIVE_DATE" => "Y", "ACTIVE" => "Y", "CODE" => $reviewProductId);
			$rsReviews = CIBlockElement::GetList(array("SORT" => "ASC", "CREATED_DATE"), $arFilter, false, false, $arSelect);
			while($oReviews = $rsReviews->GetNextElement()){
				$arResult["REVIEWS"][] = $oReviews->GetFields();
			}

			$expEnums = CIBlockPropertyEnum::GetList(array("DEF" => "DESC", "SORT" => "ASC"), array("IBLOCK_ID" => $arParams["REVIEW_IBLOCK_ID"], "CODE" => "EXPERIENCE"));
			while($enumValues = $expEnums->GetNext()){
				$arResult["NEW_REVIEW"]["EXPERIENCE"][] = array(
					"ID" => $enumValues["ID"],
					"VALUE" => $enumValues["VALUE"]
				);
			}

			if(empty($arParams["ALLOW_ADD_REVIEW_NOT_REGISTER"]) || $arParams["ALLOW_ADD_REVIEW_NOT_REGISTER"] != "Y"){
				if($userId == $arResult["PROPERTIES"]["USER_ID"]["VALUE"] || $userId == false){
					$arParams["SHOW_REVIEW_FORM"] = false;
				}
			}

		}

		// similar products
		if (intval($arResult["SIMILAR_COUNT"]) > 0){

			//filter var for catalog.section
			global $similarFilter;

			//set filter
			$similarFilter = $arResult["SIMILAR_FILTER"];

			//show tab flag
			$arResult["SHOW_SIMILAR"] = "Y";

		}

		if($arResult["CATALOG_QUANTITY"] > 0){
			if(!empty($arResult["EXTRA_SETTINGS"]["STORES"])){
				$arResult["SHOW_STORES"] = "Y";
			}
		}

		//line breaks
		if(!empty($arResult["DETAIL_TEXT"]) && !empty($arResult["DETAIL_TEXT_TYPE"]) && $arResult["DETAIL_TEXT_TYPE"] == "text"){
			$arResult["DETAIL_TEXT"] = str_replace("\n", "<br />", $arResult["DETAIL_TEXT"]);
			$arResult["~DETAIL_TEXT"] = str_replace("\n", "<br />", $arResult["~DETAIL_TEXT"]);
		}

		//tabs
		$arResult["TABS"]["CATALOG_ELEMENT_BACK"] = array("PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco1.svg", "NAME" => GetMessage("CATALOG_ELEMENT_BACK"), "LINK" => $arResult["LAST_SECTION"]["SECTION_PAGE_URL"]);
		$arResult["TABS"]["CATALOG_ELEMENT_OVERVIEW"] = array(
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco2.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_OVERVIEW"),
			"ACTIVE" => "Y",
			"ID" => "browse"
		);

		if($arResult["CATALOG_AVAILABLE"] === "Y"){
			$arResult["TABS"]["CATALOG_ELEMENT_SET"] = array(
				"DISABLED" => CCatalogProductSet::isProductHaveSet($parentElementId, CCatalogProductSet::TYPE_GROUP) ? "N" : "Y",
				"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco3.svg",
				"NAME" => GetMessage("CATALOG_ELEMENT_SET"),
				"ID" => "set"
			);
		}

		$arResult["TABS"]["CATALOG_ELEMENT_COMPLECT"] = array(
			"DISABLED" => !empty($arResult["COMPLECT"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco3.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_COMPLECT"),
			"ID" => "complect"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_DESCRIPTION"] = array(
			"DISABLED" => !empty($arResult["DETAIL_TEXT"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco8.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_DESCRIPTION"),
			"ID" => "detailText"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_CHARACTERISTICS"] = array(
			"DISABLED" => !empty($arResult["PROPERTIES"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco9.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_CHARACTERISTICS"),
			"ID" => "elementProperties"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_ACCEESSORIES"] = array(
			"DISABLED" => $arResult["SHOW_RELATED"] == "Y" ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco5.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_ACCEESSORIES"),
			"ID" => "related"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_REVIEW"] = array(
			"DISABLED" => !empty($arResult["REVIEWS"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco4.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_REVIEW"),
			"ID" => "catalogReviews"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_SIMILAR"] = array(
			"DISABLED" => $arResult["SHOW_SIMILAR"] == "Y" ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco6.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_SIMILAR"),
			"ID" => "similar"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_AVAILABILITY"] = array(
			"DISABLED" => $arResult["SHOW_STORES"] != "Y" || $arParams["USE_STORE"] != "Y" ? "Y" : "N",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco7.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_AVAILABILITY"),
			"ID" => "stores"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_FILES"] = array(
			"DISABLED" => !empty($arResult["FILES"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco11.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_FILES"),
			"ID" => "files"
		);

		$arResult["TABS"]["CATALOG_ELEMENT_VIDEO"] = array(
			"DISABLED" => !empty($arResult["VIDEO"]) ? "N" : "Y",
			"PICTURE" => SITE_TEMPLATE_PATH."/images/elementNavIco10.svg",
			"NAME" => GetMessage("CATALOG_ELEMENT_VIDEO"),
			"ID" => "video"
		);

	}

?>
