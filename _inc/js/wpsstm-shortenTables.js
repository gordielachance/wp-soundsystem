(function( $ ){
   $.fn.shortenTable = function(max_rows,items_selector) {

        if( (typeof items_selector === typeof undefined) || !items_selector ) {
            items_selector = '> *';
        }

        //reduce tracklists
        this.each(function() {

            var wrapper = $(this);
            var rows = wrapper.find(items_selector);
            var current_visible_rows = wrapper.attr('data-visible-rows');

            if (rows.length == 0) return;
            if (rows.length <= max_rows) return;

            rows.show();
            rows.slice(max_rows).hide();

            if ( !current_visible_rows ){
                var toggle_expand = $('<p class="wpsstm-toggle-tracklist-length"><button><i class="fa fa-angle-down" aria-hidden="true"></i></button></p>');
                toggle_expand.insertAfter(wrapper);
                toggle_expand.click(function(e) {
                    e.preventDefault();
                    
                    if (!wrapper.hasClass('shortened-table-expanded')){
                        // all trs with level-1 class inside abc table
                        rows.show();
                        toggle_expand.find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
                    }else{
                        
                    }
                    wrapper.toggleClass('shortened-table-expanded');
                    

                });
            }
            
            $(this).addClass('shortened-table');
            $(this).attr('data-visible-rows',max_rows);


        });
       
       
   }; 
})( jQuery );