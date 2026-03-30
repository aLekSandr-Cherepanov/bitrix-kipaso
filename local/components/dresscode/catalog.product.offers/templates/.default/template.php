<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<!-- Подключение CSS-файла -->
<?$APPLICATION->SetAdditionalCSS($this->GetFolder() . '/style.css');?>
<!-- Подключение CSS-файла конец-->
<?$this->setFrameMode(true);?>
<?if(!empty($arResult["ITEMS"])):?>
	<?
		$parentSectionId = (int)($arResult["PARENT_PRODUCT"]["IBLOCK_SECTION_ID"] ?? 0);
		$showDetailsButton = ($parentSectionId === 111);
		$basketColumnLabel = $showDetailsButton ? "Подробнее" : GetMessage("OFFERS_ADD_CART_COLUMN");
	?>
	<?if(empty($arParams["FROM_AJAX"])):?>
		<div id="skuOffersTable">
			<span class="heading"><?=GetMessage("OFFERS_PRODUCT_VARIANT")?></span>
			<div class="offersTableContainer">
				<div class="offersTable">
					<div class="thead">
						<div class="tb">
							<?if($arParams["DISPLAY_PICTURE_COLUMN"] == "Y"):?>
								<div class="tc offersPicture" style="display: none;"></div>
							<?endif;?>
							<div class="tc offersName"><?=GetMessage("OFFERS_NAME_COLUMN")?></div>
							<?foreach ($arResult["PROPERTY_NAMES"] as $nextPropertyName):?>
								<div class="tc property"><?=$nextPropertyName?></div>
							<?endforeach;?>
							<div class="tc quantity"><?=GetMessage("OFFERS_AVAILABLE_COLUMN")?></div>
							<div class="tc quanBaskWrap">
								<div class="tb">
									
									<div class="tc priceWrap"><?=GetMessage("OFFERS_PRICE_COLUMN")?></div>
									<div class="tc basket"><?=$basketColumnLabel?></div>
								</div>
							</div>
						</div>
					</div>
					<div class="skuOffersTableAjax">
					<?endif;//empty($arParams["FROM_AJAX"])?>
						<?php
						// Сортировка офферов: сначала "В наличии" (QUANTITY>0), затем "Под заказ" (доступные при QUANTITY<=0), потом недоступные. Далее по названию.
						if (!empty($arResult["ITEMS"]) && is_array($arResult["ITEMS"])) {
							usort($arResult["ITEMS"], function($a, $b) {
								$sa = isset($a["SORT"]) ? (int)$a["SORT"] : null;
								$sb = isset($b["SORT"]) ? (int)$b["SORT"] : null;
								if ($sa !== null && $sb !== null && $sa !== $sb) {
									return $sa - $sb; // приоритет SORT из бэкенда
								}

								$rank = function($x) {
									$q = isset($x["CATALOG_QUANTITY"]) ? (float)$x["CATALOG_QUANTITY"] : 0;
									$avail = isset($x["CATALOG_AVAILABLE"]) ? (string)$x["CATALOG_AVAILABLE"] : "N";
									if ($q > 0) return 0;                     // В наличии
									if ($avail === "Y") return 1;              // Под заказ (доступен к заказу)
									return 2;                                    // Недоступен
								};
								$ra = $rank($a);
								$rb = $rank($b);
								if ($ra !== $rb) return $ra - $rb;
								$an = isset($a["NAME"]) ? (string)$a["NAME"] : "";
								$bn = isset($b["NAME"]) ? (string)$b["NAME"] : "";
								return strcasecmp($an, $bn);
							});
						}
						?>
							<?foreach ($arResult["ITEMS"] as $inx => $arNextElement):?>
							<?
								$this->AddEditAction("offers_".$arNextElement["ID"], $arNextElement["EDIT_LINK"], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_EDIT"));
								$this->AddDeleteAction("offers_".$arNextElement["ID"], $arNextElement["DELETE_LINK"], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_DELETE"), array());

								$parentSectionId = (int)($arResult["PARENT_PRODUCT"]["IBLOCK_SECTION_ID"] ?? 0);
								$showDetailsButton = ($parentSectionId === 111);
								
								$posadValue = "";
								$offerDetailsUrl = "";
								
								if ($showDetailsButton) {
									$posadValue = $arNextElement["PROPERTIES"]["URL_POSAD"]["VALUE"] ?? "";
									if (is_array($posadValue)) {
										$posadValue = reset($posadValue);
									}
									$posadValue = trim((string)$posadValue);

									if ($posadValue !== "") {
										if (preg_match("~^https?://~i", $posadValue) || strpos($posadValue, "//") === 0) {
											$offerDetailsUrl = $posadValue;
										} elseif (substr($posadValue, 0, 1) === "/") {
											$offerDetailsUrl = $posadValue;
										} else {
											$parentUrl = (string)($arResult["PARENT_PRODUCT"]["DETAIL_PAGE_URL"] ?? "");
											$parentUrl = preg_replace("~[?#].*$~", "", $parentUrl);
											$parentDir = rtrim(dirname($parentUrl), "/");
											if ($parentDir === ".") {
												$parentDir = "";
											}

											$parentExt = pathinfo($parentUrl, PATHINFO_EXTENSION);
											$posadExt = pathinfo($posadValue, PATHINFO_EXTENSION);
											$posadFile = ($posadExt !== "") ? $posadValue : ($posadValue . (!empty($parentExt) ? ("." . $parentExt) : ""));

											if ($parentDir !== "") {
												$offerDetailsUrl = $parentDir . "/" . $posadFile;
											} else {
												$offerDetailsUrl = "/" . $posadFile;
											}
										}
									}

									if ($offerDetailsUrl === "") {
										$offerDetailsUrl = (string)($arNextElement["DETAIL_PAGE_URL"] ?? "");
									}
								}
							?>
							<div class="tableElem" id="<?=$this->GetEditAreaId("offers_".$arNextElement["ID"]);?>" data-offer-image="<?=htmlspecialcharsbx($arNextElement["PICTURE"]["src"])?>">
								<div class="tb">
									<?if($arParams["DISPLAY_PICTURE_COLUMN"] == "Y"):?>
										<div class="tc offersPicture" style="display: none;">
											<img src="<?=$arNextElement["PICTURE"]["src"]?>" alt="<?=$arNextElement["NAME"]?>">
										</div>
									<?endif;?>
									<div class="tc offersName"><?=
                                        !empty($arNextElement['PROPERTIES']['modific']['VALUE'])
                                            ? $arNextElement['PROPERTIES']['modific']['VALUE']
                                            : $arNextElement["NAME"]
                                    ?></div>
									<?foreach ($arNextElement["PROPERTIES_FILTRED"] as $arNextPropertyFiltred):?>
										<div class="tc property"><?=$arNextPropertyFiltred["DISPLAY_VALUE"]?></div>
									<?endforeach;?>
									<div class="tc quantity">
										<?if($arNextElement["CATALOG_QUANTITY"] > 0):?>
											<span class="inStock label"><img src="<?=SITE_TEMPLATE_PATH?>/images/inStock.svg" alt="<?=GetMessage("AVAILABLE")?>" class="icon"><span><?=GetMessage("AVAILABLE")?></span></span>
										<?elseif($arNextElement["CATALOG_AVAILABLE"] == "Y"):?>
											<span class="onOrder label"><img src="<?=SITE_TEMPLATE_PATH?>/images/onOrder.svg" alt="<?=GetMessage("ON_ORDER")?>" class="icon"><?=GetMessage("ON_ORDER")?></span>
										<?else:?>
											<span class="outOfStock label"><img src="<?=SITE_TEMPLATE_PATH?>/images/outOfStock.svg" alt="<?=GetMessage("CATALOG_NO_AVAILABLE")?>" class="icon"><?=GetMessage("CATALOG_NO_AVAILABLE")?></span>
										<?endif;?>
									</div>
									
									<div class="tc quanBaskWrap">
										<div class="tb">
											<div class="tc priceWrap test">
										<?if(!empty($arNextElement["PRICE"])):?>
											<?if($arNextElement["EXTRA_SETTINGS"]["COUNT_PRICES"] > 1):?>
												<a class="price getPricesWindow" data-id="<?=$arNextElement["ID"]?>">
													<span class="priceIcon"></span><?=CCurrencyLang::CurrencyFormat($arNextElement["PRICE"]["DISCOUNT_PRICE"], $arNextElement["EXTRA_SETTINGS"]["CURRENCY"], true)?>
													<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arNextElement["EXTRA_SETTINGS"]["MEASURES"][$arNextElement["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
														<span class="measure"> / <?=$arNextElement["EXTRA_SETTINGS"]["MEASURES"][$arNextElement["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
													<?endif;?>
													<s class="discount">
														<?if(!empty($arNextElement["PRICE"]["DISCOUNT"])):?>
															<?=CCurrencyLang::CurrencyFormat($arNextElement["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arNextElement["EXTRA_SETTINGS"]["CURRENCY"], true)?>
														<?endif;?>
													</s>
												</a>
											<?else:?>
												<a class="price"><?=CCurrencyLang::CurrencyFormat($arNextElement["PRICE"]["DISCOUNT_PRICE"], $arNextElement["EXTRA_SETTINGS"]["CURRENCY"], true)?>
													<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arNextElement["EXTRA_SETTINGS"]["MEASURES"][$arNextElement["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
														<span class="measure"> / <?=$arNextElement["EXTRA_SETTINGS"]["MEASURES"][$arNextElement["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
													<?endif;?>
													<s class="discount">
														<?if(!empty($arNextElement["PRICE"]["DISCOUNT"])):?>
															<?=CCurrencyLang::CurrencyFormat($arNextElement["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arNextElement["EXTRA_SETTINGS"]["CURRENCY"], true)?>
														<?endif;?>
													</s>
												</a>
											<?endif;?>								
										<?else:?>
											<a class="price"><?=GetMessage("OFFERS_REQUEST_PRICE_LABEL")?></a>
										<?endif;?>
									</div>
											<div class="tc basket">
												<?if($showDetailsButton):?>
													<a href="<?=htmlspecialcharsbx($offerDetailsUrl)?>" class="addCart offerDetails" target="_blank" rel="noopener noreferrer">Подробнее</a>
												<?else:?>
													<?if(!empty($arNextElement["PRICE"])):?>
														<?if($arNextElement["CATALOG_AVAILABLE"] != "Y"):?>
															<?if($arNextElement["CATALOG_SUBSCRIBE"] == "Y"):?>
																<a href="#" class="addCart subscribe" data-id="<?=$arNextElement["ID"]?>" data-quantity="<?=$arNextElement["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><?=GetMessage("PRODUCT_SUBSCRIBE_LABEL")?></a>
															<?else:?>
																<a href="#" class="addCart disabled" data-id="<?=$arNextElement["ID"]?>" data-quantity="<?=$arNextElement["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><?=GetMessage("ADDCART_LABEL")?></a>															
															<?endif;?>
														<?else:?>
															<a href="#" class="addCart" data-id="<?=$arNextElement["ID"]?>" data-quantity="<?=$arNextElement["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><?=GetMessage("ADDCART_LABEL")?></a>														
														<?endif;?>
													<?else:?>
														<a href="#" class="addCart disabled requestPrice" data-id="<?=$arNextElement["ID"]?>" data-quantity="<?=$arNextElement["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><?=GetMessage("OFFERS_REQUEST_PRICE_BUTTON_LABEL")?></a>
													<?endif;?>
												<?endif;?>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?endforeach;?>
						<?if(!empty($arResult["PAGER_ENABLED"]) && !empty($arParams["PAGER_NUM"])):?>
							<div class="catalogProductOffersPager">
								<a href="#" class="catalogProductOffersNext btn-simple btn-small" data-page-num="<?=$arParams["PAGER_NUM"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/plusWhite.svg" alt=""><?=GetMessage("PAGER_NEXT_PAGE_LABEL")?></a>
							</div>
						<?endif;?>
					<?if(empty($arParams["FROM_AJAX"])):?>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			var catalogProductOffersParams = '<?=\Bitrix\Main\Web\Json::encode($arParams);?>';
			var catalogProductOffersAjaxDir = "<?=$this->GetFolder();?>";

			// переключение фото по клику на модификацию,пока сделал по порядку,так как не понятно как делать соответствие
				(function(){
					var table = document.getElementById('skuOffersTable');
					if(!table){
						return;
					}
					table.addEventListener('click', function(e){
						try{
							var row = e.target && e.target.closest ? e.target.closest('.tableElem') : null;
							if(!row){
								return; // клик не по строке оффера
							}

							var clickedLink = e.target && e.target.closest ? e.target.closest('a') : null;
							if(clickedLink && clickedLink.classList && clickedLink.classList.contains('offerDetails')){
								return; // разрешаем обычный переход по ссылке "Подробнее"
							}

							// Блокируем переходы по большинству ссылок внутри строки, кроме окон цен
							if(clickedLink && clickedLink.classList && clickedLink.classList.contains('getPricesWindow') === false){
								e.preventDefault();
							}

							// Ищем индекс строки среди всех .tableElem
							var rows = table.querySelectorAll('.tableElem');
							var index = Array.prototype.indexOf.call(rows, row);
							if(index < 0){
								return;
							}

							// визуально подсветить активную строку
							for(var i=0; i<rows.length; i++){
								rows[i].classList.remove('active');
							}
							row.classList.add('active');

							// Пытаемся переключить слайдер через клик по соответствующей миниатюре
							var thumbs = document.querySelectorAll('#moreImagesCarousel .slideBox .item');
							var thumbsCount = thumbs ? thumbs.length : 0;
							if(thumbs && thumbsCount){
								var thumbIndex = index % thumbsCount; // зацикливание индекса
								thumbs[thumbIndex].dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}));
								return;
							}

							// Если миниатюр нет, двигаем основной слайдер напрямую
							var pictureSlider = document.querySelector('#pictureContainer .pictureSlider');
							if(pictureSlider){
								var slides = pictureSlider.querySelectorAll('.item');
								var slidesCount = slides ? slides.length : 0;
								if(slidesCount > 0){
									var slideIndex = index % slidesCount; // зацикливание индекса
									pictureSlider.style.left = '-' + (slideIndex * 100) + '%';
								}
							}
						}catch(err){
							// silent
						}
					});
				})();

				// скрыть колонку с кнопкой "Купить" (#elementTools), если есть блок товарных предложений (#skuOffersTable)
				(function(){
					function toggleBuyTools(){
						var offers = document.getElementById('skuOffersTable');
						var tools = document.getElementById('elementTools');
						if(!tools){
							return;
						}
						if(offers){
							tools.style.display = 'none';
						}else{
							tools.style.display = '';
						}
					}

					if(document.readyState === 'loading'){
						document.addEventListener('DOMContentLoaded', toggleBuyTools);
					}else{
						toggleBuyTools();
					}

					if(window.BX && BX.addCustomEvent){
						BX.addCustomEvent('onAjaxSuccess', toggleBuyTools);
					}

					if(window.MutationObserver){
						try{
							var mo = new MutationObserver(function(){ toggleBuyTools(); });
							mo.observe(document.body, {childList:true, subtree:true});
						}catch(e){ /* silent */ }
					}
				})();
			</script>
	<?endif;//empty($arParams["FROM_AJAX"])?>
<?endif;?>