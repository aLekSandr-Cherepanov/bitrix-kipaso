$(document).ready(function() {
    $('[data-fancybox]').fancybox({
        buttons: [
            'zoom',
            'close'
        ],
        loop: false,
        protect: true,
        clickContent: false,
        clickSlide: 'close',
        touch: {
            vertical: false
        }
    });
});
