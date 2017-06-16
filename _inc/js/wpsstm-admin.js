(function($){

  $(document).ready(function(){

    //artist lookup
    var artist_lookup_input = $('input.wpsstm-lookup-artist');
    artist_lookup_input.suggest(
        wpsstmL10n.ajaxurl + '?action=wpsstm_artist_lookup',
        { 
            delay: 500, 
            minchars: 2, 
            //multiple: true, 
            //multipleSep: window.tagsBoxL10n.tagDelimiter 
        }
    );

      /*
    $('input[name="wpsstm[artist][search]"]').click(function(event){
            
        $(this).suggest(wpsstmL10n.ajaxurl + '?action=wpsstm_artist_lookup');
        $(this).addClass('loading');
        console.log('loading');
    });
    */

  });  
})(jQuery);

