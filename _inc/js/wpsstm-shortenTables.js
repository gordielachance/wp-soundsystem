(function( $ ){
   $.fn.shortenTable = function(max_rows,items_selector) {

        if( (typeof items_selector === typeof undefined) || !items_selector ) {
            items_selector = '> *';
        }

        //reduce tracklists
        this.each(function() {

            var rows = $(this).find(items_selector);

            if (rows.length == 0) return;
            if (rows.length <= max_rows) return;

            var toggle_expand = $('<p class="wpsstm-toggle-tracklist-length"><button><i class="fa fa-angle-down" aria-hidden="true"></i></button></p>');
            toggle_expand.insertAfter($(this));

            rows.slice(max_rows).hide();

            toggle_expand.click(function(e) {
                e.preventDefault();
                // all trs with level-1 class inside abc table
                rows.show();
                //toggle_expand.find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
                toggle_expand.hide();
            });
        });
       
       
   }; 
})( jQuery );