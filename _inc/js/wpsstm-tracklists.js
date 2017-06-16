(function($){
    
    $.fn.extend({ 
        toggleTracklist: function(options){
            // OPTIONS
            var defaults = {
                childrenMax: 3
            };
            var options =  $.extend(defaults, options);
            
            if ( $(this).attr("data-tracks-count") > 0 ) {
                return this.toggleChildren({
                    childrenMax:        options.childrenMax,
                    childrenSelector:   'tbody tr',
                    moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                    lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
                });
            }


        }
    });

    $( document ).on( "wpsstmTrackInit", function( event, track_obj ) {
        
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;
        
        var tracklist_el = track_obj.get_tracklist_el();
        var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.track_idx + 1;
        
        if ( newTracksCount <= visibleTracksCount ) return;
        
        tracklist_el.toggleTracklist({
            childrenMax:newTracksCount
        });
        
    });
    
    $(document).ready(function(){

        $('.wpsstm-tracklist table').toggleTracklist();

  });  


})(jQuery);

/*
Displays a box with a text the user can copy.
http://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
*/
function wpsstm_clipboard_box(text) {
    window.prompt(wpsstmL10n.clipboardtext, text);
}
