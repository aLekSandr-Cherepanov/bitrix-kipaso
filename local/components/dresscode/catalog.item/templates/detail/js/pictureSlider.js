// global function var
var startPictureElementSlider;
var noZoomer = false;

$(function () {
	//vars
	var carouselStartPosition;
	var touchStartPosition;
	var touchStartedFlag;

	var slideCarouselWidth = 0;
	var slideElementsWidth = 0;

	startPictureElementSlider = function () {
		var $pictureContainer = $("#pictureContainer");
		var $pictureSlider = $pictureContainer.find(".pictureSlider");
		var $pictureSliderElements = $pictureSlider.find(".item");

		var $moreImagesCarousel = $("#moreImagesCarousel");
		var $itemClickToEvent = $moreImagesCarousel.find(".item");

		var elementsCount = $pictureSliderElements.length;
		var currentPosition = 0;

		// add styles
		$pictureContainer.css({
			overflow: "hidden",
			width: "100%"
		});

		$pictureSlider.css({
			width: elementsCount * 100 + "%",
			position: "relative",
			overflow: "hidden",
			display: "table",
			left: "0px"
		});

		$pictureSliderElements.css({
			width: 100 / elementsCount + "%",
			display: "table-cell",
			position: "relative",
			textAlign: "center"
		});

		//set width
		slideCarouselWidth = $pictureSlider[0].offsetWidth;
		slideElementsWidth = slideCarouselWidth / elementsCount;
		slideElementsCount = elementsCount - 1;

		var reCalcVars = function (event) {
			slideCarouselWidth = $pictureSlider[0].offsetWidth;
			slideElementsWidth = slideCarouselWidth / elementsCount;
			slideElementsCount = elementsCount - 1;
		};

		var slideCalcToMove = function (event) {
			$this = $(this);

			if (!$this.hasClass("selected")) {
				$this.siblings(".item").removeClass("selected").find("a").removeClass("zoom");
				$this.addClass("selected").find("a").addClass("zoom");
				event.stopImmediatePropagation();
			}

			return event.preventDefault(slideMove($this.index()));
		};

		var slideMove = function (to) {
			$pictureSlider.animate(
				{
					left: "-" + to * 100 + "%"
				},
				250
			);

			return true;
		};

		var changeActiveMoreElement = function (eq) {
			//get carousel items
			var sliderItems = $("#moreImagesCarousel .item");

			//clear clases
			sliderItems.find("a").removeClass("zoom");

			//add selected
			sliderItems
				.removeClass("selected")
				.eq(eq)
				.addClass("selected")
				.find("a")
				.addClass("zoom");

			//event
			$("#moreImagesCarousel").trigger("changeSlide", eq);
		};

		var sliderStartTouch = function (event) {
			//check length
			if (elementsCount > 1) {
				event.pageX =
					event.type == "touchstart" ? event.originalEvent.touches[0].pageX : event.pageX;
				carouselStartPosition = parseInt($pictureSlider.css("left"), 10);
				touchStartPosition = event.pageX;
				touchStartedFlag = true;
			}

			return event.preventDefault();
		};

		var sliderTouchMove = function (event) {
			if (touchStartedFlag === true) {
				event.pageX =
					event.type == "touchmove" ? event.originalEvent.touches[0].pageX : event.pageX;
				$pictureSlider.css(
					"left",
					carouselStartPosition - (touchStartPosition - event.pageX) + "px"
				);
			}
		};

		var sliderTouchEnd = function (event) {
			if (touchStartedFlag === true) {
				var carouselCurrentPosition = parseInt($pictureSlider.css("left"), 10);
				var carouselMoveDistance = carouselStartPosition - carouselCurrentPosition;

				//set zoomer state
				noZoomer = Math.abs(carouselMoveDistance) > 10 ? true : false;

				touchStartedFlag = false;

				if (carouselCurrentPosition > 0) {
					$pictureSlider.finish().animate(
						{
							left: 0
						},
						200
					);
					changeActiveMoreElement(0);
				} else if (
					slideCarouselWidth - slideElementsWidth <
					Math.abs(carouselCurrentPosition)
				) {
					$pictureSlider.animate(
						{
							left: "-" + slideElementsCount * 100 + "%"
						},
						200
					);
					changeActiveMoreElement(slideElementsCount);
				} else {
					if (Math.abs(carouselMoveDistance) > 60) {
						if (Math.abs(carouselMoveDistance) == carouselMoveDistance) {
							var calcCurrentMove = Math.ceil(
								Math.abs(carouselCurrentPosition) / slideElementsWidth
							);
						} else {
							var calcCurrentMove = Math.floor(
								Math.abs(carouselCurrentPosition) / slideElementsWidth
							);
						}
					} else {
						var calcCurrentMove = Math.ceil(
							Math.abs(carouselStartPosition) / slideElementsWidth
						);
					}

					$pictureSlider.finish().animate(
						{
							left: "-" + calcCurrentMove * 100 + "%"
						},
						200
					);
					changeActiveMoreElement(calcCurrentMove);
				}
			}
		};

		$(window).on("resize", reCalcVars);

		//binds
		$(document).on("click", "#moreImagesCarousel .item", slideCalcToMove);

		//touch
		$(document).on("mousedown touchstart", "#pictureContainer", sliderStartTouch);
		$(document).on("mousemove touchmove", sliderTouchMove);
		$(document).on("mouseup touchend", sliderTouchEnd);
	};

	startPictureElementSlider(); // start slider =)
});
