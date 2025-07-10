<?$timerUniqId = randString(18);?>
<div class="mainTool">
	<div class="mainToolContainer">
		<div class="mobilePriceContainer">
			<?if(!empty($arResult["EXTRA_SETTINGS"]["SHOW_TIMER"])):?>
				<div class="specialTime smallSpecialTime" id="timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$timerUniqId?>">
					<div class="specialTimeItem">
						<div class="specialTimeItemValue timerDayValue">0</div>
						<div class="specialTimeItemlabel"><?=GetMessage("TIMER_DAY_LABEL")?></div>
					</div>
					<div class="specialTimeItem">
						<div class="specialTimeItemValue timerHourValue">0</div>
						<div class="specialTimeItemlabel"><?=GetMessage("TIMER_HOUR_LABEL")?></div>
					</div>
					<div class="specialTimeItem">
						<div class="specialTimeItemValue timerMinuteValue">0</div>
						<div class="specialTimeItemlabel"><?=GetMessage("TIMER_MINUTE_LABEL")?></div>
					</div>
					<div class="specialTimeItem">
						<div class="specialTimeItemValue timerSecondValue">0</div>
						<div class="specialTimeItemlabel"><?=GetMessage("TIMER_SECOND_LABEL")?></div>
					</div>
				</div>
			<?endif;?>
			<?if(!empty($arResult["PROPERTIES"]["TIMER_LOOP"]["VALUE"])):?>
				<script type="text/javascript">
					$(document).ready(function(){
						$("#timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$timerUniqId?>").dwTimer({
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
						$("#timer_<?=$arResult["EXTRA_SETTINGS"]["TIMER_UNIQ_ID"];?>_<?=$timerUniqId?>").dwTimer({
							endDate: "<?=MakeTimeStamp($arResult["PROPERTIES"]["TIMER_DATE"]["VALUE"], "DD.MM.YYYY HH:MI:SS")?>"
						});
					});
				</script>
			<?endif;?>
			<?if(!empty($arResult["PRICE"])):?>
				<?if($arResult["EXTRA_SETTINGS"]["COUNT_PRICES"] > 1):?>
					<a class="price changePrice getPricesWindow" data-id="<?=$arResult["ID"]?>">
						<?if(!empty($arResult["PRICE"]["DISCOUNT"])):?>
							<span class="priceBlock">
							<span class="oldPriceLabel"><s class="discount"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></s></span>
								<?if(!empty($arResult["PRICE"]["RESULT_PRICE"]["DISCOUNT"])):?>
									<span class="oldPriceLabel"><?=GetMessage("OLD_PRICE_DIFFERENCE_LABEL")?> <span class="economy"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["DISCOUNT"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span></span>
								<?endif;?>
							</span>
						<?endif;?>
						<span class="priceContainer"><span class="priceIcon"></span><span class="priceVal"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span></span>
						<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
							<span class="measure"> / <?=$arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
						<?endif;?>
						<?if(!empty($arResult["PROPERTIES"]["BONUS"]["VALUE"])):?>
							<span class="purchaseBonus"><span class="theme-color">+ <?=$arResult["PROPERTIES"]["BONUS"]["VALUE"]?></span><?=$arResult["PROPERTIES"]["BONUS"]["NAME"]?></span>
						<?endif;?>
					</a>
				<?else:?>
					<a class="price changePrice">
						<?if(!empty($arResult["PRICE"]["DISCOUNT"])):?>
							<span class="priceBlock">
								<span class="oldPriceLabel"><s class="discount"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["BASE_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></s></span>
								<?if(!empty($arResult["PRICE"]["RESULT_PRICE"]["DISCOUNT"])):?>
									<span class="oldPriceLabel"><?=GetMessage("OLD_PRICE_DIFFERENCE_LABEL")?> <span class="economy"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["RESULT_PRICE"]["DISCOUNT"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span></span>
								<?endif;?>
							</span>
						<?endif;?>
						<span class="priceContainer">
							<span class="priceVal"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></span>
							<?if($arParams["HIDE_MEASURES"] != "Y" && !empty($arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"])):?>
								<span class="measure"> / <?=$arResult["EXTRA_SETTINGS"]["MEASURES"][$arResult["CATALOG_MEASURE"]]["SYMBOL_RUS"]?></span>
							<?endif;?>
						</span>
						<?if(!empty($arResult["PROPERTIES"]["BONUS"]["VALUE"])):?>
							<span class="purchaseBonus"><span class="theme-color">+ <?=$arResult["PROPERTIES"]["BONUS"]["VALUE"]?></span><?=$arResult["PROPERTIES"]["BONUS"]["NAME"]?></span>
						<?endif;?>
					</a>
				<?endif;?>
			<?else:?>
				<a class="price changePrice"><?=GetMessage("REQUEST_PRICE_LABEL")?></a>
			<?endif;?>
		</div>
		<div class="mobileButtonsContainer columnRowWrap">
			<div class="addCartContainer">
				<?if(!empty($arResult["PRICE"])):?>
					<?if($arResult["CATALOG_AVAILABLE"] != "Y"):?>
						<?if($arResult["CATALOG_SUBSCRIBE"] == "Y"):?>
							<a href="#" class="addCart subscribe changeID changeQty changeCart" data-id="<?=$arResult["ID"]?>" data-quantity="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><span><img src="<?=SITE_TEMPLATE_PATH?>/images/subscribe.svg" alt="<?=GetMessage("SUBSCRIBE_LABEL")?>" class="icon"><?=GetMessage("SUBSCRIBE_LABEL")?></span></a>
						<?else:?>
							<a href="#" class="addCart changeID changeQty changeCart disabled" data-id="<?=$arResult["ID"]?>" data-quantity="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><span><img src="<?=SITE_TEMPLATE_PATH?>/images/incart.svg" alt="<?=GetMessage("ADDCART_LABEL")?>" class="icon"><?=GetMessage("ADDCART_LABEL")?></span></a>
						<?endif;?>
					<?else:?>
						<a href="#" class="addCart changeID changeQty changeCart" data-id="<?=$arResult["ID"]?>" data-quantity="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><span><img src="<?=SITE_TEMPLATE_PATH?>/images/incart.svg" alt="<?=GetMessage("ADDCART_LABEL")?>" class="icon"><?=GetMessage("ADDCART_LABEL")?></span></a>
					<?endif;?>
				<?else:?>
					<a href="#" class="addCart changeID changeQty changeCart disabled requestPrice" data-id="<?=$arResult["ID"]?>" data-quantity="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>"><span><img src="<?=SITE_TEMPLATE_PATH?>/images/request.svg" alt="<?=GetMessage("REQUEST_PRICE_BUTTON_LABEL")?>" class="icon"><?=GetMessage("REQUEST_PRICE_BUTTON_LABEL")?></span></a>
				<?endif;?>
				<div class="qtyBlock columnRow row">
					<div class="qtyBlockContainer">
						<a href="#" class="minus"></a><input type="text" class="qty"<?if(!empty($arResult["PRICE"]["EXTENDED_PRICES"])):?> data-extended-price='<?=\Bitrix\Main\Web\Json::encode($arResult["PRICE"]["EXTENDED_PRICES"])?>'<?endif;?> value="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>" data-step="<?=$arResult["EXTRA_SETTINGS"]["BASKET_STEP"]?>" data-max-quantity="<?=$arResult["CATALOG_QUANTITY"]?>" data-enable-trace="<?=(($arResult["CATALOG_QUANTITY_TRACE"] == "Y" && $arResult["CATALOG_CAN_BUY_ZERO"] == "N") ? "Y" : "N")?>"><a href="#" class="plus"></a>
					</div>
				</div>
			</div>
			<div class="mobileFastBackContainer row columnRow">
				<a href="#" class="fastBack label changeID<?if(empty($arResult["PRICE"]) || $arResult["CATALOG_AVAILABLE"] != "Y"):?> disabled<?endif;?>" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/fastBack.svg" alt="<?=GetMessage("FASTBACK_LABEL")?>" class="icon"><?=GetMessage("FASTBACK_LABEL")?></a>
			</div>
		</div>
	</div>
</div>
<div class="secondTool">
	<?if(!empty($arParams["DISPLAY_CHEAPER"]) && $arParams["DISPLAY_CHEAPER"] == "Y" && !empty($arParams["CHEAPER_FORM_ID"])):?>
		<div class="row cheaper-container">
			<a href="#" class="cheaper label openWebFormModal<?if(empty($arResult["PRICE"]) || $arResult["CATALOG_AVAILABLE"] != "Y"):?> disabled<?endif;?>" data-id="<?=$arParams["CHEAPER_FORM_ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/cheaper.svg" alt="<?=GetMessage("CHEAPER_LABEL")?>" class="icon"><?=GetMessage("CHEAPER_LABEL")?></a>
		</div>
	<?endif;?>
	<?if(empty($arParams["HIDE_DELIVERY_CALC"]) || !empty($arParams["HIDE_DELIVERY_CALC"]) && $arParams["HIDE_DELIVERY_CALC"] == "N"):?>
		<div class="row delivery-button-container">
			<a href="#" class="deliveryBtn label changeID calcDeliveryButton" data-id="<?=$arResult["ID"]?>"><img src="<?=SITE_TEMPLATE_PATH?>/images/delivery.svg" alt="<?=GetMessage("DELIVERY_LABEL")?>" class="icon"><?=GetMessage("DELIVERY_LABEL")?></a>
		</div>
	<?endif;?>
	<div class="row available-block">
		<?if($arResult["CATALOG_QUANTITY"] > 0):?>
			<?if(!empty($arResult["EXTRA_SETTINGS"]["STORES"]) && $arResult["EXTRA_SETTINGS"]["STORES_MAX_QUANTITY"] > 0):?>
				<a href="#" data-id="<?=$arResult["ID"]?>" class="inStock label eChangeAvailable getStoresWindow"><img src="<?=SITE_TEMPLATE_PATH?>/images/inStock.svg" alt="<?=GetMessage("AVAILABLE")?>" class="icon"><span><?=GetMessage("AVAILABLE")?></span></a>
			<?else:?>
				<span class="inStock label eChangeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/inStock.svg" alt="<?=GetMessage("AVAILABLE")?>" class="icon"><span><?=GetMessage("AVAILABLE")?></span></span>
			<?endif;?>
		<?else:?>
			<?if($arResult["CATALOG_AVAILABLE"] == "Y"):?>
				<a class="onOrder label eChangeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/onOrder.svg" alt="<?=GetMessage("ON_ORDER")?>" class="icon"><?=GetMessage("ON_ORDER")?></a>
			<?else:?>
				<a class="outOfStock label eChangeAvailable"><img src="<?=SITE_TEMPLATE_PATH?>/images/outOfStock.svg" alt="<?=GetMessage("CATALOG_NO_AVAILABLE")?>" class="icon"><?=GetMessage("CATALOG_NO_AVAILABLE")?></a>
			<?endif;?>
		<?endif;?>
	</div>
	<div class="row share-items">
		<div class="ya-share-label"><?=GetMessage("SHARE_LABEL")?></div>
		<div class="ya-share2" data-services="vkontakte,facebook,odnoklassniki,moimir,twitter"></div>
	</div>
</div>
