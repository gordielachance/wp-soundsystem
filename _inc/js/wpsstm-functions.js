var $ = jQuery.noConflict();

$.fn.wpsstmJsonViewer = function() {

    this.filter( "textarea" ).each(function() {
        
        var $input = $(this);
        var $container = $input.closest('.wpsstm-json');
        var $output = $container.find('.wpsstm-json-output');
        
        //setup dom
        if ( $container.length == 0 ){
            $input.addClass('wpsstm-json-input');
            $input.wrap( "<div class='wpsstm-json'></div>" );
            var $container = $(this).parent();
            var $output = $("<div class='wpsstm-json-output'></div>");
            $container.append($output);
        }

        //
        var data = $input.val();
        if (data){
            var json = JSON.parse(data);
            $output.jsonViewer(json,{collapsed: true,rootCollapsable:false});
        }
    });

    return this;

};

function wpsstm_js_notice(msg,preprendTo){
    
    if (typeof preprendTo === 'undefined'){
        preprendTo = $('body');
    }
    
    preprendTo = preprendTo.get(0);

    var noticeBlock = $('<div class="wpsstm-block-notice"></div>').get(0);
    var closeNotice = $('<a href="#" class="wpsstm-close-notice"><i class="fa fa-close"></i></a>').get(0);
    var noticeMessage = $('<span/>').html(msg).get(0);
    noticeBlock.append(noticeMessage);
    noticeBlock.append(closeNotice);

    preprendTo.prepend(noticeBlock);
    
    $('html, body').animate({
        scrollTop: $(noticeBlock).offset().top - ( $(window).height() / 3) //not at the very top
    }, 500);

}

function wpsstm_debug(msg,data){
    if (!wpsstmL10n.debug) return;
    
    //data
    if (typeof data !== 'object'){
        msg = msg + ' - ' + data;
        data = undefined;
    }

    //msg
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
        console.log('[wpsstm]' + msg);
    }
    
    //data
    if (typeof data !== 'undefined'){
        console.log(data);
    }
}

function wpsstm_shuffle(array) {
  var currentIndex = array.length, temporaryValue, randomIndex;

  // While there remain elements to shuffle...
  while (0 !== currentIndex) {

    // Pick a remaining element...
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex -= 1;

    // And swap it with the current element.
    temporaryValue = array[currentIndex];
    array[currentIndex] = array[randomIndex];
    array[randomIndex] = temporaryValue;
  }

  return array;
}

/*
Because we can't (?) switch the outerHTML of nodes, custom-hackish method to update attributes and content.
*/

function wpsstmSwapNode(oldNode,newNode){

    //check both nodes have the same tag
    if (oldNode.tagName !== newNode.tagName){
        console.log("wpsstmSwapNode - tags do not match, abord.");
        return false;
    }

    //remove all old attributes
    while(oldNode.attributes.length > 0){
        oldNode.removeAttribute(oldNode.attributes[0].name);
    }

    //add new attributes
    let attr;
    let attributes = Array.prototype.slice.call(newNode.attributes);
    while(attr = attributes.pop()) {
        oldNode.setAttribute(attr.nodeName, attr.nodeValue);
    }
    
    //switch HTML
    oldNode.innerHTML = newNode.innerHTML;

    return true;

}
