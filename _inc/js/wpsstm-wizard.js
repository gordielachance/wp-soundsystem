/*
helpers
*/

//artist
var artist_helper = $('#wpsstm-wizard-widget-artist');
var artist_helper_input = artist_helper.find('input');
var artist_helper_links = artist_helper.find('a');

artist_helper_input.change(function() {

    var artist = $(this).val();
    artist.trim();

    var lastfm_artist = artist;
    var wrapper = $(this).parents('.wpsstm-wizard-widget');

    var top_tracks_el = $('#wpsstm-wizard-widget-artist-top-tracks a');
    var similar_el = $('#wpsstm-wizard-widget-artist-similar a');

    if (artist){
        var artist_url = 'https://www.last.fm/music/'+lastfm_artist;
        top_tracks_el.attr('data-wpsstm-wizard-click',artist_url+'/+tracks');
        similar_el.attr('data-wpsstm-wizard-click',artist_url + '/+similar');
    }else{
        top_tracks_el.removeAttr( "data-wpsstm-wizard-click" );
        similar_el.removeAttr( "data-wpsstm-wizard-click" );
    }

    wrapper.toggleClass('wpsstm-wizard-widget-success',(artist.length !== 0));

});

//init helper
artist_helper_input.trigger('change');
artist_helper_links.click(function(e) {
    var artist = artist_helper_input.val();
    if (!artist){
        artist_helper_input.focus();
    }
});


//user stations
var user_helper_input = $('#wpsstm-wizard-widget-lastfm-user-stations').find('input');

user_helper_input.change(function() {

    var username = $(this).val();
    username.trim();

    var wrapper = $(this).parents('.wpsstm-wizard-widget');

    var recommandations_el = $('#wpsstm-wizard-widget-lastfm-user-stations-recommendations a');
    var library_el = $('#wpsstm-wizard-widget-lastfm-user-stations-library a');
    var mix_el = $('#wpsstm-wizard-widget-lastfm-user-stations-mix a');

    recommandations_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:recommended');
    library_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:library');
    mix_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:mix');

    wrapper.toggleClass('wpsstm-wizard-widget-success',(username.length !== 0));

});

//init helper
user_helper_input.trigger('change');


/*
WIZARD INPUT hover & fill
*/
var wizard_input_wrapper_el = $('#wpsstm-wizard-search');
var wizard_input_el = wizard_input_wrapper_el.find('input[type=text]');
var wizard_submit_el = wizard_input_wrapper_el.find('input[type=submit]');
var wizard_input_placeholder_default = wizard_input_el.attr('placeholder');

//hover
$('#wizard-wrapper [data-wpsstm-wizard-hover]').hover(function(e){
    e.preventDefault();
    var new_value = $(this).attr('data-wpsstm-wizard-hover');
    if (!new_value) return;
    wizard_input_el.attr('placeholder',new_value);

}, function(){
    wizard_input_el.attr('placeholder',wizard_input_placeholder_default);         
});

//click
$('#wizard-wrapper').on( "click",'[data-wpsstm-wizard-click]', function(e) {
    e.preventDefault();
    var new_value = $(this).attr('data-wpsstm-wizard-click');
    wizard_input_el.val(new_value);
    wizard_input_el.addClass('input-loading');

    if (new_value){
        $('html, body').animate({
            scrollTop: wizard_input_el.offset().top - ( $(window).height() / 3) //not at the very top
        }, 500, function() {
            wizard_submit_el.trigger('click');
        });
    }
});

//tabs
$("#wizard-wrapper #wpsstm-wizard-sections").tabs();

/*
advanced selectors
*/

//should we show it by default ?
$('.wpsstm-wizard-step-content .wpsstm-wizard-selector-advanced').each(function() {
    var advanced_block = $(this);
    var inputs_filled = advanced_block.find('input').filter(function () {
        return !!this.value;
    });
    if ( inputs_filled.length > 0 ){
        $(this).addClass('active');
    }
});

$('.wpsstm-wizard-step-content a.wpsstm-wizard-selector-toggle-advanced').click(function(e) {
    e.preventDefault();
    var selector_row = $(this).parents('tr');
    var advanced_row = selector_row.find('.wpsstm-wizard-selector-advanced');
    advanced_row.toggleClass('active');
});