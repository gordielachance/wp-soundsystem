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
                childrenToShow:         3, //int, or jQuery item/selector
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
                var childEls =          $content.find(op.childrenSelector);
                var btMoreEl =           $container.find('> .toggle-children-more');
                var btLessEl;            $container.find('> .toggle-children-less');
                var speedPerChild;
                var countEl;
                var visibleChildren;
                var hiddenChildren;

                //initialize if not done yet
                if ( !$container.length){
                    $container = $('<span class="toggle-children-container" />');
                    
                    //wrap container
                    $content = $content.wrap($container);
                    
                    /*
                    create nav
                    */
                    
                    //more
                    if ( $(op.btMore).length ) { //existing
                        btMoreEl = $(op.btMore);
                        countEl = btMoreEl.find(".toggle-children-count");
                    }else if( op.btMore === null ){ //new
                        btMoreEl = $('<a href="#">'+op.moreText+'</a>');
                        $content.after(btMoreEl);
                        if(op.childrenShowCount){
                            countEl = $('<small class="toggle-children-count" />');
                            btMoreEl.append(countEl);   
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
                
                //get children to show
                if ( $.isNumeric( op.childrenToShow ) ){ 
                    visibleChildren = $(childEls).slice(0,op.childrenToShow);
                }else{ //show those items
                    visibleChildren = $(childEls).filter(op.childrenToShow);
                }
                
                //get children to hide
                hiddenChildren = $(childEls).not(visibleChildren);

                // Update children count
                if( op.childrenShowCount && $(countEl) ){
                    $(countEl).text( ' +' + hiddenChildren.length );
                }

                // Get animation speed per LI; Divide the total speed by num of LIs. 
                // Avoid dividing by 0 and make it at least 1 for small numbers.
                if ( $(childEls).length > 0 && op.speed > 0  ){ 
                    speedPerChild = Math.round( op.speed / $(childEls).length );
                    if ( speedPerChild < 1 ) { speedPerChild = 1; }
                } else { 
                    speedPerChild = 0; 
                }

                //show & hide children
                visibleChildren.show();
                hiddenChildren.hide();

                // Some items are hidden
                if ( $(childEls).length > $(visibleChildren).length ){
                    
                    //show navigation
                    $(btMoreEl).show();
                    $(btLessEl).hide();

                    // READ MORE
                    $(btMoreEl).off('click').on("click", function(e){
                        
                        $(btMoreEl).hide();
                        $(btLessEl).show();

                        // Sequentially show the list items
                        // For more info on this awesome function: http://goo.gl/dW0nM
                        var i = 0;
                        hiddenChildren.each(function () {
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

                        var i = hiddenChildren.length - 1; 
                        hiddenChildren.each(function () {
                          $(this).delay(speedPerChild*i).slideUp(speedPerChild,'linear');
                          i--;
                        });

                        // Prevent Default Click Behavior (Scrolling)
                        e.preventDefault();
                    });
                    
                }else { //all items are displayed
                    
                    //hide navigation
                    $(btMoreEl).hide();
                    $(btLessEl).hide();
                    
                }
            });
        }
    });
})(jQuery); // End jQuery Plugin
