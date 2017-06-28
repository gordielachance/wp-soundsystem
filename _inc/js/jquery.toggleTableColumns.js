//v1

(function($){
    $.fn.extend({ 
        tableToggleColumns: function(attr = {}) {

            if( (typeof attr.ignore_columns === typeof undefined) || !attr.ignore_columns ) {
                attr.ignore_columns = [];
            }

            if( (typeof attr.unchecked_columns === typeof undefined) || !attr.unchecked_columns ) {
                attr.unchecked_columns = [];
            }

            this.each(function() {

                var $table = $(this);
                
                //Wrap into a container
                var $container = $table.parent(".toggle-table-columns");
                var $toggle_driver;
                var $thead_driver;
                var hasInit = ( $container.length > 0 );
                
                if ( !hasInit ){
                    
                    $container = $table.wrap('<div class="toggle-table-columns" />');
                    var $thead = $container.find('thead');
                    if ($thead.length == 0) return;
                    
                    $thead_driver = $thead.clone();
                    $toggle_driver = $('<table class="toggle-columns-driver"></table>');
                    $toggle_driver.insertBefore( $table );
                    
                //map columns keys
                $thead_driver.find('tr > *').each(function( key, td ) {
                    $(td).attr('data-toggle-column-key',key);
                });

                //ignored columns array
                var $ignore_columns = [];
                $(attr.ignore_columns).each(function( key, selector ) {
                    var $ignore_column = $thead_driver.find(selector);
                    if ($ignore_column.length > 0) $ignore_columns.push($ignore_column);
                });

                //unchecked columns array
                var $unchecked_columns = [];
                $(attr.unchecked_columns).each(function( key, selector ) {
                    var $unchecked_column = $thead_driver.find(selector);
                    if ($unchecked_column.length > 0) $unchecked_columns.push($unchecked_column);
                });

                //remove ignore columns
                $($ignore_columns).each(function( key, el ) {
                    el.remove();
                });

                //remove columns without title
                $thead_driver.find('th').each(function( key, th ) {
                    var content = $(th).html();
                    if (!content) $(th).remove();
                });

                //add checkboxes
                $thead_driver.find('th').each(function( key, th ) {
                    var column_key = Number( $(th).attr('data-toggle-column-key') ) + 1;
                    var $driver_checkbox = $('<input type="checkbox" />');
                    $driver_checkbox.prop('checked',true);
                    $(th).prepend($driver_checkbox);

                    $driver_checkbox.click(function(e) {
                        var $table_column = $table.find('tr >*:nth-child('+column_key+')');
                        if ( $(this).is(':checked') ){
                            $table_column.removeClass('toggle-column-hidden');
                        }else{
                            $table_column.addClass('toggle-column-hidden');
                        }
                    });
                });

                //unchecked columns
                $($unchecked_columns).each(function( key, th ) {
                    var column_key = Number( $(th).attr('data-toggle-column-key') ) + 1;
                    var $driver_checkbox = $(th).find('input');
                    $driver_checkbox.prop('checked',false);
                    var $table_column = $table.find('tr >*:nth-child('+column_key+')');
                    $table_column.addClass('toggle-column-hidden');
                });
                
                $toggle_driver.html($thead_driver);
                    
                }else{
                    $toggle_driver = $container.find('table.toggle-columns-driver');
                    $thead_driver = $toggle_driver.find('thead');
                    var driver_checkboxes = $thead_driver.find('input[type="checkbox"]');
                    
                    //force refresh (double click so we restore the initial state)
                    $(driver_checkboxes).trigger("click");
                    $(driver_checkboxes).trigger("click");
                }

            });
        }
    });
})(jQuery); // End jQuery Plugin