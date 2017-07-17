(function($){

    $(document).ready(function(){
        console.log("track popup JS");
        //tabs
        var tabs_container = $('#track-popup-tabs');
        var tabs_links = tabs_container.find('>ul li');
        var active_tab_link = tabs_links.filter('.active');
        var active_tab_idx = tabs_links.index( active_tab_link );
        tabs_container.tabs({ active: active_tab_idx });
    });

})(jQuery);

