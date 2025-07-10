var startMorePicturesElementCarousel;

$(function () {
	startMorePicturesElementCarousel = function () {
		//kill last binds
		$(document).off("changeSlide", "#moreImagesCarousel", changeSlideControl);
		$(document).off("click", "#moreImagesRightButton", rightMoveCarousel);
		$(document).off("click", "#moreImagesLeftButton", leftMoveCarousel);

		//settings
		var maxVisibleElements = 5;
		var windowWidth = $(window).innerWidth();
		var resizeVisibleElements = {
			4096: 5,
			1366: 4
		};

		$.each(resizeVisibleElements, function (resolution, visibleElements) {
			if (windowWidth <= resolution) {
				maxVisibleElements = visibleElements;
				return false;
			}
		});

		var $moreImagesCarousel = $("#moreImagesCarousel").addClass("show");
		var $moreImagesSlideBox = $moreImagesCarousel.find(".slideBox");
		var $moreImagesItems = $moreImagesSlideBox.find(".item");

		var elementsCount = $moreImagesItems.length;
		var maxPosition = $moreImagesItems.length - maxVisibleElements;
		var currentPosition = 0;
		var startPosition = 0;

		$moreImagesItems.eq(0).addClass("selected").find("a").addClass("zoom");

		if (elementsCount <= maxVisibleElements) {
			$("#moreImagesRightButton, #moreImagesLeftButton").hide();
			startPosition = (100 / maxVisibleElements) * ((maxVisibleElements - elementsCount) / 2);
		} else {
			$("#moreImagesRightButton, #moreImagesLeftButton").show();
		}

		$moreImagesSlideBox.css({
			width: elementsCount * 100 + "%",
			left: startPosition + "%"
		});

		$moreImagesItems.css({
			width: 100 / elementsCount / maxVisibleElements + "%"
		});

		var carouselMoving = function (to) {
			if (elementsCount > maxVisibleElements) {
				$moreImagesSlideBox.finish().animate(
					{
						left: "-" + (100 / maxVisibleElements) * to + "%"
					},
					200
				);
			}
		};

		var leftMoveCarousel = function (event) {
			if (--currentPosition < 0) {
				currentPosition = maxPosition;
			}
			return event.preventDefault(carouselMoving(currentPosition));
		};

		var rightMoveCarousel = function (event) {
			if (++currentPosition > maxPosition) {
				currentPosition = 0;
			}
			return event.preventDefault(carouselMoving(currentPosition));
		};

		var changeSlideControl = function (event, position) {
			if (typeof position != "undefined" && elementsCount > maxVisibleElements) {
				if (position >= maxVisibleElements) {
					currentPosition = position + 1 - maxVisibleElements;
				} else {
					currentPosition = 0;
				}

				carouselMoving(currentPosition);
			}
		};

		//binds
		$(document).on("click", "#moreImagesRightButton", rightMoveCarousel);
		$(document).on("click", "#moreImagesLeftButton", leftMoveCarousel);

		//event
		$(document).on("changeSlide", "#moreImagesCarousel", changeSlideControl);
	};

	//resize control
	$(window).on("resize", function () {
		startMorePicturesElementCarousel();
	});

	startMorePicturesElementCarousel();
});
