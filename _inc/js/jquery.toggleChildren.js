/*
Forked from HIDE MAX LIST ITEMS JQUERY PLUGIN by Josh Winn (https://github.com/jawinn/Hide-Max-List-Items-Expander-jQuery-Plugin)
*/

(function($){
    $.fn.extend({ 
        toggleChildren: function(options){
            // OPTIONS
            var defaults = {
                childrenSelector:       '> *',
                btMore:                 null, //jQuery item or selector
                btLess:                 null, //jQuery item or selector
                childrenShowCount:      false,
                childrenMax:            3,
                speed:                  500,
                moreText:               'Read more', //if btMore is not defined
                lessText:               'Read less', //if btLess is not defined
            };
            var options =  $.extend(defaults, options);

            // FOR EACH MATCHED ELEMENT
            return this.each(function() {
                var op =                options;
                var $container =        $(this);
                var $children =         $container.find(op.childrenSelector);
                var totalChildren =     $children.length;
                var $btMore;
                var $btLess;
                var speedPerChild;
                var $itemsCount;
                
                //Wrap into a container
                var hasInit = ( $container.parent(".toggle-children-container").length > 0 );
                if ( !hasInit ){
                    $container = $container.wrap('<p class="toggle-children-container" />');
                }

                // Get or create "Read More" button
                if ( op.btMore && $(op.btMore).length ) {
                    $btMore = $(op.btMore);
                }else if ( $container.nextAll(".toggle-children-more").length > 0 ){ //has already been created
                    $btMore = $container.nextAll(".toggle-children-more");
                }else{
                    $btMore = $('<a href="#">'+op.moreText+'</a>');
                    $container.after($btMore);
                }

                $btMore.addClass('toggle-children-link toggle-children-more');
                
                // Show children count
                if(op.childrenShowCount){
                    if ( $btMore.find(".toggle-children-count").length > 0 ){ //has already been created
                        $itemsCount = $btMore.find(".toggle-children-count");
                    }else{
                        $itemsCount = $('<small class="toggle-children-count" />');
                        $btMore.append($itemsCount);   
                    }
                    $itemsCount.text(' +' + (totalChildren - op.childrenMax));
                }

                // Get or create "Read less" button
                if ( op.btLess && $(op.btLess).length ) {
                    $btLess = $(op.btLess);
                }else if ( $container.nextAll(".toggle-children-less").length > 0 ){ //has already been created
                    $btLess = $container.nextAll(".toggle-children-less");
                }else{
                    $btLess = $('<a href="#">'+op.lessText+'</a>');
                    $container.after($btLess);
                }
                
                $btLess.addClass('toggle-children-link toggle-children-less');
                $btLess.hide(); //hide it by default

                // Get animation speed per LI; Divide the total speed by num of LIs. 
                // Avoid dividing by 0 and make it at least 1 for small numbers.
                if ( totalChildren > 0 && op.speed > 0  ){ 
                    speedPerChild = Math.round( op.speed / totalChildren );
                    if ( speedPerChild < 1 ) { speedPerChild = 1; }
                } else { 
                    speedPerChild = 0; 
                }

                // If list has more than the "childrenMax" option
                if ( (totalChildren > 0) && (totalChildren > op.childrenMax) ){
                    
                    // Initial Page Load: Hide each LI element over the max
                    $children.each(function(index){
                        if ( (index+1) > op.childrenMax ) {
                            $(this).hide(0);
                        } else {
                            $(this).show(0);
                        }
                    });
                    
                    // Get array of children past the maximum option 
                    var $childrenSliced = $children.slice(op.childrenMax);

                    // READ MORE
                    $btMore.off('click').on("click", function(e){
                        
                        $btMore.hide();
                        $btLess.show();

                        // Sequentially show the list items
                        // For more info on this awesome function: http://goo.gl/dW0nM
                        var i = 0;
                        $childrenSliced.each(function () {
                          $(this).delay(speedPerChild*i).slideDown(speedPerChild,'linear');
                          i++;
                        });

                        // Prevent Default Click Behavior (Scrolling)
                        e.preventDefault();
                    });
                    
                    // READ LESS
                    $btLess.off('click').on("click", function(e){
                        
                        $btMore.show();
                        $btLess.hide();

                        var i = $childrenSliced.length - 1; 
                        $childrenSliced.each(function () {
                          $(this).delay(speedPerChild*i).slideUp(speedPerChild,'linear');
                          i--;
                        });

                        // Prevent Default Click Behavior (Scrolling)
                        e.preventDefault();
                    });
                    
                }else {
                    // LIST HAS LESS THAN THE MAX
                    // Hide buttons
                    $btMore.hide();
                    $btLess.hide();
                    
                    // Show all list items that may have been hidden
                    $children.each(function(index){
                        $(this).show(0);
                    });
                }
            });
        }
    });
})(jQuery); // End jQuery Plugin