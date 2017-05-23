
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track
var wpsstm_source_requests = [];

(function($){

    $(document).ready(function(){



        //user is not logged
        $('.wpsstm-requires-auth').click(function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                $('#wpsstm-bottom-notice-wp-auth').addClass('active');
            }

        });

    });
    

    

    
    wpsstm_source_requests.cancelTrackSourceRequests = function() {
        for (var i = 0; i < wpsstm_source_requests.length; i++) {
            wpsstm_source_requests[i].abort();
        }

        wpsstm_source_requests.length = 0;
    };



    function wpsstm_populate_track_sources(track_obj){
        if (!$track_obj) return;

        var track_el    = track_obj.get_track_el();
        
        //append sources to track obj
        var source_lis = track_el.find('.trackitem_sources li');
        var sources = [];
        $.each(source_lis, function( index, source_li ) {
            var new_source = {
                type:   $(source_li).attr('data-wpsstm-source-type'),
                src:    $(source_li).find('a').attr('href')
            }
            sources.push(new_source);
        });
        
        if (sources.length > 0){
            track_obj.sources = sources;
        }
        track_el.attr('data-wpsstm-sources-count',sources.length);
    }

    $.fn.wpsstm_init_tracklists = function() {

        this.each(function( i, tracklist_el ) {
            new WpsstmTracklist(tracklist_el);
        });
            
    };

})(jQuery);




