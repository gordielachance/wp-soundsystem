/*
Forked from HIDE MAX LIST ITEMS JQUERY PLUGIN by Josh Winn (https://github.com/jawinn/Hide-Max-List-Items-Expander-jQuery-Plugin)
*/

(function($){
    $.fn.extend({ 
        toggleChildren: function(options){
            // OPTIONS
            var defaults = {
                childrenSelector:       '> *',
                btMore:                 null, //jQuery item/selector, null, or false
                btLess:                 null, //jQuery item/selector, null, or false
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
                var $content =          $(this);
                var $container =        $(this).parent(".toggle-children-container");
                var $children =         $content.find(op.childrenSelector);
                var totalChildren =     $children.length;
                var btMoreEl =           $container.find('> .toggle-children-more');
                var btLessEl;            $container.find('> .toggle-children-less');
                var speedPerChild;
                var childrenCountEl;
                
                
                if ( !$container.length){ //not yet initialized
                    $container = $('<p class="toggle-children-container" />');
                    
                    //wrap container
                    $content = $content.wrap($container);
                    
                    /*
                    create nav
                    */
                    
                    //more
                    if ( $(op.btMore).length ) { //existing
                        btMoreEl = $(op.btMore);
                        childrenCountEl = btMoreEl.find(".toggle-children-count");
                    }else if( op.btMore === null ){ //new
                        btMoreEl = $('<a href="#">'+op.moreText+'</a>');
                        $content.after(btMoreEl);
                        if(op.childrenShowCount){
                            childrenCountEl = $('<small class="toggle-children-count" />');
                            btMoreEl.append(childrenCountEl);   
                        }
                    }
                    
                    if ( btMoreEl ){
                        btMoreEl.addClass('toggle-children-link toggle-children-more');
                    }

                    //less
                    if ( $(op.btLess).length ) { //existing
                        btLessEl = $(op.btLess);
                    }else if( op.btLess === null ){ //new
                        btLessEl = $('<a href="#">'+op.lessText+'</a>');
                        $content.after(btLessEl);
                    }
                    
                    if ( btLessEl ){
                        btLessEl.addClass('toggle-children-link toggle-children-less');
                        btLessEl.hide(); //hide it by default
                    }

                }

                // Update children count
                if(op.childrenShowCount && childrenCountEl){
                    childrenCountEl.text(' +' + (totalChildren - op.childrenMax));
                }

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
                    $(btMoreEl).off('click').on("click", function(e){
                        
                        $(btMoreEl).hide();
                        $(btLessEl).show();

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
                    $(btLessEl).off('click').on("click", function(e){
                        
                        $(btMoreEl).show();
                        $(btLessEl).hide();

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
                    $(btMoreEl).hide();
                    $(btLessEl).hide();
                    
                    // Show all list items that may have been hidden
                    $children.each(function(index){
                        $(this).show(0);
                    });
                }
            });
        }
    });
})(jQuery); // End jQuery Plugin
