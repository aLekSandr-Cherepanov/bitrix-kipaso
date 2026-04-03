<div class="new-element-tools">
	<div class="new-price-block">
		<?if(!empty($arResult["PRICE"])):?>
			<div class="price-label">от</div>
			<div class="price-value"><?=CCurrencyLang::CurrencyFormat($arResult["PRICE"]["DISCOUNT_PRICE"], $arResult["EXTRA_SETTINGS"]["CURRENCY"], true)?></div>
		<?else:?>
			<div class="price-value"><?=GetMessage("REQUEST_PRICE_LABEL")?></div>
		<?endif;?>
	</div>

	<div class="advantages-list">
		<div class="advantage-item">
			<svg class="check-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
				<rect width="20" height="20" rx="4" fill="#008f86"/>
				<path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span>В наличии или на заказ</span>
		</div>
		<div class="advantage-item">
			<svg class="check-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
				<rect width="20" height="20" rx="4" fill="#008f86"/>
				<path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span>Первичная поверка включена</span>
		</div>
		<div class="advantage-item">
			<svg class="check-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
				<rect width="20" height="20" rx="4" fill="#008f86"/>
				<path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span>Быстрая доставка по России</span>
		</div>
		<div class="advantage-item">
			<svg class="check-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
				<rect width="20" height="20" rx="4" fill="#008f86"/>
				<path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span>Оплата картой, по счёту, или другим способом</span>
		</div>
	</div>

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

	<div class="service-info-block">
		<div class="service-info-item">
			<svg width="30" height="30" viewBox="0 0 32 32" fill="none">
				<rect x="4" y="8" width="24" height="16" rx="2" stroke="#008f86" stroke-width="2"/>
				<path d="M4 12H28" stroke="#008f86" stroke-width="2"/>
			</svg>
			<div class="service-info-content">
				<div class="service-info-title">Рассчитаем стоимость доставки</div>
				<div class="service-info-text">по всей России!</div>
			</div>
		</div>
		<div class="service-info-item">
			<svg width="30" height="30" viewBox="0 0 32 32" fill="none">
				<path d="M28 10.6667L16 17.3333L4 10.6667L16 4L28 10.6667Z" stroke="#008f86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M4 21.3333L16 28L28 21.3333" stroke="#008f86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M4 16L16 22.6667L28 16" stroke="#008f86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<div class="service-info-content">
				<div class="service-info-title">В наличии - отправка день в день</div>
			</div>
		</div>
		<div class="service-info-item">
			<svg width="27" height="27" viewBox="0 0 32 32" fill="none">
				<path d="M28 13.3333L16 2.66666L4 13.3333V26.6667C4 27.3739 4.28095 28.0522 4.78105 28.5523C5.28115 29.0524 5.95942 29.3333 6.66667 29.3333H25.3333C26.0406 29.3333 26.7189 29.0524 27.219 28.5523C27.719 28.0522 28 27.3739 28 26.6667V13.3333Z" stroke="#008f86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M12 29.3333V16H20V29.3333" stroke="#008f86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<div class="service-info-content">
				<div class="service-info-title">Доставка от 1 дня по РФ</div>
			</div>
		</div>
	</div>

	<div class="payment-info-block">
		<div class="payment-title">Оплата:</div>
		<div class="payment-methods">
			<img src="/local/templates/dresscodeV2/images/visa.png" alt="VISA" style="height: 70px;">
			<img src="/local/templates/dresscodeV2/images/mastercard.png" alt="MasterCard" style="height: 36px;">
			<img src="/local/templates/dresscodeV2/images/mir.png" alt="МИР" style="height: 20px;">
			<img src="/local/templates/dresscodeV2/images/schet.svg" alt="Счет" style="height: 37px;">
		</div>
	</div>

	<div class="contact-info-block">
		<div class="contact-phone">
			<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
				<path d="M18.3 14.4C18.3 14.7 18.23 15.01 18.08 15.31C17.93 15.61 17.74 15.9 17.5 16.18C17.12 16.62 16.7 16.93 16.23 17.13C15.77 17.33 15.27 17.43 14.73 17.43C13.94 17.43 13.09 17.24 12.2 16.85C11.31 16.46 10.42 15.94 9.54 15.29C8.65 14.63 7.81 13.91 7.01 13.12C6.22 12.32 5.5 11.48 4.85 10.6C4.21 9.72 3.69 8.84 3.31 7.97C2.93 7.09 2.74 6.24 2.74 5.42C2.74 4.89 2.83 4.39 3.02 3.93C3.21 3.46 3.5 3.04 3.9 2.67C4.38 2.21 4.91 2 5.47 2C5.67 2 5.87 2.04 6.05 2.12C6.24 2.2 6.41 2.32 6.54 2.49L8.76 5.51C8.89 5.67 8.98 5.82 9.04 5.96C9.1 6.09 9.13 6.22 9.13 6.33C9.13 6.48 9.08 6.63 8.98 6.77C8.89 6.91 8.76 7.06 8.6 7.21L8 7.84C7.93 7.91 7.9 7.99 7.9 8.09C7.9 8.14 7.91 8.18 7.92 8.23C7.94 8.28 7.96 8.32 7.97 8.36C8.1 8.61 8.33 8.94 8.66 9.34C9 9.74 9.36 10.15 9.75 10.56C10.17 10.97 10.57 11.34 10.98 11.68C11.38 12.01 11.71 12.23 11.97 12.36C12 12.37 12.04 12.39 12.08 12.41C12.13 12.43 12.18 12.43 12.24 12.43C12.35 12.43 12.43 12.39 12.5 12.32L13.11 11.72C13.27 11.56 13.42 11.43 13.56 11.35C13.7 11.25 13.84 11.2 14 11.2C14.11 11.2 14.23 11.22 14.37 11.28C14.51 11.34 14.66 11.43 14.82 11.55L17.88 13.8C18.05 13.93 18.17 14.08 18.24 14.26C18.3 14.44 18.3 14.62 18.3 14.4Z" stroke="#008f86" stroke-width="1.5" stroke-miterlimit="10"/>
			</svg>
			<a href="tel:88005654972" class="phone-number">(800) 565-49-72</a>
		</div>
		<div class="contact-item">
			<img class="diler-item-icon" src="/local/templates/dresscodeV2/images/deliv.png" alt="">
			<span>Официальный дилер ОВЕН в России</span>
		</div>
	</div>

	<div class="question-button-wrapper">
		<a href="#" class="question-button">Помочь подобрать модификацию</a>
	</div>
</div>
