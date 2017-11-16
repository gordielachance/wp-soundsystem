jQuery(document).ready(function($){
    
    /*
    helpers
    */
    
    //artist
    var artist_helper = $('#wpsstm-wizard-helper-artist');
    var artist_helper_input = artist_helper.find('input');
    var artist_helper_links = artist_helper.find('a');

    artist_helper_input.change(function() {
        
        var artist = $(this).val();
        artist.trim();
        
        var lastfm_artist = artist;
        var wrapper = $(this).parents('.wpsstm-wizard-helper');
        
        var top_tracks_el = $('#wpsstm-wizard-helper-artist-top-tracks a');
        var similar_el = $('#wpsstm-wizard-helper-artist-similar a');
        
        if (artist){
            var artist_url = 'https://www.last.fm/music/'+lastfm_artist;
            top_tracks_el.attr('data-wpsstm-wizard-click',artist_url+'/+tracks');
            similar_el.attr('data-wpsstm-wizard-click',artist_url + '/+similar');
        }else{
            top_tracks_el.removeAttr( "data-wpsstm-wizard-click" );
            similar_el.removeAttr( "data-wpsstm-wizard-click" );
        }

        wrapper.toggleClass('wpsstm-wizard-helper-success',(artist.length !== 0));

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
    var user_helper_input = $('#wpsstm-wizard-helper-lastfm-user-stations').find('input');

    user_helper_input.change(function() {
        
        var username = $(this).val();
        username.trim();
        
        var wrapper = $(this).parents('.wpsstm-wizard-helper');
        
        var recommandations_el = $('#wpsstm-wizard-helper-lastfm-user-stations-recommendations a');
        var library_el = $('#wpsstm-wizard-helper-lastfm-user-stations-library a');
        var mix_el = $('#wpsstm-wizard-helper-lastfm-user-stations-mix a');
        
        recommandations_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:recommended');
        library_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:library');
        mix_el.attr('data-wpsstm-wizard-click','lastfm:user:'+username+':station:mix');
        
        wrapper.toggleClass('wpsstm-wizard-helper-success',(username.length !== 0));
        
    });
    
    //init helper
    user_helper_input.trigger('change');

    //wizard URL fill
    $('#wizard-wrapper').on( "click",'[data-wpsstm-wizard-click]', function(e) {
        e.preventDefault();
        var input_el = $('#wpsstm-wizard-input');
        var new_value = $(this).attr('data-wpsstm-wizard-click');
        input_el.val(new_value);
        
        if (new_value){
            $('html, body').animate({
                scrollTop: input_el.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);
        }
        


    });
    
    //tabs
    $("#wizard-wrapper #wpsstm-advanced-wizard-sections").tabs();

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
    
});

