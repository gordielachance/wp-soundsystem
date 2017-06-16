// HIDE MAX LIST ITEMS JQUERY PLUGIN
// Version: 1.36
// Author: Josh Winn
// Website: www.joshuawinn.com
// Usage: Free and Open Source. WTFPL: http://sam.zoy.org/wtfpl/
(function($){
    $.fn.extend({ 
        toggleChildren: function(options){
            // OPTIONS
            var defaults = {
                childrenSelector:       '> *',
                btMore:                 null, //jQuery item or selector
                btLess:                 null, //jQuery item or selector
                childrenShowCount:      false,
                max:                    3,
                speed:                  1000,
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

                // Get or create "Read More" button
                if ( op.btMore && $(op.btMore).length ) {
                    $btMore = $(op.btMore);
                }else if ( $container.next("toggle-children-more").length > 0 ){ //has already been created
                    $btMore = $container.next("toggle-children-more");
                }else{
                    $btMore = $('<a href="#">'+op.moreText+'</a>');
                    $container.after($btMore);
                }

                $btMore.addClass('toggle-children-link toggle-children-more');
                
                // Show children count
                if(op.childrenShowCount){
                    if ( $btMore.find("toggle-children-count").length > 0 ){ //has already been created
                        $itemsCount = $btMore.find("toggle-children-count");
                    }else{
                        $itemsCount = $('<small class="toggle-children-count" />');
                        $btMore.append($itemsCount);   
                    }
                    $itemsCount.text(totalChildren - op.max);
                }

                // Get or create "Read less" button
                if ( op.btLess && $(op.btLess).length ) {
                    $btLess = $(op.btLess);
                }else if ( $container.next("toggle-children-less").length > 0 ){ //has already been created
                    $btLess = $container.next("toggle-children-less");
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

                // If list has more than the "max" option
                if ( (totalChildren > 0) && (totalChildren > op.max) ){
                    
                    // Initial Page Load: Hide each LI element over the max
                    $children.each(function(index){
                        if ( (index+1) > op.max ) {
                            $(this).hide(0);
                        } else {
                            $(this).show(0);
                        }
                    });

                    // READ MORE
                    $btMore.off('click').on("click", function(e){
                        
                        $btMore.hide();
                        $btLess.show();
                        
                        // Get array of children past the maximum option 
                        var $childrenSliced = $children.slice(op.max);

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

                        // Get array of children past the maximum option 
                        var $childrenSliced = $children.slice(op.max);

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