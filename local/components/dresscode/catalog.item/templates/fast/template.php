<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?$this->setFrameMode(true);?>
<?if(!empty($arResult)):?>
	<?$uniqID = CAjax::GetComponentID($this->__component->__name, $this->__component->__template->__name, false);?>
	<div id="appFastView" class="superitem item<?if(!empty($arResult["SKU_OFFERS"])):?> sku<?endif;?>" data-product-iblock-id="<?=$arParams["IBLOCK_ID"]?>" data-from-cache="<?=$arResult["FROM_CACHE"]?>" data-convert-currency="<?=$arParams["CONVERT_CURRENCY"]?>" data-currency-id="<?=$arParams["CURRENCY_ID"]?>" data-hide-not-available="<?=$arParams["HIDE_NOT_AVAILABLE"]?>" data-product-id="<?=!empty($arResult["~ID"]) ? $arResult["~ID"] : $arResult["ID"]?>"<?if(!empty($arResult["SKU_INFO"])):?> data-iblock-id="<?=$arResult["SKU_INFO"]["IBLOCK_ID"]?>" data-currency="<?=$arResult["EXTRA_SETTINGS"]["CURRENCY"]?>" data-prop-id="<?=$arResult["SKU_INFO"]["SKU_PROPERTY_ID"]?>"<?endif;?> data-product-width="<?=$arParams["PICTURE_WIDTH"]?>" data-product-height="<?=$arParams["PICTURE_HEIGHT"]?>" data-hide-measure="<?=$arParams["HIDE_MEASURES"]?>" data-cast-func="fastViewSku" data-change-prop="fast-view" data-more-pictures="Y" data-price-code="<?=implode("||", $arParams["PRODUCT_PRICE_CODE"])?>">
		<div class="appFastViewContainer">
			<div class="appFastViewHeading"><?=GetMessage("FAST_VIEW_HEADING")?> <a href="#" class="appFastViewExit"></a></div>
			<div class="appFastViewColumnContainer">
				<div class="appFastViewPictureColumn">
					<?if(!empty($arResult["PROPERTIES"]["OFFERS"]["VALUE"])):?>
						<div class="markerContainer">
							<?foreach ($arResult["PROPERTIES"]["OFFERS"]["VALUE"] as $ifv => $marker):?>
							    <div class="marker" style="background-color: <?=strstr($arResult["PROPERTIES"]["OFFERS"]["VALUE_XML_ID"][$ifv], "#") ? $arResult["PROPERTIES"]["OFFERS"]["VALUE_XML_ID"][$ifv] : "#424242"?>"><?=$marker?></div>
							<?endforeach;?>
						</div>
					<?endif;?>
					<?if(!empty($arResult["IMAGES"])):?>
						<div class="appFastViewPictureSlider">
							<div class="appFastViewPictureSliderItems">
								<?foreach ($arResult["IMAGES"] as $inm => $arNextPicture):?>
									<div class="appFastViewPictureSliderItem">
										<div class="appFastViewPictureSliderItemLayout">
											<a href="<?=$arResult["DETAIL_PAGE_URL"]?>" class="appFastViewPictureSliderItemLink" data-loupe-picture="<?=$arNextPicture["MEDIUM_IMAGE"]["SRC"]?>">
												<img src="<?=$arNextPicture["REGULAR_IMAGE"]["SRC"]?>" class="appFastViewPictureSliderItemPicture" alt="">
											</a>
										</div>
									</div>
								<?endforeach;?>
							</div>
						</div>
						<div class="appFastViewPictureCarousel">
							<div class="appFastViewPictureCarouselItems">
								<?foreach ($arResult["IMAGES"] as $inm => $arNextPicture):?>
									<div class="appFastViewPictureCarouselItem">
										<a href="#" class="appFastViewPictureCarouselItemLink"><img src="<?=$arNextPicture["SMALL_IMAGE"]["SRC"]?>" class="appFastViewPictureCarouselItemPicture" alt=""></a>
									</div>
								<?endforeach;?>
							</div>
							<a href="#" class="appFastViewPictureCarouselLeftButton"></a>
							<a href="#" class="appFastViewPictureCarouselRightButton"></a>
						</div>
					<?endif;?>
				</div>
				<div class="appFastViewDescriptionColumn">
					<div class="appFastViewDescriptionColumnContainer">
						<div class="appFastViewProductHeading"><a href="<?=$arResult["DETAIL_PAGE_URL"]?>" class="appFastViewProductHeadingLink"><span class="appFastViewProductHeadingLinkLayout"><?=$arResult["NAME"]?></span></a></div>
						<?if(!empty($arResult["SKU_OFFERS"])):?>
							<?if(!empty($arResult["SKU_PROPERTIES"]) && $level = 1):?>
								<div class="appFastSkuProductProperties">
									<div class="appFastSkuProductPropertiesHeading"><?=GetMessage("FAST_VIEW_SKU_PROPERTIES_TITLE")?></div>
									<?foreach ($arResult["SKU_PROPERTIES"] as $propName => $arNextProp):?>
										<?if(!empty($arNextProp["VALUES"])):?>
											<?if($arNextProp["LIST_TYPE"] == "L" && $arNextProp["HIGHLOAD"] != "Y"):?>
												<div class="skuProperty oSkuDropDownProperty" data-name="<?=$propName?>" data-level="<?=$level++?>" data-highload="<?=$arNextProp["HIGHLOAD"]?>">
													<div class="skuPropertyName"><?=preg_replace("/\[.*\]/", "", $arNextProp["NAME"])?>:</div>
													<div class="oSkuDropdown">
														<ul class="skuPropertyList oSkuDropdownList">
															<?foreach ($arNextProp["VALUES"] as $xml_id => $arNextPropValue):?>
																<?if($arNextPropValue["SELECTED"] == "Y"):?>
																	<?$currentSkuValue = $arNextPropValue["DISPLAY_VALUE"];?>
																<?endif;?>
																<li class="skuPropertyValue oSkuDropdownListItem<?if($arNextPropValue["DISABLED"] == "Y"):?> disabled<?elseif($arNextPropValue["SELECTED"] == "Y"):?> selected<?endif;?>" data-name="<?=$propName?>" data-value="<?=$arNextPropValue["VALUE"]?>">
																	<a href="#" class="skuPropertyLink oSkuPropertyItemLink"><?=$arNextPropValue["DISPLAY_VALUE"]?></a>
																</li>
															<?endforeach;?>
														</ul>
														<span class="oSkuCheckedItem"><?=$currentSkuValue?></span>
													</div>
												</div>
											<?else:?>
												<div class="skuProperty" data-name="<?=$propName?>" data-level="<?=$level++?>" data-highload="<?=$arNextProp["HIGHLOAD"]?>">
													<div class="skuPropertyName"><?=preg_replace("/\[.*\]/", "", $arNextProp["NAME"])?></div>
													<ul class="skuPropertyList">
														<?foreach ($arNextProp["VALUES"] as $xml_id => $arNextPropValue):?>
															<li class="skuPropertyValue<?if($arNextPropValue["DISABLED"] == "Y"):?> disabled<?elseif($arNextPropValue["SELECTED"] == "Y"):?> selected<?endif;?>" data-name="<?=$propName?>" data-value="<?=$arNextPropValue["VALUE"]?>">
																<a href="#" class="skuPropertyLink">
																	<?if(!empty($arNextPropValue["IMAGE"])):?>
																		<img src="<?=$arNextPropValue["IMAGE"]["src"]?>" alt="">
																	<?else:?>
																		<?=$arNextPropValue["DISPLAY_VALUE"]?>
																	<?endif;?>
																</a>
															</li>
														<?endforeach;?>
													</ul>
												</div>
											<?endif;?>
										<?endif;?>
									<?endforeach;?>
								</div>
							<?endif;?>
						<?endif;?>
						<div class="changeProperties">
							<?$APPLICATION->IncludeComponent(
								"dresscode:catalog.properties.list",
								"fast-view",
								array(
									"PRODUCT_ID" => $arResult["ID"],
									"COUNT_PROPERTIES" => 20
								),
								false
							);?>
						</div>
						<div class="appFastViewDescription<?if(!empty($arResult["PREVIEW_TEXT"])):?> visible<?endif;?>">
							<div class="appFastViewDescriptionHeading"><?=GetMessage("FAST_VIEW_DESCRIPTION_TITLE")?></div>
							<div class="appFastViewDescriptionText"><?if(!empty($arResult["PREVIEW_TEXT"])):?><?=$arResult["PREVIEW_TEXT"]?><?endif;?></div>
						</div>
						<a href="<?=$arResult["DETAIL_PAGE_URL"]?>" class="appFastViewMoreLink"><?=GetMessage("FAST_VIEW_PRODUCT_MORE_LINK")?></a>
					</div>
				</div>
				<div class="appFastViewInformationColumn">
					<?if(!empty($arResult["EXTRA_SETTINGS"]["SHOW_TIMER"])):?>
						<div class="specialTime fastSpecialTime" id="timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$uniqID?>">
							<div class="specialTimeItem">
								<div class="specialTimeItemValue timerDayValue">0</div>
								<div class="specialTimeItemlabel"><?=GetMessage("FAST_VIEW_TIMER_DAY_LABEL")?></div>
							</div>
							<div class="specialTimeItem">
								<div class="specialTimeItemValue timerHourValue">0</div>
								<div class="specialTimeItemlabel"><?=GetMessage("FAST_VIEW_TIMER_HOUR_LABEL")?></div>
							</div>
							<div class="specialTimeItem">
								<div class="specialTimeItemValue timerMinuteValue">0</div>
								<div class="specialTimeItemlabel"><?=GetMessage("FAST_VIEW_TIMER_MINUTE_LABEL")?></div>
							</div>
							<div class="specialTimeItem">
								<div class="specialTimeItemValue timerSecondValue">0</div>
								<div class="specialTimeItemlabel"><?=GetMessage("FAST_VIEW_TIMER_SECOND_LABEL")?></div>
							</div>
						</div>
					<?endif;?>
					<?if(!empty($arResult["PROPERTIES"]["TIMER_LOOP"]["VALUE"])):?>
						<script type="text/javascript">
							$(document).ready(function(){
								$("#timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$uniqID?>").dwTimer({
									timerLoop: "<?=$arResult["PROPERTIES"]["TIMER_LOOP"]["VALUE"]?>",
									<?if(empty($arResult["PROPERTIES"]["TIMER_START_DATE"]["VALUE"])):?>
										startDate: "<?=MakeTimeStamp($arResult["DATE_CREATE"], "DD.MM.YYYY HH:MI:SS")?>"
									<?else:?>
										startDate: "<?=MakeTimeStamp($arResult["PROPERTIES"]["TIMER_START_DATE"]["VALUE"], "DD.MM.YYYY HH:MI:SS")?>"
									<?endif;?>
								});
							});
						</script>
					<?elseif(!empty($arResult["EXTRA_SETTINGS"]["SHOW_TIMER"]) && !empty($arResult["PROPERTIES"]["TIMER_DATE"]["VALUE"])):?>
						<script type="text/javascript">
							$(document).ready(function(){
								$("#timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$uniqID?>").dwTimer({
									endDate: "<?=MakeTimeStamp($arResult["PROPERTIES"]["TIMER_DATE"]["VALUE"], "DD.MM.YYYY HH:MI:SS")?>"
								});
							});
						</script>
					<?endif;?>
					<div class="article<?if(empty($arResult["PROPERTIES"]["CML2_ARTICLE"]["VALUE"])):?> hidden<?endif;?>">
						<?=GetMessage("FAST_VIEW_ARTICLE_LABEL")?> <span class="changeArticle" data-first-value="<?=$arResult["PROPERTIES"]["CML2_ARTICLE"]["VALUE"]?>"><?=$arResult["PROPERTIES"]["CML2_ARTICLE"]["VALUE"]?></span>
					</div>
					<?if(!empty($arResult["PRICE"])):?>
						<?if($arResult["EXTRA_SETTINGS"]["COUNT_PRICES"] > 1):?>
							<a href="#" data-id="<?=$arResult["ID"]?>" class="price getPricesWindow" data-fixed="Y">
								<span class="priceIcon"></span><span class="priceVal"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span>
								<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
									<span class="measure"> / <?=$arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
								<?endif;?>
								<?if(!empty($arResult["PRICE"]["DISCOUNT"])):?>
									<span class="oldPriceLabel"><?=GetMessage("FAST_VIEW_OLD_PRICE_LABEL")?><s class="discount"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></s></span>
								<?endif;?>
							</a>
						<?else:?>
							<a class="price">
								<span class="priceVal"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span>
								<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
									<span class="measure"> / <?=$arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
								<?endif;?>
								<?if(!empty($arResult["PRICE"]["DISCOUNT"])):?>
									<span class="oldPriceLabel"><?=GetMessage("FAST_VIEW_OLD_PRICE_LABEL")?><s class="discount"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></s></span>
								<?endif;?>
							</a>
						<?endif;?>
						<?if($arResult["CATALOG_AVAILABLE"] != "Y"):?>
							<?if($arResult["CATALOG_SUBSCRIBE"] == "Y"):?>
								<a href="#" class="addCart subscribe" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/subscribe.svg" alt="" class="icon"><?=GetMessage("FAST_VIEW_SUBSCRIBE_LABEL")?></a>
							<?else:?>
								<a href="#" class="addCart disabled" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/incart.svg" alt="" class="icon"><?=GetMessage("FAST_VIEW_ADDCART_LABEL")?></a>
							<?endif;?>
						<?else:?>
							<a href="#" class="addCart" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/incart.svg" alt="" class="icon"><?=GetMessage("FAST_VIEW_ADDCART_LABEL")?></a>
						<?endif;?>
					<?else:?>
						<a class="price"><?=GetMessage("FAST_VIEW_REQUEST_PRICE_LABEL")?></a>
						<a href="#" class="addCart disabled requestPrice" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/request.svg" alt="" class="icon"><?=GetMessage("FAST_VIEW_REQUEST_PRICE_BUTTON_LABEL")?></a>
					<?endif;?>
					<div class="catalogQtyBlock">
			            <input type="text" class="catalogQty"<?if(!empty($arResult["PRICE"]["EXTENDED_PRICES"])):?> data-extended-price='<?=\Bitrix\Main\Web\Json::encode($arResult["PRICE"]["EXTENDED_PRICES"])?>'<?endif;?> value="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>" data-step="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>" data-max-quantity="<?=$arResult["CATALOG_QUANTITY"]?>" data-enable-trace="<?=(($arResult["CATALOG_QUANTITY_TRACE"] == "Y" && $arResult["CATALOG_CAN_BUY_ZERO"] == "N") ? "Y" : "N")?>" max="9999"><a href="#" class="catalogMinus"></a><a href="#" class="catalogPlus"></a>
			        </div>
					<div class="secondTool">
						<?if(isset($arResult["PROPERTIES"]["RATING"]["VALUE"])):?>
							<div class="row">
								<img src="<?=SITE_TEMPLATE_PATH?>/images/reviews.svg" alt="" class="icon">
								<span class="label<?if(!empty($arResult["REVIEWS"])):?> countReviewsTools<?endif;?>"><?=GetMessage("FAST_VIEW_REVIEWS_LABEL")?></span>
								<div class="rating">
									<i class="m" style="width:<?=(intval($arResult["PROPERTIES"]["RATING"]["VALUE"]) * 100 / 5)?>%"></i>
									<i class="h"></i>
								</div>
							</div>
						<?endif;?>
						<div class="row">
							<a href="#" class="fastBack label changeID<?if(empty($arResult["PRICE"]) || $arResult["CATALOG_AVAILABLE"] != "Y"):?> disabled<?endif;?>" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/fastBack.svg" alt="<?=GetMessage("FAST_VIEW_FASTBACK_LABEL")?>" class="icon"><?=GetMessage("FAST_VIEW_FASTBACK_LABEL")?></a>
						</div>
						<div class="row">
							<a href="#" class="addWishlist label" data-id="<?=!empty($arResult["~ID"]) ? $arResult["~ID"] : $arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/wishlist.svg" alt="<?=GetMessage("FAST_VIEW_WISHLIST_LABEL")?>" class="icon"><?=GetMessage("FAST_VIEW_WISHLIST_LABEL")?></a>
						</div>
						<div class="row">
							<a href="#" class="addCompare label changeID" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/compare.svg" alt="<?=GetMessage("FAST_VIEW_COMPARE_LABEL")?>" class="icon"><?=GetMessage("FAST_VIEW_COMPARE_LABEL")?></a>
						</div>
						<div class="row">
							<?if($arResult["CATALOG_QUANTITY"] > 0):?>
								<?if(!empty($arResult["EXTRA_SETTINGS"]["STORES"]) && $arResult["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] > 0):?>
									<a href="#" data-id="<?=$arResult["ID"]?>" class="inStock label changeAvailable getStoresWindow"><img src="<?=SITE_TEMPLATE_PATH?>/images/inStock.svg" alt="<?=GetMessage("FAST_VIEW_AVAILABLE")?>" class="icon"><span><?=GetMessage("FAST_VIEW_AVAILABLE")?></span></a>
								<?else:?>
									<span class="inStock label changeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/inStock.svg" alt="<?=GetMessage("FAST_VIEW_AVAILABLE")?>" class="icon"><span><?=GetMessage("FAST_VIEW_AVAILABLE")?></span></span>
								<?endif;?>
							<?else:?>
								<?if($arResult["CATALOG_AVAILABLE"] == "Y"):?>
									<span class="onOrder label changeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/onOrder.svg" alt="<?=GetMessage("FAST_VIEW_ON_ORDER")?>" class="icon"><?=GetMessage("FAST_VIEW_ON_ORDER")?></span>
								<?else:?>
									<span class="outOfStock label changeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/outOfStock.svg" alt="<?=GetMessage("FAST_VIEW_NO_AVAILABLE")?>" class="icon"><?=GetMessage("FAST_VIEW_NO_AVAILABLE")?></span>
								<?endif;?>
							<?endif;?>
						</div>	
					</div>
				</div>
			</div>
		</div>
		<script>
			var fastViewAjaxDir = "<?=$componentPath?>";
			var CATALOG_LANG = {
				FAST_VIEW_OLD_PRICE_LABEL: "<?=GetMessage("FAST_VIEW_OLD_PRICE_LABEL")?>",
			};
		</script>

		<script src="<?=$templateFolder?>/fast_script.js"></script>
	</div>
<?endif;?>
