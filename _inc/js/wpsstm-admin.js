(function($){

  $(document).ready(function(){
      
      $('.wpsstm-tracklist-table table').shortenTable('tbody tr');
      $('.wpsstm-tracklist-list').shortenTable();

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
      
    // MusicBrainz Metabox
    $('#wpsstm-mbdata-fill').click(function(event){
        var container = $(this).closest('#wpsstm-mbdata-fill-container');
        if ( !container.hasClass('is-opened') ){
            event.preventDefault();
            container.addClass('is-opened');
        }
    });
      
      

    
  });  
})(jQuery);

