(function($){

    $(document).ready(function(){
        console.log("track popup JS");
        //tabs
        var tabs_args = {}

        var tabs_container = $('#track-popup-tabs');
        var tabs = tabs_container.find('>ul li');
        var active_tab_link = tabs.filter('.current-action-tab');
        if (active_tab_link.length > 0){
            tabs_args.active = tabs.index( active_tab_link );
        }

        tabs_container.tabs(tabs_args);
    });

})(jQuery);

