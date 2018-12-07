(function($){
    
    var formNewTracklist = $('form#wpsstm-new-tracklist');
    var formToggleTracklist = $('form#wpsstm-toggle-tracklists');
    
    var toggleTracklistResults = function(){
        
        var newForm = $('form#wpsstm-new-tracklist');
        var submitEl = newForm.find('button[type="submit"]');
        
        var toggleForm = $('form#wpsstm-toggle-tracklists');
        var visibleTracklistRows = toggleForm.find('li.tracklist-row:visible');
        
        var show = ( visibleTracklistRows.length );
        
        if (show){
            submitEl.hide();
        }else{
            submitEl.show();
        }

        
    }
    
    //at init
    formToggleTracklist.find('button[type="submit"]').hide(); //hide since form is sent through JS
    toggleTracklistResults();
 
    //filter playlists
    formNewTracklist.find('input[type="text"]').on('keyup', function(e){

        e.preventDefault();
        var value = $(this).val().toLowerCase();
        var form = $(this).parents('form');
        var toggleForm = $('#wpsstm-toggle-tracklists');

        
        var tracklist_items = toggleForm.find('#tracklists-manager .tracklist-row');
        

        var has_results = false;
        $(tracklist_items).each(function() {
            var tracklistTitle = $(this).find('[itemprop="name"]').attr('title');
            if (tracklistTitle.toLowerCase().search(value) > -1) {
                $(this).show();
                has_results = true;
            }
            else {
                $(this).hide();
            }
        });
        
        //toggle new playlist button
        toggleTracklistResults();


    });

    //toggle track in playlist
    formToggleTracklist.on( "click",'input[type="radio"]', function(e){
        var form = $(this).closest('form#wpsstm-toggle-tracklists');
        form.submit();
    });

})(jQuery);