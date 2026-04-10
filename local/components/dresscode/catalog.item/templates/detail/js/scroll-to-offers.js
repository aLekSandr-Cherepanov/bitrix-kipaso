$(document).ready(function() {
    $('.scrollToOffers').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).data('target');
        
        if ($(target).length) {
            $('html, body').animate({
                scrollTop: $(target).offset().top - 100
            }, 800);
        }
    });
});
