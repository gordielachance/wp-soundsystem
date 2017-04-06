(function($){

  $(document).ready(function(){

      $('.wpsstm-tracklist-table table').shortenTable('tbody tr');
  });  
})(jQuery);

function wpsstm_nav_previous(){
    console.log('wpsstm_nav_previous()');
    var previous_track_link = jQuery('#wpsstm-player .nav-previous a');
    if ( previous_track_link.length ){
        var url = previous_track_link.attr('href');
        window.location.replace(url);
    }
}


