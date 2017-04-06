(function( $ ){
   $.fn.shortenTable = function(items_selector) {
       
        if(typeof items_selector === 'undefined'){
            items_selector = '> *';
        }
       
       var tracklist_tracks_max = 3;
       
        //reduce tracklists
       this.each(function() {

            var rows = $(this).find(items_selector);

            if (rows.length == 0) return;
            if (rows.length <= tracklist_tracks_max) return;

            var toggle_expand = $('<p class="wpsstm-toggle-tracklist-length"><button><i class="fa fa-angle-down" aria-hidden="true"></i></button></p>');
            toggle_expand.insertAfter($(this));

            rows.slice(tracklist_tracks_max).hide();

            toggle_expand.click(function(e) {
                e.preventDefault();
                // all trs with level-1 class inside abc table
                rows.show();
                toggle_expand.hide();
            });
       });
       
       
   }; 
})( jQuery );