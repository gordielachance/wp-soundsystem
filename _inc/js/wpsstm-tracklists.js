var $ = jQuery.noConflict();

//tracklist modals
$('body.wpsstm-popup').on('click', 'a.wpsstm-tracklist-popup,li.wpsstm-tracklist-popup>a', function(e) {
    e.preventDefault();

    var content_url = this.href;

    console.log("tracklist popup");
    console.log(content_url);


    var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
    var popup = $('<div></div>').append(loader_el);

    var popup_w = $(window).width() *.75;
    var popup_h = $(window).height() *.75;

    popup.dialog({
        width:popup_w,
        height:popup_h,
        modal: true,
        dialogClass: 'wpsstm-tracklist-dialog wpsstm-dialog dialog-loading',

        open: function(ev, ui){
            $('body').addClass('wpsstm-popup-overlay');
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
            $('body').removeClass('wpsstm-popup-overlay');
        }

    });

});

class WpsstmTracklist extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.index =                    undefined;
        this.post_id =                  undefined;
        this.isExpired =                undefined;
        this.player =                   undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });
    }
    connectedCallback(){
        //console.log("TRACKLIST CONNECTED!");
        /*
        Called every time the element is inserted into the DOM. Useful for running setup code, such as fetching resources or rendering. Generally, you should try to delay work until this time.
        */
        this.render();
    }

    disconnectedCallback(){
        /*
        Called every time the element is removed from the DOM. Useful for running clean up code.
        */
    }
    attributeChangedCallback(attrName, oldVal, newVal){
        /*
        Called when an observed attribute has been added, removed, updated, or replaced. Also called for initial values when an element is created by the parser, or upgraded. Note: only attributes listed in the observedAttributes property will receive this callback.
        */
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['wpsstm-playable'];
    }
    
    get playable() {
        return this.hasAttribute('wpsstm-playable');
    }
    
    set playable(value) {
        const isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('wpsstm-playable', '');
        } else {
            this.removeAttribute('wpsstm-playable');
        }
    }
    
    ///
    ///
    
    debug(msg){
        var debug = {message:msg,tracklist:this};
        wpsstm_debug(debug);
    }

    render(){

        var tracklist = this;

        tracklist.post_id =     Number( $(tracklist).data('wpsstm-tracklist-id') );

        tracklist.init_tracklist_expiration();

        /*
        New subtracks
        */

        var queue_tracks_form = $(tracklist).find('#wpsstm-queue-tracks');
        var queue_tracks_submit = queue_tracks_form.find('#wpsstm-queue-tracks-submit');
        var queue_more_tracks = queue_tracks_form.find('#wpsstm-queue-more-tracks');

        //add new track row
        queue_more_tracks.on( "click", function(e) {
            e.preventDefault();
            var last_row = queue_tracks_form.find('.wpsstm-new-track').last();

            var new_row = last_row.clone();
            new_row.find('input').val('');
            new_row.removeClass('wpsstm-new-track-ready');
            new_row.insertAfter( last_row );
        });
        
        //remove new track row
        queue_tracks_form.on( "click",'.wpsstm-remove-new-track-row', function(e) {
            var row = $(this).parents('.wpsstm-new-track');
            row.remove();
        });
        
        
        //submit tracks
        queue_tracks_submit.click(function(e) {
            
            e.preventDefault();
            
            var isExpanded = queue_tracks_form.hasClass('expanded');
            
            if (!isExpanded){
                queue_tracks_form.addClass('expanded');
            }else{
                
                var rows = queue_tracks_form.find('.wpsstm-new-track');
                var doReload = false;
                var ajaxCalls = [];
                
                queue_tracks_form.addClass('wpsstm-freeze');

                rows.each(function( index ) {
                    var row = $(this);
                    var track = new WpsstmTrack();
                    track.track_artist = row.find('input[name="wpsstm_track_data[artist]"]').val();
                    track.track_title = row.find('input[name="wpsstm_track_data[title]"]').val();
                    track.track_album = row.find('input[name="wpsstm_track_data[album]"]').val();
                    
                    var ajax = tracklist.new_subtrack(track,row).done(function() { //at least one track added, we'll need to reload the tracklist
                        doReload = true;
                        row.remove();
                    });

                    ajaxCalls.push(ajax);
                    
                });

                //TOUFIX BROKEN
                //should be fired when all promises have returned a response, no matter if it succeeded or not.
                $.when.apply($, ajaxCalls).always(function(){
                    queue_tracks_form.removeClass('wpsstm-freeze');
                    if (doReload){
                        tracklist.reload_tracklist();
                    }
                })
            }

        });

        /*
        Refresh
        */
        if (wpsstmL10n.ajax_tracks){
            var refresh_bt = $(tracklist).find(".wpsstm-reload-bt");
            refresh_bt.click(function(e) {
                e.preventDefault();
                tracklist.debug("clicked 'refresh' bt");
                tracklist.reload_tracklist();
            });

            $(tracklist).on( "wpsstmTracklistLoop", function( event,player ) {
                tracklist.debug("tracklist loop");
                if (tracklist.isExpired){
                    tracklist.reload_tracklist(true);
                }
            });
        }

        /*
        Tracklist actions
        */

        //toggle favorite
        $(tracklist).find('.wpsstm-tracklist-action-favorite,.wpsstm-tracklist-action-unfavorite').click(function(e) {
            e.preventDefault();
            
            var do_love = $(this).hasClass('wpsstm-tracklist-action-favorite');

            tracklist.toggle_favorite_tracklist(do_love);
        });


        /*
        Subtracks
        */

        var tracks = $(tracklist).find('wpsstm-track');

        //sort subtracks
        var startSortIdx, endSortIdx;
        $(tracklist).find( '.wpsstm-tracks-list' ).sortable({
            axis: "y",
            handle: '.wpsstm-track-action-move',
            start: function(event, ui) { 
                startSortIdx = ui.item.index();
            },
            update: function(event, ui) {
                endSortIdx = ui.item.index();
                var track = tracks.get(startSortIdx);
                var old_position = Number($(track).attr('data-wpsstm-subtrack-position'));
                var new_position = ui.item.index() + 1;


                if (track){
                    //new position
                    track.position = ui.item.index();
                    tracklist.update_subtrack_position(track,new_position);
                }

            }
        });

        tracklist.debug("Tracklist ready");
        
        $(document).trigger("wpsstmTracklistReady",[tracklist]); //custom event
    }
    
    get_instances(){
        var tracklist = this;
        return $(document).find('wpsstm-tracklist[data-wpsstm-tracklist-id="'+tracklist.post_id+'"]');
    }

    reload_tracklist(autoplay){
        var tracklist = this;
        
        //stop player
        var pageNode = $(tracklist).find('wpsstm-track.track-active').get(0);
        
        if (pageNode){
            var queueNode = pageNode.queueNode;
            if (typeof autoplay === 'undefined'){
                autoplay = ( queueNode.status == 'playing' )
            }
            queueNode.status = '';
        }

        tracklist.debug("reload tracklist... autoplay ?" + autoplay);
        
        var ajax_data = {
            action:     'wpsstm_reload_tracklist',
            tracklist:      tracklist.to_ajax(),
        };
        
        var abord_reload = function(e) {
             if ( (e.key === "Escape") ) { // escape key maps to keycode `27`
                xhr.abort();
            }
        }

        var xhr = $.ajax({
            type:           "post",
            url:            wpsstmL10n.ajaxurl,
            data:           ajax_data,
            dataType:       'json',
            beforeSend:     function() {
                $(tracklist).addClass('tracklist-reloading');
                $(document).bind( "keyup.reloadtracklist", abord_reload ); //use namespace - https://acdcjunior.github.io/jquery-creating-specific-event-and-removing-it-only.html
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    /*
                    If the tracklist WAS playing, keep those classes (used for autoplay).
                    */
                    var newTracklist = $(data.html).get(0);

                    if (autoplay){
                        $(newTracklist).find('wpsstm-track:first-child').addClass('track-autoplay');
                    }

                    //swap content
                    tracklist.replaceWith( newTracklist );
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(tracklist).removeClass('tracklist-reloading');
                $(document).unbind( "keyup.reloadtracklist", abord_reload );
            }
        })
        
        return xhr;
    }
    
    toggle_favorite_tracklist(do_love){
        var tracklist = this;
        
        if (do_love){
            var link_el = $(tracklist).find('.wpsstm-tracklist-action-favorite');
        }else{
            var link_el = $(tracklist).find('.wpsstm-tracklist-action-unfavorite');
        }

        var ajax_data = {
            action:     'wpsstm_tracklist_toggle_favorite',
            tracklist:  tracklist.to_ajax(),   
            do_love:    do_love,
        };

        return $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
            beforeSend: function() {
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){

                if (data.success === false) {
                    console.log(data);
                    link_el.addClass('action-error');
                    if (data.notice){
                        wpsstm_js_notice(data.notice);
                    }
                }else{
                    if (do_love){
                        $(tracklist).addClass('favorited-tracklist');
                    }else{
                        $(tracklist).removeClass('favorited-tracklist');
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                link_el.removeClass('action-loading');
            }
        })
    }
    
    init_tracklist_expiration(){
        var tracklist = this;

        var now = Math.round( $.now() /1000);
        var remaining_sec = undefined;

        var meta_expiration = $(tracklist).find('meta[itemprop="wpsstmRefreshTimer"]');
        if (!meta_expiration.length) return;
            
        remaining_sec = parseInt( meta_expiration.attr('content') );

        if (remaining_sec > 0){
            tracklist.isExpired = false;
            var expirationTimer = setTimeout(function(){
                tracklist.isExpired = true;
                $(tracklist).addClass('tracklist-expired');
                tracklist.debug("tracklist has expired, stop expiration timer");

            }, remaining_sec * 1000 );

        }else{
            tracklist.isExpired = true;
            $(tracklist).addClass('tracklist-expired');
        }
        
        if (remaining_sec < 0){
            tracklist.debug("tracklist has expired "+Math.abs(remaining_sec)+" seconds ago");
        }else{
            tracklist.debug("tracklist will expire in "+remaining_sec+" seconds");
        }

    }

    refresh_tracks_positions(){
        var tracklist = this;
        var all_rows = $(tracklist).find( 'wpsstm-track' );
        jQuery.each( all_rows, function( key, value ) {
            var position = jQuery(this).find('.wpsstm-track-position [itemprop="position"]');
            position.text(key + 1);
        });
    }

    update_subtrack_position(track,new_pos){
        var tracklist = this;
        var track_instances = track.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-move');

        var ajax_data = {
            action:     'wpsstm_update_subtrack_position',
            new_pos:    new_pos,
            track:      track.to_ajax(),
        };

        $.ajax({
            type: "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_instances.addClass('track-loading');
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){

                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    tracklist.refresh_tracks_positions();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_instances.removeClass('track-loading');
                link_el.removeClass('action-loading');
            }
        })

    }

    //reduce object for communication between JS & PHP
    to_ajax(){

        var tracklist = this;
        var allowed = ['post_id'];
        var filtered = Object.keys(tracklist)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
            obj[key] = tracklist[key];
            return obj;
        }, {});
        return filtered;
    }
    
    new_subtrack(track,row){
        
        var tracklist = this;
        var success = $.Deferred();

        var ajax_data = {
            action:         'wpsstm_tracklist_new_subtrack',
            track:          track.to_ajax(),
            tracklist_id:   tracklist.post_id
        };

        var ajax = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',

            beforeSend: function() {
                row.removeClass('action-error').addClass('action-loading wpsstm-freeze');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                row.addClass('action-error');
                success.reject();
            },
            complete: function() {
                row.removeClass('action-loading wpsstm-freeze');
            }
        })
        
        return success.promise();
    }
    
    
}

window.customElements.define('wpsstm-tracklist', WpsstmTracklist);
