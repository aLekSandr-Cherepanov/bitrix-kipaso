var timeOutID;
var intervalID;
var flushTimeout;
var lastAddCartText;
var skuLoading = false;
var fastViewOpen = false;
var fastViewStoresOpen = false;
var priceVariantOpen = false;
var specialBlockMoved = false;
var basketProductsNow = false;
var oSkuDropdownOpened = false;
var dropDownListOpened = false;

var loadingPictureControl = function (imagePath, callBack) {
	if (typeof imagePath != "undefined" && imagePath != "") {
		var newImage = new Image();
		newImage.src = imagePath;

		$(newImage)
			.one("load", callBack)
			.each(function () {
				if (this.complete) {
					$(this).load();
				}
			});
	}
};

var checkLazyItems = function () {
	var $lazyItems = $(".lazy");
	$.each($lazyItems, function () {
		var $item = $(this);
		var lazyPath = $item.data("lazy");
		if (typeof lazyPath != "undefined" && lazyPath != "") {
			loadingPictureControl(lazyPath, function () {
				$item.attr("src", lazyPath);
			});
		}
	});
};

var changeAddCartButton = function (jsonData) {
	//search addCart buttons
	if (typeof jsonData["CATEGORIES"] != "undefined") {
		if (typeof jsonData["CATEGORIES"]["READY"] != "undefined") {
			//each basket elements
			$.each(jsonData["CATEGORIES"]["READY"], function (index, nextElement) {
				if (typeof nextElement["PRODUCT_ID"] != "undefined") {
					var $currentButton = $('.addCart[data-id="' + nextElement["PRODUCT_ID"] + '"]');
					if (typeof $currentButton != "undefined") {
						$currentButton.each(function (ii, nextButton) {
							updateAddCartButton($(nextButton));
						});
					}
				}
			});

			//save current values
			basketProductsNow = jsonData;
		}
	}
};

var updateAddCartButton = function ($currentElement) {
	var $imageAfterLoad = $currentElement.find("img");
	$currentElement
		.text(LANG["ADDED_CART_SMALL"])
		.attr("href", SITE_DIR + "personal/cart/")
		.prepend($imageAfterLoad.attr("src", TEMPLATE_PATH + "/images/added.svg"))
		.addClass("added");
};

var cartReload = function () {
	if (typeof window.topCartTemplate == "undefined") {
		window.topCartTemplate = "topCart";
	}

	if (typeof window.wishListTemplate == "undefined") {
		window.wishListTemplate = ".default";
	}

	if (typeof window.compareTemplate == "undefined") {
		window.compareTemplate = ".default";
	}

	$.get(
		ajaxPath +
			"?act=flushCart&topCartTemplate=" +
			window.topCartTemplate +
			"&wishListTemplate=" +
			window.wishListTemplate +
			"&compareTemplate=" +
			window.compareTemplate,
		function (data) {
			var $items = $(data).find(".dl");

			$("#flushTopCart").html($items.eq(0).html());
			$("#flushFooterCart").html($items.eq(1).html());
			$("#flushTopwishlist").html($items.eq(2).html());
			$("#flushTopCompare").html($items.eq(3).html());
		}
	);
};

$(function () {
	$(".questions-answers-list .question").click(function () {
		var par = $(this).parents(".question-answer-wrap");
		par.toggleClass("active").find(".answer").slideToggle();
	});

	$(".banner-animated").addClass("banner-image-load");

	// open tabs
	$(".tab-link").click(function () {
		if (!$(this).hasClass("active")) {
			var par = $(this).parents(".tabs-wrap");
			var index = $(this).index();

			$(this).addClass("active").siblings().removeClass("active");
			$(".tab-content:eq(" + index + ")", par)
				.addClass("active")
				.siblings()
				.removeClass("active");
		}
	});

	// call the modal window on the feedback block
	$(".consultation-btn").click(function () {
		$(".callBack").click();
	});

	if ($("#footerTabs .tab").size() == 0) {
		$("#footerTabs, #footerTabsCaption").remove();
	} else {
		$("#footerTabsCaption .item").eq(0).find("a").addClass("selected");
		$("#footerTabs .tab").eq(0).addClass("selected");
	}

	if ($("#infoTabs .tab").size() == 0) {
		$("#infoTabs, #infoTabsCaption").remove();
	} else {
		$("#infoTabsCaption .item").eq(0).find("a").addClass("selected");
		$("#infoTabs .tab").eq(0).addClass("selected");
	}

	var $upButton = $("#upButton");

	$(window).on("ready scroll", function (event) {
		var curScrollValueY = event.currentTarget.scrollY
			? event.currentTarget.scrollY
			: $(window).scrollTop();
		if (curScrollValueY > 0) {
			$upButton.addClass("enb");
		} else {
			$upButton.removeClass("enb");
		}
	});

	$upButton.on("click", function (event) {
		$("html,body").animate(
			{
				scrollTop: 0
			},
			250
		);

		return event.preventDefault();
	});
});

$(window).on("ready", function (event) {
	//vars
	var $topMenuContainer = $("#mainMenuContainer");
	var $mainMenuStaticContainer = $("#mainMenuStaticContainer");
	var $body = $("body").removeClass("loading"); // cache body
	var $window = $(window);

	if (
		$("div").is(".global-block-container") &&
		$("div").is(".global-information-block") &&
		$("div").is(".global-information-block-cn")
	) {
		//disable fixed menu
		_topMenuNoFixed = true;

		//vars
		var $globalBlockContainer = $body.find(".global-block-container");
		var $globalInformationBlock = $globalBlockContainer.find(".global-information-block");
		var $globalInformationBlockCntr = $globalInformationBlock.find(
			".global-information-block-cn"
		);

		//set height
		$globalBlockContainer.css("min-height", $globalInformationBlock.height());

		if (!$globalInformationBlock.hasClass("no-fixed")) {
			var informBlockOffset = $globalInformationBlock.offset();
			var maxScrollHeight =
				$globalBlockContainer.height() +
				informBlockOffset.top -
				($globalInformationBlockCntr.height() + 24); //24 padding top
		}

		var gbScrollCtr = function (event) {
			var $this = $(this);
			var currentScrollValue = $this.scrollTop();

			if (currentScrollValue >= informBlockOffset.top) {
				if (currentScrollValue >= maxScrollHeight) {
					$globalInformationBlock.addClass("max-scroll");
				} else {
					$globalInformationBlock.removeClass("max-scroll");
				}
				$globalInformationBlock.addClass("fixed");
			} else {
				$globalInformationBlock.removeClass("fixed");
			}
		};

		var reCalcGbParams = function () {
			informBlockOffset = $globalInformationBlock.offset();
			maxScrollHeight =
				$globalBlockContainer.height() +
				informBlockOffset.top -
				($globalInformationBlockCntr.height() + 24); //24 padding top
		};

		$(window).on("scroll", gbScrollCtr);
		$(window).on("resize", reCalcGbParams);
	}

	var moveBlockToContainer = function (blockID, moveBlockID) {
		//set j vars
		var $blockID = $(blockID);
		var $moveBlockID = $(moveBlockID);

		//move
		$moveBlockID.append($blockID);

		//set global flag var
		return (specialBlockMoved = true);
	};

	var setSpecialBlockPosition = function () {
		if ($("div").is("#specialBlock")) {
			if ($(window).width() <= 1850) {
				moveBlockToContainer("#specialBlock", "#specialBlockMoveContainer");
			} else if (specialBlockMoved === true && $(window).width() > 1600) {
				moveBlockToContainer("#specialBlock", "#promoBlock");
			}
		}
	};

	//start form load
	setSpecialBlockPosition();

	//resize events
	$(window).on("resize", setSpecialBlockPosition);

	var getFastView = function (event) {
		var $this = $(this).addClass("loading");
		var $productContainer = $this.parents(".item");
		var productID = $this.data("id");
		var productIblockID = $productContainer.data("product-iblock-id");

		if (productID) {
			$.ajax({
				url:
					ajaxPath +
					"?act=getFastView&product_id=" +
					productID +
					"&product_iblock_id=" +
					productIblockID +
					"&product_currency_id=" +
					$productContainer.data("currency-id") +
					"&product_convert_currency=" +
					$productContainer.data("convert-currency") +
					"&product_price_code=" +
					$productContainer.data("price-code") +
					"&product_hide_measures=" +
					$productContainer.data("hide-measure") +
					"&product_hide_not_available=" +
					$productContainer.data("hide-not-available"),
				success: function (http) {
					// delete fastViewInitPictureCarousel;
					// delete fastViewInitPictureSlider;
					// delete initFastViewApp;
					// delete createFastView;

					//remove fastview window
					$("#appFastView").remove();

					//append to body
					$body.append(http);
					$this.removeClass("loading");

					//unbind last events
					$(document).off("click", "#appFastView .appFastViewExit");
					$(document).off("click", "#appFastView .appFastViewPictureCarouselItem");
					$(document).off("click", "#appFastView .appFastViewPictureCarouselLeftButton");
					$(document).off("click", "#appFastView .appFastViewPictureCarouselRightButton");
					$(document).off("mousemove", "#appFastView .appFastViewPictureSliderItemLink");
					$(document).off("mouseover", "#appFastView .appFastViewPictureSliderItemLink");
					$(document).off("mouseleave", "#appFastView .appFastViewPictureSliderItemLink");

					//start fastView scrips
					initFastViewApp();
					//reload addCart button
					cartReload();
					//subscribe button reload
					subscribeOnline();
				},
				cache: false,
				async: false
			});
			fastViewOpen = true;
		}

		return event.preventDefault();
	};

	var getStoresWindow = function (event) {
		var $this = $(this).addClass("loading");
		var productID = $this.data("id");

		if (productID) {
			$.getJSON(
				ajaxPath + "?act=getAvailableWindow&product_id=" + productID,
				function (json) {
					if (typeof json["COMPONENT_DATA"] != "undefined") {
						$("#fastViewStores").remove();
						$body.append(json["COMPONENT_DATA"]);
						$this.removeClass("loading");
						fastViewStoresOpen = true;
					}
				}
			);
		}

		return event.preventDefault();
	};

	var closeStoresWindow = function (event) {
		$("#fastViewStores").remove();
		fastViewStoresOpen = false;
		return event.preventDefault();
	};

	var getPricesWindow = function (event) {
		var $this = $(this).addClass("loading");
		var $thisContainer = $this.parents(".item");
		var productID = $this.data("id");

		if (productID) {
			$.get(
				ajaxPath +
					"?act=getPricesWindow&product_id=" +
					productID +
					"&product_price_code=" +
					encodeURIComponent($thisContainer.data("price-code")) +
					"&product_currency=" +
					encodeURIComponent($thisContainer.data("currency")),
				function (http) {
					$("#appProductPriceVariant").remove();
					$this.removeClass("loading");
					$body.append(http);

					var thisOffsetLeft = $this.offset().left;
					var thisOffsetTop = $this.offset().top;

					if (thisOffsetLeft + 320 > $(window).width()) {
						thisOffsetLeft = $(window).width() - 334;
					}

					if ($this.data("fixed") == "Y") {
						$("#appProductPriceVariant").css({
							left: thisOffsetLeft,
							top: thisOffsetTop - $(window).scrollTop(),
							position: "fixed"
						});
					} else {
						$("#appProductPriceVariant").css({
							left: thisOffsetLeft,
							top: thisOffsetTop
						});
					}
					priceVariantOpen = true;
				}
			);
		}

		return event.preventDefault();
	};

	var closePricesWindow = function (event) {
		$("#appProductPriceVariant, .priceVariantStyles").remove();
		priceVariantOpen = false;
		return event.preventDefault();
	};

	var giftView = function ($product, http) {
		var $namer = $product.find(".name");
		var $nameMiddler = $namer.find(".middle");
		var $elPicture = $product.find(".picture");
		var $changeFastBack = $product.find(".quickBuy").removeClass("disabled");

		if ($nameMiddler) {
			$namer.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]);
			$nameMiddler.html(http[0]["PRODUCT"]["NAME"]);
		} else {
			$namer
				.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"])
				.html(http[0]["PRODUCT"]["NAME"]);
		}

		$elPicture.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]);
		$elPicture.html($("<img/>").attr("src", http[0]["PRODUCT"]["PICTURE"]));
		$elPicture.append(
			$("<span />", { class: "getFastView" })
				.data("id", http[0]["PRODUCT"]["ID"])
				.data("productId", http[0]["PRODUCT"]["ID"])
				.html(LANG["FAST_VIEW_PRODUCT_LABEL"])
		);

		$product
			.find(".addCart, .quickBuy, .addCompare")
			.data("id", http[0]["PRODUCT"]["ID"])
			.data("productId", http[0]["PRODUCT"]["ID"]);

		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			$product
				.find(".price")
				.html(LANG["GIFT_PRICE_LABEL"] + " ")
				.removeClass("getPricesWindow");
			$product
				.find(".price")
				.append(
					$("<s/>")
						.addClass("discount")
						.html(http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"])
				);
		} else {
			$product
				.find(".price")
				.html(LANG["REQUEST_PRICE_LABEL"])
				.removeClass("getPricesWindow");
		}

		var $changeCart = $product.find(".addCart");

		$changeCart.find("img").remove();
		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			if ($changeCart.hasClass("added")) {
				$changeCart
					.removeClass("disabled")
					.removeClass("requestPrice")
					.html($changeCart.data("cart-label"))
					.prepend(
						$("<img />").attr({
							src: TEMPLATE_PATH + "/images/added.svg",
							class: "icon"
						})
					);
			} else {
				$changeCart
					.removeClass("disabled")
					.removeClass("requestPrice")
					.html($product.data("cart-label"))
					.prepend(
						$("<img />").attr({
							src: TEMPLATE_PATH + "/images/addGift.svg",
							class: "icon"
						})
					)
					.attr("href", "#");
			}
		} else {
			$changeFastBack.addClass("disabled");
			$changeCart
				.addClass("disabled")
				.addClass("requestPrice")
				.html(LANG["REQUEST_PRICE_BUTTON_LABEL"])
				.prepend(
					$("<img />").attr({ src: TEMPLATE_PATH + "/images/request.svg", class: "icon" })
				)
				.attr("href", "#");
			http[0]["PRODUCT"]["CAN_BUY"] = "N";
		}

		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			if (
				$product.data("hide-measure") != "Y" &&
				http[0]["PRODUCT"]["MEASURE"] != undefined &&
				http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] != ""
			) {
				$product
					.find(".price")
					.find(".discount")
					.append(
						$("<span/>")
							.addClass("measure")
							.html(" / " + http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] + " ")
					);
			}
		}

		var $changeAvailable = $product.find(".changeAvailable");

		$changeAvailable.removeClass("getStoresWindow");
		$changeAvailable.removeClass("outOfStock");
		$changeAvailable.removeClass("onOrder");
		$changeAvailable.removeClass("inStock");
		$changeAvailable.removeAttr("href");

		if (http[0]["PRODUCT"]["CATALOG_QUANTITY"] > 0) {
			if (http[0]["PRODUCT"]["STORES_COUNT"] > 1) {
				$changeAvailable
					.html($("<span/>").html(LANG["CATALOG_AVAILABLE"]))
					.addClass("inStock")
					.attr("href", "#")
					.addClass("getStoresWindow")
					.data("id", http[0]["PRODUCT"]["ID"])
					.data("productId", http[0]["PRODUCT"]["ID"]);
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
				);
			} else {
				$changeAvailable.html(LANG["CATALOG_AVAILABLE"]).addClass("inStock");
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
				);
			}
		} else {
			if (http[0]["PRODUCT"]["CAN_BUY"] != "Y") {
				$changeAvailable.html(LANG["CATALOG_NO_AVAILABLE"]).addClass("outOfStock");
				$changeFastBack.addClass("disabled");
				$changeCart.addClass("disabled");
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/outOfStock.svg")
				);
			} else {
				$changeAvailable.html(LANG["CATALOG_ON_ORDER"]).addClass("onOrder");
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/onOrder.svg")
				);
			}
		}
	};

	var fastViewSku = function ($product, http) {
		var $namer = $product.find(".appFastViewProductHeadingLink");
		var $elPicture = $product.find(".picture");
		var $changeFastBack = $product.find(".quickBuy").removeClass("disabled");

		$namer.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]).html(http[0]["PRODUCT"]["NAME"]);

		$elPicture.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]);
		$elPicture.html($("<img/>").attr("src", http[0]["PRODUCT"]["PICTURE"]));

		$product
			.find(".addCart, .quickBuy, .addCompare")
			.data("id", http[0]["PRODUCT"]["ID"])
			.attr("data-id", http[0]["PRODUCT"]["ID"]);
		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			$product
				.find(".price")
				.html(
					$("<span />", { class: "priceVal" }).html(
						http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"] + " "
					)
				)
				.removeClass("getPricesWindow");
		} else {
			$product
				.find(".price")
				.html($("<span />", { class: "priceVal" }).html(LANG["REQUEST_PRICE_LABEL"]))
				.removeClass("getPricesWindow")
				.removeAttr("href");
			http[0]["PRODUCT"]["CAN_BUY"] = "N";
		}
		if (http[0]["PRODUCT"]["RESULT_PROPERTIES"]) {
			$product.find(".changeProperties").html(http[0]["PRODUCT"]["RESULT_PROPERTIES"]);
		}

		var $changeCart = $product.find(".addCart").removeClass("subscribe unSubscribe");

		$changeCart.find("img").remove();
		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			$changeCart
				.removeClass("added")
				.removeClass("disabled")
				.removeClass("requestPrice")
				.html(LANG["ADD_BASKET_DEFAULT_LABEL"])
				.prepend(
					$("<img />").attr({ src: TEMPLATE_PATH + "/images/incart.svg", class: "icon" })
				)
				.attr("href", "#");
		} else {
			$changeFastBack.addClass("disabled");
			$changeCart
				.removeClass("added")
				.addClass("disabled")
				.addClass("requestPrice")
				.html(LANG["REQUEST_PRICE_BUTTON_LABEL"])
				.prepend(
					$("<img />").attr({ src: TEMPLATE_PATH + "/images/request.svg", class: "icon" })
				)
				.attr("href", "#");
		}

		if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
			if (
				$product.data("hide-measure") != "Y" &&
				http[0]["PRODUCT"]["MEASURE"] != undefined &&
				http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] != ""
			) {
				$product.find(".price").append(
					$("<span/>")
						.addClass("measure")
						.html(" / " + http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] + " ")
				);
			}
		}

		if (http[0]["PRODUCT"]["PRICE"]["RESULT_PRICE"]["DISCOUNT"] > 0) {
			$product
				.find(".price")
				.append(
					$("<span/>")
						.addClass("oldPriceLabel")
						.html(CATALOG_LANG["FAST_VIEW_OLD_PRICE_LABEL"])
						.append(
							$("<s/>")
								.addClass("discount")
								.html(http[0]["PRODUCT"]["PRICE"]["RESULT_PRICE"]["BASE_PRICE"])
						)
				);
		}

		if (http[0]["PRODUCT"]["COUNT_PRICES"] > 1) {
			$product
				.find(".price")
				.addClass("getPricesWindow")
				.data("id", http[0]["PRODUCT"]["ID"])
				.data("productId", http[0]["PRODUCT"]["ID"])
				.prepend($("<span/>", { class: "priceIcon" }))
				.attr("href", "#");
		} else {
			$product.find(".price").removeAttr("href").find(".priceIcon").remove();
		}

		var $changeAvailable = $product.find(".changeAvailable");

		$changeAvailable.removeClass("getStoresWindow");
		$changeAvailable.removeClass("outOfStock");
		$changeAvailable.removeClass("onOrder");
		$changeAvailable.removeClass("inStock");
		$changeAvailable.removeAttr("href");

		if (http[0]["PRODUCT"]["CATALOG_QUANTITY"] > 0) {
			if (http[0]["PRODUCT"]["STORES_COUNT"] > 1) {
				$changeAvailable
					.html($("<span/>").html(LANG["CATALOG_AVAILABLE"]))
					.addClass("inStock")
					.attr("href", "#")
					.addClass("getStoresWindow")
					.data("id", http[0]["PRODUCT"]["ID"])
					.data("productId", http[0]["PRODUCT"]["ID"]);
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
				);
			} else {
				$changeAvailable.html(LANG["CATALOG_AVAILABLE"]).addClass("inStock");
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
				);
			}
		} else {
			if (http[0]["PRODUCT"]["CAN_BUY"] != "Y") {
				$changeAvailable.html(LANG["CATALOG_NO_AVAILABLE"]).addClass("outOfStock");
				$changeFastBack.addClass("disabled");

				if (
					http[0]["PRODUCT"]["CATALOG_SUBSCRIBE"] == "Y" &&
					http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]
				) {
					$changeCart
						.html(LANG["ADD_SUBSCRIBE_LABEL"])
						.prepend(
							$("<img />").attr({
								src: TEMPLATE_PATH + "/images/subscribe.svg",
								class: "icon"
							})
						)
						.attr("href", "#")
						.addClass("subscribe");
				} else {
					$changeCart.addClass("disabled");
				}

				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/outOfStock.svg")
				);
			} else {
				$changeAvailable.html(LANG["CATALOG_ON_ORDER"]).addClass("onOrder");
				$changeAvailable.prepend(
					$("<img/>")
						.addClass("icon")
						.attr("src", TEMPLATE_PATH + "/images/onOrder.svg")
				);
			}
		}

		//article

		if (typeof http[0]["PRODUCT"]["PROPERTIES"]["CML2_ARTICLE"] != "undefined") {
			if (
				typeof http[0]["PRODUCT"]["PROPERTIES"]["CML2_ARTICLE"]["VALUE"] != "undefined" &&
				http[0]["PRODUCT"]["PROPERTIES"]["CML2_ARTICLE"]["VALUE"] != ""
			) {
				$product
					.find(".changeArticle")
					.html(http[0]["PRODUCT"]["PROPERTIES"]["CML2_ARTICLE"]["VALUE"])
					.parents(".article")
					.removeClass("hidden");
			} else {
				if ($product.find(".changeArticle").data("first-value")) {
					$product
						.find(".changeArticle")
						.html($product.find(".changeArticle").data("first-value"));
				} else {
					$product.find(".changeArticle").parents(".article").addClass("hidden");
				}
			}
		}

		//desc
		var $productDescription = $product.find(".appFastViewDescription");
		var $productDescriptionText = $productDescription.find(".appFastViewDescriptionText");
		if (http[0]["PRODUCT"]["PREVIEW_TEXT"]) {
			$productDescription.addClass("visible");
			$productDescriptionText.html(http[0]["PRODUCT"]["PREVIEW_TEXT"]);
		} else {
			$productDescription.removeClass("visible");
		}

		//QTY BOX

		//get qty box ()
		var $qtyBox = $product.find(".catalogQtyBlock .catalogQty");
		$qtyBox.removeAttr("data-extended-price").removeData("extended-price");

		//write values
		$qtyBox
			.val(http[0]["PRODUCT"]["BASKET_STEP"])
			.data("max-quantity", http[0]["PRODUCT"]["CATALOG_QUANTITY"])
			.data("step", http[0]["PRODUCT"]["BASKET_STEP"])
			.removeClass("error");
		$changeCart.data("quantity", http[0]["PRODUCT"]["BASKET_STEP"]);

		if (typeof http[0]["PRODUCT"]["PRICE"]["EXTENDED_PRICES_JSON_DATA"] != "undefined") {
			if (http[0]["PRODUCT"]["PRICE"]["EXTENDED_PRICES_JSON_DATA"] != "") {
				$qtyBox.data(
					"extended-price",
					http[0]["PRODUCT"]["PRICE"]["EXTENDED_PRICES_JSON_DATA"]
				);
			}
		}

		if (
			http[0]["PRODUCT"]["CATALOG_QUANTITY_TRACE"] == "Y" &&
			http[0]["PRODUCT"]["CATALOG_CAN_BUY_ZERO"] == "N"
		) {
			$qtyBox.data("enable-trace", "Y");
		} else {
			$qtyBox.data("enable-trace", "N");
		}

		if (http[0]["PRODUCT"]["IMAGES"]) {
			var $appFastViewPictureSliderItems = $product
				.find(".appFastViewPictureSliderItems")
				.css({ left: 0 });
			var $appFastViewPictureCarouselItems = $product
				.find(".appFastViewPictureCarouselItems")
				.css({ left: 0 });

			$appFastViewPictureSliderItems.empty();
			$appFastViewPictureCarouselItems.empty();

			$.each(http[0]["PRODUCT"]["IMAGES"], function (i, nextElement) {
				$appFastViewPictureSliderItems.append(
					$("<div />", { class: "appFastViewPictureSliderItem" }).append(
						$("<div />", { class: "appFastViewPictureSliderItemLayout" }).append(
							$("<a />", {
								class: "appFastViewPictureSliderItemLink",
								href: http[0]["PRODUCT"]["DETAIL_PAGE_URL"]
							})
								.data("loupe-picture", nextElement["SUPER_LARGE_PICTURE"]["src"])
								.append(
									$("<img />", {
										class: "appFastViewPictureSliderItemPicture",
										src: nextElement["LARGE_PICTURE"]["src"]
									})
								)
						)
					)
				);

				$appFastViewPictureCarouselItems.append(
					$("<div />", { class: "appFastViewPictureCarouselItem" }).append(
						$("<a />", {
							class: "appFastViewPictureCarouselItemLink",
							href: "#"
						}).append(
							$("<img />", {
								class: "appFastViewPictureCarouselItemPicture",
								src: nextElement["SMALL_PICTURE"]["src"]
							})
						)
					)
				);
			});

			// //addCart button reload
			// changeAddCartButton(basketProductsNow);
			// //subscribe button reload
			// subscribeOnline();

			//sliders
			fastViewInitPictureSlider();
			fastViewInitPictureCarousel();
		}
	};

	var selectSku = function (event) {
		if (skuLoading == true) {
			return false;
		}

		var _params = "";
		var _props = "";
		var _highload = "";
		var _product_width = 200;
		var _product_height = 180;

		var $_this = $(this);
		var $_mProductContainer = $_this.parents(".item");
		var $_mProduct = $_this.parents(".sku");
		var $_parentProp = $_this.parents(".skuProperty");
		var $_propList = $_mProduct.find(".skuProperty");
		var $_clickedProp = $_this.parents(".skuPropertyValue");

		var _level = $_parentProp.data("level");

		$_this.parents(".skuPropertyList").find("li").removeClass("selected");
		$_clickedProp.addClass("selected loading");

		skuLoading = true; //block

		// set product image paramets
		if ($_mProduct.data("product-width") != undefined) {
			_product_width = $_mProduct.data("product-width");
		}

		if ($_mProduct.data("product-height") != undefined) {
			_product_height = $_mProduct.data("product-height");
		}

		$_propList.each(function (i, prop) {
			var $_nextProp = $(prop);
			var $_nextPropList = $_nextProp.find("li");

			var propName = $_nextProp.data("name");
			var _used = false;

			if ($_nextProp.data("highload") == "Y") {
				_highload = _highload + propName + ";";
			}

			$_nextPropList.each(function (io, obj) {
				var $_currentObj = $(obj);
				_props = _props + propName + ":" + $_currentObj.data("value") + ";";
				if ($_currentObj.hasClass("selected")) {
					_params = _params + propName + ":" + $_currentObj.data("value") + ";";
					return (_used = true);
				}
			});

			if (!_used) {
				_params = _params + propName + ":-forse;";
			}
		});

		$.getJSON(
			ajaxPath +
				"?act=selectSku&props=" +
				encodeURIComponent(_props) +
				"&params=" +
				encodeURIComponent(_params) +
				"&level=" +
				_level +
				"&iblock_id=" +
				$_mProduct.data("iblock-id") +
				"&prop_id=" +
				$_mProduct.data("prop-id") +
				"&product_id=" +
				$_mProduct.data("product-id") +
				"&highload=" +
				encodeURIComponent(_highload) +
				"&product_width=" +
				_product_width +
				"&product_height=" +
				_product_height +
				"&product-change-prop=" +
				$_mProduct.data("change-prop") +
				"&product-more-pictures=" +
				$_mProduct.data("more-pictures") +
				"&price-code=" +
				encodeURIComponent($_mProductContainer.data("price-code"))
		)
			.done(function (http) {
				$_propList.each(function (pI, pV) {
					var $_sf = $(pV);
					$_sf.data("level") > _level &&
						$_sf.find(".skuPropertyValue").removeClass("selected").addClass("disabled");
				});
				$.each(http[1]["PROPERTIES"], function (name, val) {
					var $_gPropList = $_propList.filter(function () {
						return $(this).data("name") == name;
					});
					var $_gPropListValues = $_gPropList.find(".skuPropertyValue");
					$_gPropListValues.each(function (il, element) {
						var $nextElement = $(element);
						$.each(val, function (pVal, _selected) {
							if (pVal == $nextElement.data("value") && _selected != "D") {
								_selected == "Y"
									? $nextElement
											.addClass("selected")
											.removeClass("disabled")
											.trigger("click")
									: $nextElement.removeClass("disabled");
								return false;
							}
						});
					});
				});

				if ($_mProduct.data("cast-func")) {
					eval($_mProduct.data("castFunc"))($_mProduct, http); // callback function for cast sku change.
				} else {
					var $namer = $_mProduct.find(".name");
					var $nameMiddler = $namer.find(".middle");
					var $elPicture = $_mProduct.find(".picture");
					var $changeFastBack = $_mProduct.find(".quickBuy").removeClass("disabled");
					var $quantity = $_mProduct.find(".quantity");

					if ($nameMiddler) {
						$namer.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]);
						$nameMiddler.html(http[0]["PRODUCT"]["NAME"]);
					} else {
						$namer
							.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"])
							.html(http[0]["PRODUCT"]["NAME"]);
					}

					$elPicture.attr("href", http[0]["PRODUCT"]["DETAIL_PAGE_URL"]);
					$elPicture.html($("<img/>").attr("src", http[0]["PRODUCT"]["PICTURE"]));
					$elPicture.append(
						$("<span />", { class: "getFastView" })
							.data("id", http[0]["PRODUCT"]["ID"])
							.html(LANG["FAST_VIEW_PRODUCT_LABEL"])
					);

					$_mProduct
						.find(".addCart, .quickBuy, .addCompare")
						.data("id", http[0]["PRODUCT"]["ID"])
						.attr("data-id", http[0]["PRODUCT"]["ID"]);

					if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
						$_mProduct
							.find(".price")
							.html(http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"] + " ")
							.removeClass("getPricesWindow");
					} else {
						$_mProduct
							.find(".price")
							.html(LANG["REQUEST_PRICE_LABEL"])
							.removeClass("getPricesWindow");
					}

					var $changeCart = $_mProduct
						.find(".addCart")
						.removeClass("subscribe unSubscribe");

					$changeCart.find("img").remove();
					if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
						$changeCart
							.removeClass("added")
							.removeClass("disabled")
							.removeClass("requestPrice")
							.html(
								$("<span/>")
									.html(LANG["ADD_BASKET_DEFAULT_LABEL"])
									.prepend(
										$("<img />").attr({
											src: TEMPLATE_PATH + "/images/incart.svg",
											class: "icon"
										})
									)
							)
							.attr("href", "#");
					} else {
						$changeFastBack.addClass("disabled");
						$changeCart
							.removeClass("added")
							.addClass("disabled")
							.addClass("requestPrice")
							.html(
								$("<span/>")
									.html(LANG["REQUEST_PRICE_BUTTON_LABEL"])
									.prepend(
										$("<img />").attr({
											src: TEMPLATE_PATH + "/images/request.svg",
											class: "icon"
										})
									)
							)
							.attr("href", "#");
						http[0]["PRODUCT"]["CAN_BUY"] = "N";
					}

					if (http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]) {
						if (
							$_mProduct.data("hide-measure") != "Y" &&
							http[0]["PRODUCT"]["MEASURE"] != undefined &&
							http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] != ""
						) {
							$_mProduct.find(".price").append(
								$("<span/>")
									.addClass("measure")
									.html(" / " + http[0]["PRODUCT"]["MEASURE"]["SYMBOL_RUS"] + " ")
							);
						}
					}

					var discountPrice =
						http[0]["PRODUCT"]["PRICE"]["RESULT_PRICE"]["DISCOUNT"] > 0
							? http[0]["PRODUCT"]["PRICE"]["RESULT_PRICE"]["BASE_PRICE"]
							: "";

					$_mProduct
						.find(".price")
						.append($("<s/>").addClass("discount").html(discountPrice));

					if (http[0]["PRODUCT"]["COUNT_PRICES"] > 1) {
						$_mProduct
							.find(".price")
							.addClass("getPricesWindow")
							.data("id", http[0]["PRODUCT"]["ID"])
							.data("productId", http[0]["PRODUCT"]["ID"])
							.prepend($("<span/>", { class: "priceIcon" }))
							.attr("href", "#");
					} else {
						$_mProduct.find(".price").find(".priceIcon").remove();
					}

					var $changeAvailable = $_mProduct.find(".changeAvailable");

					$changeAvailable.removeClass("getStoresWindow");
					$changeAvailable.removeClass("outOfStock");
					$changeAvailable.removeClass("onOrder");
					$changeAvailable.removeClass("inStock");
					$changeAvailable.removeAttr("href");

					if (http[0]["PRODUCT"]["CATALOG_QUANTITY"] > 0) {
						if (http[0]["PRODUCT"]["STORES_COUNT"] > 1) {
							$changeAvailable
								.html($("<span/>").html(LANG["CATALOG_AVAILABLE"]))
								.addClass("inStock")
								.attr("href", "#")
								.addClass("getStoresWindow")
								.data("id", http[0]["PRODUCT"]["ID"])
								.data("productId", http[0]["PRODUCT"]["ID"]);
							$changeAvailable.prepend(
								$("<img/>")
									.addClass("icon")
									.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
							);
						} else {
							$changeAvailable.html(LANG["CATALOG_AVAILABLE"]).addClass("inStock");
							$changeAvailable.prepend(
								$("<img/>")
									.addClass("icon")
									.attr("src", TEMPLATE_PATH + "/images/inStock.svg")
							);
						}
					} else {
						if (http[0]["PRODUCT"]["CAN_BUY"] != "Y") {
							$changeAvailable
								.html(LANG["CATALOG_NO_AVAILABLE"])
								.addClass("outOfStock");
							$changeFastBack.addClass("disabled");

							if (
								http[0]["PRODUCT"]["CATALOG_SUBSCRIBE"] == "Y" &&
								http[0]["PRODUCT"]["PRICE"]["DISCOUNT_PRICE"]
							) {
								$changeCart
									.html(
										$("<span/>")
											.html(LANG["ADD_SUBSCRIBE_LABEL"])
											.prepend(
												$("<img />").attr({
													src: TEMPLATE_PATH + "/images/subscribe.svg",
													class: "icon"
												})
											)
									)
									.attr("href", "#")
									.addClass("subscribe");
							} else {
								$changeCart.addClass("disabled");
							}

							$changeAvailable.prepend(
								$("<img/>")
									.addClass("icon")
									.attr("src", TEMPLATE_PATH + "/images/outOfStock.svg")
							);
						} else {
							$changeAvailable.html(LANG["CATALOG_ON_ORDER"]).addClass("onOrder");
							$changeAvailable.prepend(
								$("<img/>")
									.addClass("icon")
									.attr("src", TEMPLATE_PATH + "/images/onOrder.svg")
							);
						}
					}

					//check quantity field
					if ($quantity.length > 0) {
						//clear
						$quantity
							.parents(".addCartContainer")
							.removeClass("viewed")
							.find(".minus, .plus")
							.removeClass("error");

						//set special attr
						$quantity.data({
							"enable-trace":
								http[0]["PRODUCT"]["CATALOG_QUANTITY_TRACE"] == "Y" &&
								http[0]["PRODUCT"]["CATALOG_CAN_BUY_ZERO"] == "N"
									? "Y"
									: "N",
							"max-quantity": http[0]["PRODUCT"]["CATALOG_QUANTITY"],
							step: http[0]["PRODUCT"]["BASKET_STEP"]
						});

						//set start value
						$quantity.val(http[0]["PRODUCT"]["BASKET_STEP"]);
					}
				}

				//addCart button reload
				changeAddCartButton(basketProductsNow);
				//subscribe button reload
				subscribeOnline();

				$_clickedProp.removeClass("loading");
				skuLoading = false;
			})
			.fail(function (jqxhr, textStatus, error) {
				$_clickedProp.removeClass("loading");
				skuLoading = false;
				alert("Request Failed: " + textStatus + ", " + error);
			});

		event.preventDefault();
	};

	var addSubscribe = function (event) {
		//j vars
		$body = $("body");
		$this = $(this);

		//vars
		productId = $this.data("id");

		//check id
		if (productId != "") {
			//loader
			$this.addClass("loading");

			//get subscribe window
			$.getJSON(
				ajaxPath + "?act=addSubscribe&id=" + productId + "&site_id=" + SITE_ID,
				function (jsonData) {
					if (jsonData["SUCCESS"] == "Y") {
						//metrica
						if (
							typeof globalSettings != "undefined" &&
							typeof globalSettings["TEMPLATE_METRICA_SUBSCRIBE"] != "undefined" &&
							typeof globalSettings["TEMPLATE_METRICA_ID"] != "undefined" &&
							typeof window["yaCounter" + globalSettings["TEMPLATE_METRICA_ID"]] !=
								"undefined"
						) {
							window["yaCounter" + globalSettings["TEMPLATE_METRICA_ID"]].reachGoal(
								globalSettings["TEMPLATE_METRICA_SUBSCRIBE"]
							);
						}

						//show form
						if (jsonData["SUBSCRIBE_FORM"] != "") {
							$body.append(jsonData["SUBSCRIBE_FORM"]);
							$this.removeClass("loading");
						}
					} else {
						console.error(jsonData);
					}
				}
			);
		} else {
			//show error
			console.error("product id not found");
		}

		//block action
		return event.preventDefault();
	};

	var unSubscribe = function (event) {
		//j vars
		$this = $(this);
		$thisImage = $this.find("img");

		//vars
		subscribeId = $this.data("subscribe-id");

		//check id
		if (subscribeId != "") {
			//loader
			$this.addClass("loading");

			//get subscribe window
			$.getJSON(
				ajaxPath + "?act=unSubscribe&subscribeId=" + subscribeId + "&site_id=" + SITE_ID,
				function (jsonData) {
					if (jsonData["SUCCESS"] == "Y") {
						$this
							.data("subscribe-id", "")
							.text(LANG["ADD_SUBSCRIBE_LABEL"])
							.prepend(
								$thisImage.attr({
									src: TEMPLATE_PATH + "/images/subscribe.svg"
								})
							)
							.removeClass("unSubscribe loading");
					} else {
						console.error(jsonData);
					}
				}
			);
		} else {
			//show error
			console.error("product id not found");
		}
		return event.preventDefault();
	};

	var addCart = function (event) {
		var $this = $(this);
		if ($this.hasClass("offerDetails")) {
			return true;
		}
		var $quantityContainer = $this.siblings(".quantityContainer");
		var $quantityField = $quantityContainer.find(".quantity");
		var productID = $this.data("id");
		var quantity = $this.data("quantity");
		var windowDisplay = $this.data("display-window");
		var refreshPage = $this.data("refresh-page");
		var addedLabel = $this.data("cart-label");

		var _arID = [];

		if (!$this.hasClass("disabled") && !$this.hasClass("subscribe")) {
			if ($this.attr("href") === "#") {
				//metrica
				if (
					typeof globalSettings != "undefined" &&
					typeof globalSettings["TEMPLATE_METRICA_ADD_CART"] != "undefined" &&
					typeof globalSettings["TEMPLATE_METRICA_ID"] != "undefined" &&
					typeof window["yaCounter" + globalSettings["TEMPLATE_METRICA_ID"]] !=
						"undefined"
				) {
					window["yaCounter" + globalSettings["TEMPLATE_METRICA_ID"]].reachGoal(
						globalSettings["TEMPLATE_METRICA_ADD_CART"]
					);
				}

				if ($this.hasClass("multi")) {
					if ($this.data("selector") != "" && $this.attr("href") === "#") {
						$this.addClass("loading").text(LANG["ADD_CART_LOADING"]);
						var $addElements = $($this.data("selector")).filter(":not(.disabled)");
						var elementsQuantity = "";
						if ($addElements.length > 0) {
							$addElements.each(function (x, elx) {
								var $elx = $(elx);
								if ($elx.data("id") != "") {
									_arID[x] = $elx.data("id");
									if (parseFloat($elx.data("quantity")) != "") {
										elementsQuantity +=
											$elx.data("id") +
											":" +
											parseFloat($elx.data("quantity")) +
											";";
									}
								}
							});

							if (_arID != "") {
								$.getJSON(
									ajaxPath +
										"?act=addCart&id=" +
										_arID.join(";") +
										"&q=" +
										elementsQuantity +
										"&multi=1&site_id=" +
										SITE_ID,
									function (data) {
										var $imageAfterLoad = $this.find("img");
										$this
											.text(LANG["ADDED_CART_SMALL"])
											.attr("href", SITE_DIR + "personal/cart/")
											.prepend(
												$imageAfterLoad.attr(
													"src",
													TEMPLATE_PATH + "/images/added.svg"
												)
											)
											.removeClass("loading")
											.addClass("added");
										cartReload();
									}
								);
							} else {
								alert("error (5)");
							}
						} else {
							alert("error(6)");
						}
						event.preventDefault();
					}
				} else {
					if (parseInt(productID, 10) > 0) {
						//append loader
						$this.addClass("loading");

						//check quantity fields
						if ($quantityField.length > 0) {
							//get quantity from fields
							var quantityFromField = parseFloat($quantityField.val());

							//reset quantity
							quantity = quantityFromField;
						}

						//send object
						var gObj = {
							act: "addCart",
							id: productID,
							site_id: SITE_ID
						};

						if (quantity > 0) {
							gObj["q"] = quantity;
						}

						$.getJSON(ajaxPath, gObj)
							.done(function (jData) {
								var reloadCart = cartReload();

								//push window component
								if (
									typeof windowDisplay == "undefined" ||
									(typeof windowDisplay != "undefined" && windowDisplay == "Y")
								) {
									if (
										typeof jData["status"] != "undefined" &&
										jData["status"] == true &&
										typeof jData["window_component"] != "undefined" &&
										jData["window_component"] != ""
									) {
										$body.append(jData["window_component"]);
									}
								}

								//change add cart label
								LANG["BASKET_ADDED"] =
									typeof addedLabel == "undefined"
										? LANG["BASKET_ADDED"]
										: addedLabel;

								var $imageAfterLoad = $this.find("img");

								$(".bwOpened").removeClass("bwOpened");
								lastAddCartText = $this.html();

								$this
									.removeClass("loading")
									.addClass("added")
									.addClass("bwOpened")
									.html(LANG["BASKET_ADDED"])
									.prepend(
										$imageAfterLoad.attr(
											"src",
											TEMPLATE_PATH + "/images/added.svg"
										)
									)
									.attr("href", SITE_DIR + "personal/cart/");

								//reload page after add cart
								if (typeof refreshPage != "undefined" && refreshPage == "Y") {
									document.location.reload();
									window.scrollTo(0, 0);
								}
							})
							.fail(function (jqxhr, textStatus, error) {
								$.get(ajaxPath, gObj).done(function (Data) {
									console.log(Data);
								});

								$this.removeClass("loading").addClass("error");

								console.error("Request Failed: " + textStatus + ", " + error);
							});
					}
				}
			} else {
				return true;
			}
		}

		return event.preventDefault();
	};

	var addCompare = function (event) {
		var $this = $(event.currentTarget);
		var $icon = $this.find("img");
		var productID = $this.data("id");

		if ($this.attr("href") == "#") {
			if (parseInt(productID, 10) > 0 && !$this.hasClass("added")) {
				$this.addClass("loading");

				var gObj = {
					id: productID,
					act: "addCompare"
				};

				$.get(ajaxPath, gObj)
					.done(function (hData) {
						if (hData != "") {
							var reloadCart = cartReload();
							if ($this.data("no-label") == "Y") {
								$this
									.removeClass("loading")
									.addClass("added")
									.attr("href", SITE_DIR + "compare/");
							} else {
								$this
									.removeClass("loading")
									.addClass("added")
									.html(LANG["ADD_COMPARE_ADDED"])
									.prepend($icon)
									.attr("href", SITE_DIR + "compare/");
							}
						} else {
							$this.removeClass("loading").addClass("error");
						}
					})
					.fail(function (jqxhr, textStatus, error) {
						$this.removeClass("loading").addClass("error");

						console.error("Request Failed: " + textStatus + ", " + error);
					});
			}

			return event.preventDefault();
		}
	};

	var addWishlist = function (event) {
		var $this = $(event.currentTarget);
		var $icon = $this.find("img");
		var productID = $this.data("id");

		if ($this.attr("href") == "#") {
			if (parseInt(productID, 10) > 0 && !$this.hasClass("added")) {
				$this.addClass("loading");

				var gObj = {
					id: productID,
					act: "addWishlist"
				};

				$.get(ajaxPath, gObj)
					.done(function (hData) {
						if (hData != "") {
							var reloadCart = cartReload();
							if ($this.data("no-label") == "Y") {
								$this
									.removeClass("loading")
									.addClass("added")
									.attr("href", SITE_DIR + "wishlist/");
							} else {
								$this
									.removeClass("loading")
									.addClass("added")
									.html(LANG["WISHLIST_ADDED"])
									.prepend($icon)
									.attr("href", SITE_DIR + "wishlist/");
							}
						} else {
							$this.removeClass("loading").addClass("error");
						}
					})
					.fail(function (jqxhr, textStatus, error) {
						$this.removeClass("loading").addClass("error");

						console.error("Request Failed: " + textStatus + ", " + error);
					});
			}

			return event.preventDefault();
		}
	};

	var removeFromWishlist = function (event) {
		var $this = $(this);
		var $wishlist = $("#wishlist");
		var $parentThis = $(this).parents(".item");
		var productID = $this.data("id");
		$this.addClass("loading");

		var gObj = {
			id: productID,
			act: "removeWishlist"
		};

		$.get(ajaxPath, gObj)
			.done(function (hData) {
				if (hData != "") {
					if ($wishlist.find(".product, .itemRow").length == 1) {
						window.location.reload();
					} else {
						reloadCart = cartReload();
						$parentThis.remove();
					}
				} else {
					$this.removeClass("loading").addClass("error");
				}
			})
			.fail(function (jqxhr, textStatus, error) {
				$this.removeClass("loading").addClass("error");

				console.error("Request Failed: " + textStatus + ", " + error);
			});

		return event.preventDefault();
	};

	var slideCollapsedBlock = function (event) {
		var $collapsed = $("#left").children(".collapsed");
		if (!$collapsed.is(":visible") || $collapsed.hasClass("toggled")) {
			$collapsed.stop().slideToggle().addClass("toggled");
			return event.preventDefault();
		}
	};

	var openSmartFiler = function (event) {
		$smartFilterForm = $("#smartFilterForm");
		if ($(window).width() > 1024) {
			if ($smartFilterForm.is(":visible")) {
				$smartFilterForm.stop().slideUp("fast");
			} else {
				$smartFilterForm.stop().slideDown("fast");
			}
		}
	};

	var openSmartSections = function (event) {
		$smartSections = $("#nextSection ul");
		if ($smartSections.is(":visible")) {
			$smartSections.stop().slideUp("fast");
		} else {
			$smartSections.stop().slideDown("fast");
		}
	};

	var formatPrice = function (data) {
		var price = String(data).split(".");
		var strLen = price[0].length;
		var str = "";

		for (var i = strLen; i > 0; i--) {
			str = str + ((!(i % 3) ? " " : "") + price[0][strLen - i]);
		}

		return str + (price[1] != undefined ? "." + price[1] : "");
	};

	//extented prices
	var catalogReCalcPrice = function ($qtyBox, currentQuantity) {
		if (currentQuantity > 0) {
			//price
			var $priceContainer = $qtyBox.parents(".item").find(".price");
			var $priceValContainer = $priceContainer.find(".priceVal");

			//check for empty
			if ($priceValContainer.length > 0) {
				var $priceContainerStr = $priceValContainer
					.html()
					.replace(/\d\.\d/g, "")
					.replace(/[0-9]/g, "");
			}

			//discount
			var $discountContainer = $priceContainer.find(".discount");
			if ($discountContainer.length > 0) {
				var $discountContainerStr = $discountContainer
					.html()
					.replace(/\d\.\d/g, "")
					.replace(/[0-9]/g, "");
			}

			//get price object
			var obExtentedPrices = $qtyBox.data("extended-price");

			if (typeof obExtentedPrices != "undefined") {
				if (typeof obExtentedPrices == "string") {
					obExtentedPrices = $.parseJSON(obExtentedPrices);
				}
			}

			//check for empty object
			if (typeof obExtentedPrices == "object") {
				//each prices
				$.each(obExtentedPrices, function (index, nextValue) {
					//check for empty quantity
					if (nextValue["QUANTITY_FROM"] != null || nextValue["QUANTITY_TO"] != null) {
						//check for current quantity
						if (
							(nextValue["QUANTITY_FROM"] == null ||
								(nextValue["QUANTITY_FROM"] != "" &&
									currentQuantity >= nextValue["QUANTITY_FROM"])) &&
							(nextValue["QUANTITY_TO"] == null ||
								(nextValue["QUANTITY_TO"] != "" &&
									currentQuantity <= nextValue["QUANTITY_TO"]))
						) {
							//write price
							if (typeof nextValue["DISCOUNT_PRICE"] != "undefined") {
								$priceValContainer.html(
									formatPrice(Number(nextValue["DISCOUNT_PRICE"]).toFixed(0)) +
										$priceContainerStr
								);
							}

							//write discount
							if (typeof nextValue["OLD_PRICE"] != "undefined") {
								$discountContainer.html(
									formatPrice(Number(nextValue["OLD_PRICE"]).toFixed(0)) +
										$discountContainerStr
								);
							}
						}
					}
				});
			}
		}
		return;
	};

	var catalogAddCartPlus = function (event) {
		var $this = $(this);
		var $qtyBox = $this.siblings(".catalogQtyBlock .catalogQty");
		var $addCartBtn = $this.parent().siblings(".addCart");

		var xCurrentQtyValue = Number($qtyBox.val());
		var xQtyStep = Number($qtyBox.data("step"));
		var xQtyExpression = Number((xCurrentQtyValue * 10 + xQtyStep * 10) / 10); //js magic .9999999

		var _enableTrace = $qtyBox.data("enable-trace");
		var _maxQuantity = Number($qtyBox.data("max-quantity"));

		var __qtyError = false;
		var xTmpExpression = 0;

		if (_enableTrace == "Y") {
			xTmpExpression = xQtyExpression;
			xQtyExpression = xQtyExpression > _maxQuantity ? _maxQuantity : xQtyExpression;

			if (xTmpExpression != xQtyExpression) {
				__qtyError = true;
			}
		}

		$qtyBox.val(xQtyExpression);
		$addCartBtn.data("quantity", xQtyExpression);

		//extented prices
		catalogReCalcPrice($qtyBox, xQtyExpression);

		//set or remove error
		__qtyError === true ? $qtyBox.addClass("error") : $qtyBox.removeClass("error");

		return event.preventDefault();
	};

	var catalogAddCartMinus = function (event) {
		var $this = $(this);
		var $qtyBox = $this.siblings(".catalogQtyBlock .catalogQty");
		var $addCartBtn = $this.parent().siblings(".addCart");

		var xCurrentQtyValue = Number($qtyBox.val());
		var xQtyStep = Number($qtyBox.data("step"));
		var xQtyExpression = Number((xCurrentQtyValue * 10 - xQtyStep * 10) / 10); //js magic .9999999

		var _enableTrace = $qtyBox.data("enable-trace");
		var _maxQuantity = Number($qtyBox.data("max-quantity"));

		var __qtyError = false;
		var xTmpExpression = 0;

		xQtyExpression = xQtyExpression > xQtyStep ? xQtyExpression : xQtyStep;

		if (_enableTrace == "Y") {
			xTmpExpression = xQtyExpression;
			xQtyExpression = xQtyExpression > _maxQuantity ? _maxQuantity : xQtyExpression;

			if (xTmpExpression != xQtyExpression) {
				__qtyError = true;
			}
		}

		$qtyBox.val(xQtyExpression);
		$addCartBtn.data("quantity", xQtyExpression);

		//extented prices
		catalogReCalcPrice($qtyBox, xQtyExpression);

		//set or remove error
		__qtyError === true ? $qtyBox.addClass("error") : $qtyBox.removeClass("error");

		return event.preventDefault();
	};

	var catalogAddCartChange = function (event) {
		var $this = $(this);
		var $addCartBtn = $this.parent().siblings(".addCart");

		var xCurrentQtyValue = $this.val();
		var xQtyStep = Number($this.data("step"));

		var _enableTrace = $this.data("enable-trace");
		var _maxQuantity = Number($this.data("max-quantity"));

		var __qtyError = false;
		var xTmpExpression = 0;

		if (xCurrentQtyValue.replace(/[^\d.]/gi, "") != xCurrentQtyValue) {
			xCurrentQtyValue = xQtyStep;
		} else {
			xCurrentQtyValue = Number(xCurrentQtyValue);
		}

		xQtyExpression = Math.ceil(xCurrentQtyValue / xQtyStep) * xQtyStep;

		if (_enableTrace == "Y") {
			xTmpExpression = xQtyExpression;
			xQtyExpression = xQtyExpression > _maxQuantity ? _maxQuantity : xQtyExpression;

			if (xTmpExpression != xQtyExpression) {
				__qtyError = true;
			}
		}

		$this.val(xQtyExpression);
		$addCartBtn.data("quantity", xQtyExpression);

		//extented prices
		catalogReCalcPrice($qtyBox, xQtyExpression);

		//set or remove error
		__qtyError === true ? $this.addClass("error") : $this.removeClass("error");
	};

	var closeElementsAfterClick = function (event) {
		if (fastViewOpen === true) {
			$("#appFastView").remove();
			fastViewOpen = false;
		}

		if (fastViewStoresOpen === true) {
			$("#fastViewStores").remove();
			fastViewStoresOpen = false;
		}

		if (priceVariantOpen === true) {
			$("#appProductPriceVariant").remove();
			priceVariantOpen = false;
		}
	};

	$(document).on("click", "#footerTabsCaption .item", function (event) {
		$(this).find("a").addClass("selected");
		$(this).siblings(".item").find("a").removeClass("selected");
		$("#footerTabs").find(".tab").hide().eq($(this).index()).show();
		event.stopImmediatePropagation();
		return event.preventDefault();
	});

	$(document).on("click", "#infoTabsCaption .item", function (event) {
		$(this).find("a").addClass("selected");
		$(this).siblings(".item").find("a").removeClass("selected");
		$("#infoTabs").find(".tab").hide().eq($(this).index()).show();
		return event.preventDefault();
	});

	//check checkbox by class name on label
	$(".label-class").on("click", function () {
		var $this = $(this);
		var $cTarget = $this.attr("for");
		var $parentForm = $this.parents("form");
		var $cCheckBox = $parentForm.find("." + $cTarget);

		if ($cCheckBox.prop("checked")) {
			$cCheckBox.prop("checked", false).focus();
		} else {
			$cCheckBox.prop("checked", "checked").focus();
		}

		return event.preventDefault();
	});

	//check checkbox by data-for label
	$(".label-for").on("click", function () {
		var $this = $(this);
		var $cTarget = $this.data("for");
		var $parentForm = $this.parents("form");
		var $cCheckBox = $parentForm.find("." + $cTarget);

		if ($cCheckBox.prop("checked")) {
			$cCheckBox.prop("checked", false).focus();
		} else {
			$cCheckBox.prop("checked", "checked").focus();
		}

		return event.preventDefault();
	});

	var openSkuDropDown = function (event) {
		//vars
		var $this = $(this);
		var $dropList = $this.siblings(".oSkuDropdownList");

		//show list
		$dropList.toggleClass("opened");

		//opened flag
		oSkuDropdownOpened = $dropList.hasClass("opened");

		return event.preventDefault();
	};

	var selectSkuDropDownValue = function (event) {
		//vars
		var $this = $(this);
		var $dropList = $this.parents(".oSkuDropdownList");
		var $dropListItems = $dropList.find(".oSkuDropdownListItem").removeClass("selected");
		var $checkedItem = $dropList.siblings(".oSkuCheckedItem");

		if (!$checkedItem.hasClass("noHideChecked")) {
			//hide list
			$dropList.removeClass("opened");

			//opened flag
			oSkuDropdownOpened = false;
		}

		//active
		$this.addClass("selected");

		//write value
		$checkedItem.html($this.text());

		//
		return event.preventDefault();
	};

	var closeSkuDropDown = function (event) {
		//if opened
		if (oSkuDropdownOpened) {
			//block trigger events
			if (typeof event.isTrigger == "undefined") {
				//close
				$(".oSkuDropdownList").removeClass("opened");

				//opened flag
				oSkuDropdownOpened = false;
			}
		}
	};

	//fix top menu
	if ($topMenuContainer.length > 0 && $topMenuContainer.hasClass("auto-fixed")) {
		//vars
		var scrollUnbind = false;
		var topMenuFixed = false;
		var menuStartOffset = 0;
		var menuStartHeight = 0;
		var menuStartbackground = 0;

		//check disable flag
		if (typeof _topMenuNoFixed == "undefined") {
			//functions
			var calcPosFixTopMenu = function (event) {
				//to default
				$topMenuContainer.removeClass("fixed");
				$mainMenuStaticContainer.css("height", "auto");

				//get offset & css value
				menuStartOffset = $topMenuContainer.offset().top;
				menuStartHeight = $topMenuContainer.height();
				menuStartbackground = $topMenuContainer.css("background-color");

				//set flag
				topMenuFixed = false;

				//unbind
				if ($window.width() <= 1024) {
					//bind off
					$window.off("scroll", scrollFixTopMenu);

					//set flag
					scrollUnbind = true;
				} else {
					//check flag
					if (scrollUnbind) {
						//bind on
						$window.on("scroll", scrollFixTopMenu);

						//set flag
						scrollUnbind = false;
					}

					//check
					scrollFixTopMenu();
				}
			};

			var scrollFixTopMenu = function (event) {
				//vars
				var currentScrollPosition =
					typeof event != "undefined" ? event.currentTarget.scrollY : $window.scrollTop();

				if (currentScrollPosition >= menuStartOffset) {
					if (!topMenuFixed) {
						//set clases and css values
						$topMenuContainer.addClass("fixed");
						$mainMenuStaticContainer.css("height", $topMenuContainer.height() + "px");
						$mainMenuStaticContainer.css("background-color", menuStartbackground);

						//set flag
						topMenuFixed = true;
					}
				} else {
					//set clases and css values
					$topMenuContainer.removeClass("fixed");
					//set flag
					topMenuFixed = false;
				}
			};

			//binds
			$window.on("resize", calcPosFixTopMenu);
			$window.on("scroll", scrollFixTopMenu);

			//
			calcPosFixTopMenu();
		}
	}

	var toggleMobileTabs = function (event) {
		//jquery vars
		var $this = $(this);

		//check disabled
		if (!$this.hasClass("noTabs") && $(window).innerWidth() <= 1024) {
			$this.toggleClass("active");
			$this.siblings("ul, .tab").toggle(200);

			//block actions
			return event.preventDefault();
		}
	};

	//quantity functions
	var productQuantityPlus = function (event) {
		//jquery vars
		var $this = $(this),
			$quantityContainer = $this.parents(".quantityContainer"),
			$quantityInput = $quantityContainer.find(".quantity");

		//other
		var quantityParams = {
			max: $quantityInput.data("max-quantity"),
			step: $quantityInput.data("step"),
			value: $quantityInput.val()
		};

		//set float types
		$.each(quantityParams, function (i, val) {
			quantityParams[i] = parseFloat(val);
		});

		//check quantity trace
		quantityParams.trace = $quantityInput.data("enable-trace");

		//clear
		$quantityContainer.find(".minus, .plus").removeClass("error");

		//check max value
		if (
			(quantityParams.trace == "Y" && quantityParams.value < quantityParams.max) ||
			quantityParams.trace == "N"
		) {
			//calculate total quantity
			quantityParams.total = quantityParams.value + quantityParams.step;

			//set quantity
			$quantityInput.val(+quantityParams.total.toFixed(5));
		}

		//excess
		else {
			$this.addClass("error");
		}

		//block actions
		return event.preventDefault();
	};

	var productQuantityMinus = function (event) {
		//jquery vars
		var $this = $(this),
			$quantityContainer = $this.parents(".quantityContainer"),
			$quantityInput = $quantityContainer.find(".quantity");

		//other
		var quantityParams = {
			step: $quantityInput.data("step"),
			value: $quantityInput.val()
		};

		//set float types
		$.each(quantityParams, function (i, val) {
			quantityParams[i] = parseFloat(val);
		});

		//clear
		$quantityContainer.find(".minus, .plus").removeClass("error");

		//calculate total quantity
		quantityParams.total = quantityParams.value - quantityParams.step;

		//check minimal
		quantityParams.total =
			quantityParams.total < quantityParams.step ? quantityParams.step : quantityParams.total;

		//set quantity
		$quantityInput.val(+quantityParams.total.toFixed(5));

		//block actions
		return event.preventDefault();
	};

	//keypress events
	var productQuantityKeypress = function (event) {
		//available characters
		var allowedChars = [44, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57];

		//check which
		if (allowedChars.indexOf(event.which) === -1) {
			//block actions
			return event.preventDefault();
		}
	};

	var productQuantityChange = function (event) {
		//jquery vars
		var $this = $(this),
			$quantityContainer = $this.parents(".quantityContainer");

		//other
		var quantityParams = {
			max: $this.data("max-quantity"),
			step: $this.data("step"),
			value: $this.val()
		};

		//set float types
		$.each(quantityParams, function (i, val) {
			quantityParams[i] = parseFloat(val);
		});

		//check quantity trace
		quantityParams.trace = $this.data("enable-trace");

		//set multiplicity
		var multiplicity = +(quantityParams.value / quantityParams.step).toFixed(5);

		//clear
		$quantityContainer.find(".minus, .plus").removeClass("error");

		//check max value
		if (
			(quantityParams.trace == "Y" && quantityParams.value > quantityParams.max) ||
			quantityParams.trace == "N"
		) {
			//set max value
			quantityParams.value = quantityParams.max;
		}

		//check nan & min value
		else if (isNaN(quantityParams.value) || quantityParams.value < quantityParams.step) {
			//set min value
			quantityParams.value = quantityParams.step;
		}

		//check multiple of number
		else if ((multiplicity ^ 0) !== multiplicity) {
			//get near quantity
			quantityParams.value =
				Math.ceil(quantityParams.value / quantityParams.step) * quantityParams.step;
		}

		//set quantity
		$this.val(+quantityParams.value.toFixed(5));

		//block actions
		return event.preventDefault();
	};

	var productQuantityFocus = function (event) {
		//set viewed class
		$(this).parents(".addCartContainer").addClass("viewed");
	};

	var openDropDownList = function (event) {
		//jquery vars
		var $this = $(this);
		var $dropContainer = $this.siblings(".dropDownItems");
		var $dropItems = $dropContainer.find(".dropDownItem");

		//check opened
		if (!$dropContainer.hasClass("opened")) {
			//show
			$dropContainer.addClass("opened");

			//set opened flag
			dropDownListOpened = true;
		}

		//close
		else {
			closeDropDownList();
		}

		//block actions
		return event.preventDefault();
	};

	var selectDropDown = function (event) {
		//jquery vars
		var $this = $(this);

		//vars
		var $dropContainer = $this.parents(".dropDownList");
		var $dropItems = $dropContainer.find(".dropDownItem");
		var $selectedContainer = $dropContainer.find(".dropDownSelected");

		//other
		var selected = $this.hasClass("selected");

		//reset
		$dropItems.removeClass("selected");

		//set selected
		$this.addClass("selected");

		//change selected html
		$selectedContainer.html($this.html());

		//show
		$dropContainer.find(".dropDownItems").removeClass("opened");

		//set opened flag
		dropDownListOpened = false;

		//check selected
		if (selected == false) {
			//activate event
			$dropContainer.trigger("change");
		}

		//block actions
		return event.preventDefault();
	};

	var closeDropDownList = function () {
		//check opened items
		if (dropDownListOpened === true) {
			//hide
			$(".dropDownItems").removeClass("opened");

			//set opened flag
			dropDownListOpened = false;
		}
	};

	const handleGetUserAgreement = async function (event) {
		event.preventDefault();

		try {
			const url = new URL(ajaxPath, window.location.origin);
			url.searchParams.append("action", "getUserAgreement");
			url.searchParams.append("siteId", SITE_ID);
			url.searchParams.append("languageId", LANGUAGE_ID);

			const request = new Request(url, {
				method: "GET"
			});

			await componentLoader.load(request);
		} catch (error) {
			console.error(error.message, error.reasons?.join("\n"));
		}
	};

	const handleGetRequestPriceForm = async function (event) {
		event.preventDefault();

		const button = event.currentTarget;
		const productId = button.dataset.id;

		if (!productId) {
			console.error("Cannot find product ID from `data-id` attribute");
			return;
		}

		try {
			const url = new URL(ajaxPath, window.location.origin);
			url.searchParams.append("action", "getRequestPriceForm");
			url.searchParams.append("productId", productId);
			url.searchParams.append("siteId", SITE_ID);
			url.searchParams.append("languageId", LANGUAGE_ID);

			const request = new Request(url, {
				method: "GET"
			});

			await componentLoader.load(request);
		} catch (error) {
			console.error(error.message, error.reasons?.join("\n"));
		}
	};

	const handleProductGetQuickBuyForm = async function (event) {
		event.preventDefault();

		const button = event.currentTarget;
		const productId = button.dataset.id;

		if (button.classList.contains("disabled")) {
			return;
		}

		if (!productId) {
			console.error("Cannot find product ID from `data-id` attribute");
			return;
		}

		try {
			const url = new URL(ajaxPath, window.location.origin);
			url.searchParams.append("action", "getProductQuickBuyForm");
			url.searchParams.append("productId", productId);
			url.searchParams.append("siteId", SITE_ID);
			url.searchParams.append("languageId", LANGUAGE_ID);

			const request = new Request(url, {
				method: "GET"
			});

			await componentLoader.load(request);
		} catch (error) {
			console.error(error.message, error.reasons?.join("\n"));
		}
	};

	checkLazyItems();

	$(document).on("click", ".dropDownList .dropDownSelected", openDropDownList);
	$(document).on("click", ".dropDownList .dropDownItem", selectDropDown);
	$(document).on("click", ".dropDownList", function (event) {
		event.stopImmediatePropagation();
	});

	$(document).on("click", closeDropDownList);

	$(document).on("click", ".oSkuDropDownProperty .oSkuCheckedItem", openSkuDropDown);
	$(document).on("click", ".oSkuDropDownProperty .oSkuDropdownListItem", selectSkuDropDownValue);
	$(document).on("click", ".oSkuDropdown", function (event) {
		event.stopImmediatePropagation();
	});
	$(document).on("click", closeSkuDropDown);

	$(document).on("click", ".catalogQtyBlock .catalogPlus", catalogAddCartPlus);
	$(document).on("click", ".catalogQtyBlock .catalogMinus", catalogAddCartMinus);
	$(document).on("change", ".catalogQtyBlock .catalogQty", catalogAddCartChange);

	$(document).on("click", ".skuPropertyLink", selectSku);
	$(document).on("click", ".subscribe:not(.unSubscribe)", addSubscribe);
	$(document).on("click", ".unSubscribe", unSubscribe);
	$(document).on("click", ".addCart, .add-cart", addCart);

	$(document).on("click", "#footer .heading", toggleMobileTabs);

	$(document).on("click", ".addWishlist", addWishlist);
	$(document).on("click", ".addCompare", addCompare);
	$(document).on("click", ".removeFromWishlist", removeFromWishlist);

	$(document).on("click", "#fastViewStores .fastViewStoresExit", closeStoresWindow);
	$(document).on("click", ".getStoresWindow", getStoresWindow);

	$(document).on("click", "#appProductPriceVariant .appPriceVariantExit", closePricesWindow);
	$(document).on("click", ".getPricesWindow", getPricesWindow);

	$(document).on("click", "#catalogMenuHeading", slideCollapsedBlock);
	$(document).on("click", "#smartFilter .heading", openSmartFiler);
	$(document).on("click", "#nextSection .title", openSmartSections);

	$(document).on("click", ".getFastView", getFastView);

	$(document).on("keypress", ".quantityContainer .quantity", productQuantityKeypress);
	$(document).on("change", ".quantityContainer .quantity", productQuantityChange);
	$(document).on("focus", ".quantityContainer .quantity", productQuantityFocus);
	$(document).on("click", ".quantityContainer .minus", productQuantityMinus);
	$(document).on("click", ".quantityContainer .plus", productQuantityPlus);

	$(document).on("click", ".quickBuy", handleProductGetQuickBuyForm);
	$(document).on("click", ".requestPrice", handleGetRequestPriceForm);
	$(document).on("click", ".userAgreement", handleGetUserAgreement);

	$(document).on(
		"click",
		".appFastViewContainer, #fastViewStores .fastViewStoresContainer, #appProductPriceVariant, .getFastView, .getPricesWindow, .addCart",
		function (event) {
			return event.stopImmediatePropagation();
		}
	);

	$(document).on("click", closeElementsAfterClick);
	$(document).ajaxError(function (_, __, settings) {
		console.error("Error requesting page " + settings.url);
	});
});

var formatPrice = function (data) {
	var price = String(data).split(".");
	var strLen = price[0].length;
	var str = "";

	for (var i = strLen; i > 0; i--) {
		str = str + ((!(i % 3) ? " " : "") + price[0][strLen - i]);
	}

	return str + (price[1] != undefined ? "." + price[1] : "");
};

function validateEmail(sEmail) {
	const filter =
		/^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;

	return filter.test(sEmail);
}
