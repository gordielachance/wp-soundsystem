var bottom_wrapper_el;
var bottom_el;
var bottom_track_wraper_el;

var wpsstm_currentTrack;
var wpsstm_mediaElement;
var wpsstm_mediaElementPlayer;
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track
var wpsstm_player_shuffle_el; //shuffle button
//those are the globals for autoplay and tracks navigation
var wpsstm_page_player;

var $ = jQuery.noConflict();

$(document).ready(function(){
    
    bottom_wrapper_el =         $('#wpsstm-bottom-wrapper');
    bottom_el =                 $(bottom_wrapper_el).find('#wpsstm-bottom');
    bottom_track_wraper_el =    $(bottom_el).find('#wpsstm-bottom-track-wrapper');
    bottom_trackinfo_el =       $(bottom_track_wraper_el).find('#wpsstm-bottom-track-info');
    wpsstm_player_shuffle_el =  $('#wpsstm-player-shuffle');
    wpsstm_player_loop_el =     $('#wpsstm-player-loop');
    bt_prev_track =             $('#wpsstm-player-extra-previous-track');
    bt_next_track =             $('#wpsstm-player-extra-next-track');

    //init tracklists
    var all_tracklists = $( ".wpsstm-tracklist.wpsstm-playable-tracklist" );
    wpsstm_page_player.populate_tracklists(all_tracklists);

    /*
    Player : previous / next
    */

    bt_prev_track.click(function(e) {
        e.preventDefault();
        var tracklist_obj = wpsstm_page_player.get_tracklist_obj();
        tracklist_obj.play_previous_track();
    });

    bt_next_track.click(function(e) {
        e.preventDefault();
        var tracklist_obj = wpsstm_page_player.get_tracklist_obj();
        tracklist_obj.play_next_track();
    });
    
    //scroll to playlist track when clicking the player's track number
    $('#wpsstm-bottom').on( "click",'[itemprop="track"] .trackitem_order', function(e) {
        e.preventDefault();
        var player_track_el = $(this).parents('[itemprop="track"]');
        var track_idx = player_track_el.attr('data-wpsstm-track-idx');
        
        var tracklist_el = player_track_el.closest('[data-wpsstm-tracklist-idx]');
        var tracklist_idx = tracklist_el.attr('data-wpsstm-tracklist-idx');
        var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;

        var track_obj = wpsstm_page_player.get_tracklist_track_obj(tracklist_idx,track_idx);
        var track_el = track_obj.track_el;
        var newTracksCount = track_obj.track_idx + 1;

        //display request rows if needed
        tracklist_el.toggleTracklist({
            childrenMax:newTracksCount
        });

        //https://stackoverflow.com/a/6677069/782013
        $('html, body').animate({
            scrollTop: track_el.offset().top - ( $(window).height() / 3) //not at the very top
        }, 500);

    });
    

    //love/unlove track (either for page tracks or player track)
    $(document).on( "click",'[itemprop="track"] .wpsstm-track-action-wp-love-unlove a', function(e) {
        e.preventDefault();
        
        var link = $(this);
        var links_wrapper = link.closest('.wpsstm-track-action-love-unlove');
        var do_love = !links_wrapper.hasClass('wpsstm-is-loved');

        var tracklist_el = link.closest('[data-wpsstm-tracklist-idx]');
        var tracklist_idx = tracklist_el.attr('data-wpsstm-tracklist-idx');

        var track_el = link.closest('[itemprop="track"]');
        var track_idx = track_el.attr('data-wpsstm-track-idx');

        var track_obj = wpsstm_page_player.get_tracklist_track_obj(tracklist_idx,track_idx);
        track_obj.love_unlove(do_love);

    });

    //user is not logged for action
    $('.wpsstm-requires-auth').click(function(e) {
        if ( !wpsstm_get_current_user_id() ){
            e.preventDefault();
            $('#wpsstm-bottom-notice-wp-auth').addClass('active');
        }

    });

    /*
    Player : shuffle
    */

    if ( wpsstm_page_player.is_shuffle ){
        $(wpsstm_player_shuffle_el).addClass('active');
    }

    $(wpsstm_player_shuffle_el).find('a').click(function(e) {
        e.preventDefault();

        var is_active = !wpsstm_page_player.is_shuffle;
        wpsstm_page_player.is_shuffle = is_active;

        if (is_active){
            localStorage.setItem("wpsstm-player-shuffle", true);
            $(wpsstm_player_shuffle_el).addClass('active');
        }else{
            localStorage.removeItem("wpsstm-player-shuffle");
             $(wpsstm_player_shuffle_el).removeClass('active');
        }

    });

    /*
    Player : loop
    */

    if ( wpsstm_page_player.is_loop ){
        $(wpsstm_player_loop_el).addClass('active');
    }

    $(wpsstm_player_loop_el).find('a').click(function(e) {
        e.preventDefault();

        var is_active = !wpsstm_page_player.is_loop;
        wpsstm_page_player.is_loop = is_active;

        if (is_active){
            localStorage.setItem("wpsstm-player-loop", true);
            $(wpsstm_player_loop_el).addClass('active');
        }else{
            localStorage.setItem("wpsstm-player-loop", false);
             $(wpsstm_player_loop_el).removeClass('active');
        }

    });

});

//Confirmation popup is a media is playing and that we leave the page

$(window).bind('beforeunload', function(){
    if (wpsstm_mediaElement && !wpsstm_mediaElement.paused){
        return wpsstmPlayer.leave_page_text;
    }
});

class WpsstmTracklist {
    constructor(tracklist_el,tracklist_index) {

        self =                          this;
        self.tracklist_el =             undefined;
        self.current_track_idx =        undefined;
        self.tracklist_request =        undefined;
        self.refresh_timer =            undefined;
        self.expire_sec =               undefined;
        self.tracklist_idx =            tracklist_index;
        self.tracks =                   [];
        self.tracks_shuffle_order =     [];
        self.did_tracklist_request =    true;
        self.can_play =                 true;
        self.populate_tracklist(tracklist_el);

    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.tracklist_idx + ": ";
        wpsstm_debug(msg,prefix);
    }
    
    populate_tracklist(tracklist_el){
        
        self.tracklist_el = $(tracklist_el);

        self.debug("populate_tracklist()");

        self.tracklist_el.attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id = Number( self.tracklist_el.attr('data-wpsstm-tracklist-id') );
        var expire_sec_attr =  self.tracklist_el.attr('data-wpsstm-expire-sec');

        if (typeof expire_sec_attr !== typeof undefined && expire_sec_attr !== false) { // For some browsers, `attr` is undefined; for others, `attr` is false.  Check for both.
            
            self.expire_sec = Number(expire_sec_attr);
            
            if ( self.expire_sec <= 0 ){
                self.did_tracklist_request = false; 
            }
        }
        
        var tracks_html = self.tracklist_el.find('[itemprop="track"]');
        
        self.tracks = [];
        self.tracks_shuffle_order = [];
        
        if ( tracks_html.length > 0 ){
            $.each(tracks_html, function( index, track_html ) {
                var new_track = new WpsstmTrack(track_html,self.tracklist_idx,index);
                self.tracks.push(new_track);
                self.tracks_shuffle_order.push(index);
            });

            self.tracks_shuffle_order = wpsstm_shuffle(self.tracks_shuffle_order);

        }

        self.init_tracklist_dom();

    }
    
    init_tracklist_dom(){
        var self = this;
        
        /*
        Track : play buttons
        */

        self.tracklist_el.find( '[itemprop="track"] .wpsstm-play-track' ).click(function(e) {
            e.preventDefault();

            var track_el = $(this).closest('tr');
            var track_idx = $(track_el).attr('data-wpsstm-track-idx');

            if ( wpsstm_mediaElement && $(track_el).hasClass('active') ){
                if ( $(track_el).hasClass('playing') ){
                    wpsstm_mediaElement.pause();
                }else{
                    wpsstm_mediaElement.play();
                }
            }else{
                self.play_tracklist_track(track_idx);
            }

        });

        /*
        Tracklist actions
        */
        
        //refresh
        var refresh_link = self.tracklist_el.find("a.wpsstm-refresh-playlist");

        $(refresh_link).click(function(e) {
            e.preventDefault();
            //unset request status
            self.debug("clicked 'refresh' link");
            self.get_tracklist_request(true); //initialize but do not set track to play
            
        });
        
        //share
        self.tracklist_el.find('a.wpsstm-tracklist-action-share').click(function(e) {
          e.preventDefault();
          var text = $(this).attr('href');
          wpsstm_clipboard_box(text);
        });

        //toggle love/unlove
        self.tracklist_el.find('.wpsstm-playlist-action-love-unlove a').click(function(e) {
            e.preventDefault();

            var link = $(this);
            var link_wrapper = link.closest('.wpsstm-playlist-action-love-unlove');
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');
            var do_love = !link_wrapper.hasClass('wpsstm-is-loved');

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
                post_id:        tracklist_id,
                do_love:        do_love,
            };
            
            self.debug("toggle_love_tracklist:" + do_love);

            return $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json',
                beforeSend: function() {
                    link_wrapper.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        if (do_love){
                            link_wrapper.addClass('wpsstm-is-loved');
                        }else{
                            link_wrapper.removeClass('wpsstm-is-loved');
                        }
                    }
                },
                complete: function() {
                    link_wrapper.removeClass('loading');
                }
            })
        });
        
        self.tracklist_el.toggleTracklist();
        
    }

    get_tracklist_request(force = false){
        
        var self = this;
        var deferredTracklist = $.Deferred();
        
        if ( self.did_tracklist_request && !force ){
            
            deferredTracklist.resolve();
            
        }else{
            
            if (!self.tracklist_request){
            
                self.debug("get_tracklist_request");

                var ajax_data = {
                    'action':           'wpsstm_load_tracklist',
                    'post_id':          this.tracklist_id
                };

                self.tracklist_request = $.ajax({

                    type: "post",
                    url: wpsstmL10n.ajaxurl,
                    data:ajax_data,
                    dataType: 'json'
                });

                var refresh_notice = self.get_refresh_notice_el();
                var refresh_notice_table = $(refresh_notice).clone();
                refresh_notice_table.find('em').remove();
                //refresh_notice_table = $( refresh_notice_table.html() );

                $(bottom_wrapper_el).prepend(refresh_notice);
                //replace 'not found' text by refresh notice
                self.tracklist_el.find('tr.no-items td').append( refresh_notice_table );
                self.tracklist_el.addClass('loading');

            }else{ 
                //already requesting
            }

            self.tracklist_request.done(function(data) {
                if (data.success === false) {
                    deferredTracklist.reject();
                }else{
                    var new_tracklist_el = $(data.new_html);
                    self.tracklist_el.replaceWith(new_tracklist_el);
                    self.populate_tracklist( new_tracklist_el );
                    deferredTracklist.resolve();
                }

            });

            self.tracklist_request.fail(function(jqXHR, textStatus, errorThrown) {
                deferredTracklist.reject();
            });  
            
            self.tracklist_request.always(function() {

                self.did_tracklist_request = true; //so we can avoid running this function several times
                
                refresh_notice.remove();
                refresh_notice_table.remove();
                
                self.tracklist_el.removeClass('loading');

                //refresh timer
                if (self.expire_sec !== undefined){
                    self.init_refresh_timer();
                }

                self.tracklist_request = undefined;
            });
            
        }
        
        ////
        
        deferredTracklist.fail(function(jqXHR, textStatus, errorThrown) {
            self.can_play = false;
            self.tracklist_el.addClass('error');
            console.log("get_tracklist_request failed for tracklist #" + self.tracklist_idx);
        });

        return deferredTracklist.promise();

    }
    
    get_track_obj(track_idx){
        var self = this;
        
        if(typeof track_idx === 'undefined'){
            track_idx = self.current_track_idx;
        }

        track_idx = Number(track_idx);
        var track_obj = self.tracks[track_idx];
        if(typeof track_obj === 'undefined') return;
        return track_obj;
    }
    
    get_tracklist_instances(){
        var tracklist_el = $('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"]');
        return tracklist_el;
    }

    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
        var shuffle_order = self.tracks_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        
        self.debug("get_maybe_unshuffle_track_idx : " + idx + "-->" + new_idx);
        return new_idx;
    }

    abord_tracks_sources_request() {
        
        var self = this;
        
        $.each(self.tracks, function( index, track ) {
            if (track.sources_request){
                track.sources_request.abort();
            }
        });

    };

    play_previous_track(){
        var self = this;
        
        var current_track_idx = self.get_maybe_unshuffle_track_idx(self.current_track_idx);
        var queue_track_idx = current_track_idx; //get real track index
        var first_track_idx = 0;
        var new_track;

        //try to get previous track
        for (var i = 0; i < self.tracks.length; i++) {

            if (queue_track_idx == first_track_idx){
                self.debug("play_previous_track() : is first track");
                break;
            }
            
            queue_track_idx = Number(queue_track_idx) - 1;
            queue_track_idx = self.get_maybe_shuffle_track_idx(queue_track_idx);
            var check_track = self.get_track_obj(queue_track_idx);

            if (check_track.can_play){
                new_track = check_track;
                break;
            }
        }
        
        if (new_track){
            self.debug("play_previous_track() #" + queue_track_idx);
            self.play_tracklist_track(check_track.track_idx);
        }else {
            wpsstm_page_player.play_previous_tracklist();
        }
    }
    
    play_next_track(){
        var self = this;
        
        var current_track_idx = self.get_maybe_unshuffle_track_idx(self.current_track_idx);
        var queue_track_idx = current_track_idx;
        var last_track_idx = self.tracks.length -1;
        var new_track;

        //try to get next track
        for (var i = 0; i < self.tracks.length; i++) {

            if (queue_track_idx == last_track_idx){
                self.debug("play_next_track() : is tracklist last track");
                break;
            } 
            
            queue_track_idx = Number(queue_track_idx) + 1;

            queue_track_idx = self.get_maybe_shuffle_track_idx(queue_track_idx);
            var check_track = self.get_track_obj(queue_track_idx);

            if ( check_track.can_play){
                new_track = check_track;
                break;
            }
        }
        
        if (new_track){
            self.debug("play_next_track() #" + queue_track_idx);
            self.play_tracklist_track(check_track.track_idx);
        }else{
            wpsstm_page_player.play_next_tracklist();
        }
    }
    
    /*
    timer notice
    */
    
    init_refresh_timer(){
        var self = this;

        //expire countdown
        if (self.expire_sec === 0) return;
        if (self.expire_sec <= 0) return;
        
        var ms = self.expire_sec * 1000;

        self.debug("init_refresh_timer()");
        
        if (self.refresh_timer){ //stop current timer if any
            clearTimeout(self.refresh_timer);
            self.refresh_timer = undefined;
        }
        
        self.debug("could refresh in "+ self.expire_sec +" seconds");
        
        setTimeout(function(){
            
            self.expire_sec = 0;
            self.tracklist_el.attr('data-wpsstm-expire-sec',0); //for CSS
            self.did_tracklist_request = false;
            self.debug("refresh timer expired");
            
        }, ms );
        
    }


    get_refresh_notice_el(){

        var self = this;
        
        self.debug("get_refresh_notice_el");
        
        var notice_el = $('<p />');
        var tracklist_title = self.tracklist_el.find('[itemprop="name"]').first().text();
        
        notice_el.attr({
            id:     'wpsstm-bottom-refresh-notice-' + self.tracklist_idx,
            class:  'wpsstm-notice wpsstm-bottom-notice wpsstm-bottom-refresh-notice active'
        });
        
        var notice_icon_el = $('<i class="fa fa-refresh fa-fw fa-spin"></i>');
        var notice_message_el = $('<span />');
        var playlist_title = $('<em />');
        playlist_title.text("  " +tracklist_title);
        notice_message_el.html(wpsstmPlayer.refreshing_text);
        notice_message_el.append(playlist_title);
        
        notice_el.append(notice_icon_el).append(notice_message_el);

        return notice_el;
    }

    play_tracklist_track(track_idx,source_idx){

        var self = this;
        var deferredTracklist = $.Deferred();
        
        //set active playlist
        if ( wpsstm_page_player.current_tracklist_idx !== undefined ){
            if ( wpsstm_page_player.current_tracklist_idx !== self.tracklist_idx ){
                wpsstm_page_player.end_current_tracklist();
            }
        }
        wpsstm_page_player.current_tracklist_idx = self.tracklist_idx;

        //set active track
        if ( self.current_track_idx !== undefined ){
            if ( self.current_track_idx !== track_idx ){
                self.end_current_track();
            }
        }
        
        //no track idx defined, set first track and maybe refresh tracklist
        if ( track_idx === undefined ){
            track_idx = 0;
            deferredTracklist = self.get_tracklist_request();
        }else{
            deferredTracklist.resolve();
        }
        self.current_track_idx = track_idx;

        //tracklist ready
        deferredTracklist.done(function() {
            self.debug("play_tracklist_track #" +  self.current_track_idx + " source #" + source_idx);
            var play_track = self.get_track_obj(self.current_track_idx);

            if (play_track){
                play_track.play_or_skip(source_idx);
            }else{
                self.debug("Track #"+self.current_track_idx+" not found");
                return;
            }
        });

    }
    
    end_current_track(){
        var self = this;
        var current_track = self.get_track_obj();
        
        if (current_track){

            self.debug("end_current_track #" + current_track.track_idx);

            //mediaElement
            if (wpsstm_mediaElement){
                self.debug("there is an active media, stop it");
                wpsstm_mediaElement.pause();
                wpsstm_mediaElement.currentTime = 0;
                current_track.updateTrackClasses('ended');
            }
            
            if (current_track.sources_request){
                current_track.sources_request.abort();
            }
            
            current_track.current_track_idx = undefined;
            current_track.current_source_idx = undefined;
        }
    }
    
}

class WpsstmTrack {
    constructor(track_html,tracklist_idx,track_idx) {

        var self =                  this;
        self.track_el =             $(track_html);
        self.tracklist_idx =        tracklist_idx; //cast to number;
        self.track_idx =            track_idx;
        self.artist =               self.track_el.find('[itemprop="byArtist"]').text();
        self.title =                self.track_el.find('[itemprop="name"]').text();
        self.album =                self.track_el.find('[itemprop="inAlbum"]').text();
        self.post_id =              self.track_el.attr('data-wpsstm-track-id');
        self.sources_request =      null;
        self.did_sources_request =  false;
        self.can_play =             true; //false when no source have been populated or that none are playable
        self.sources =              [];
        self.current_source_idx =   undefined;
       
        //self.debug("new track");
        
        self.track_el.attr('data-wpsstm-track-idx',this.track_idx);
        
        //populate existing sources
        self.populate_html_sources();

    }
    
    get_tracklist_el(){
        var self = this;
        return self.track_el.closest('[data-wpsstm-tracklist-idx="'+self.tracklist_idx+'"]');
    }
    
    debug(msg){
        var prefix = "WpsstmTrack #" + this.track_idx + " in playlist #"+ this.tracklist_idx +": ";
        wpsstm_debug(msg,prefix);
    }
    
    love_unlove(do_love){

        var self = this;
        
        var track_instances = self.get_track_instances();
        
        var link_wrappers = track_instances.find('.wpsstm-track-action-love-unlove');

        var ajax_data = {
            action:         'wpsstm_love_unlove_track',
            do_love:        do_love,
            track:          self.build_request_obj()
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link_wrappers.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    if (do_love){
                        link_wrappers.addClass('wpsstm-is-loved');
                    }else{
                        link_wrappers.removeClass('wpsstm-is-loved');
                    }

                }
            },
            complete: function() {
                link_wrappers.removeClass('loading');
                $(document).trigger( "wpsstmTrackLove", [self,do_love] ); //register custom event - used by lastFM for the track.updateNowPlaying call
            }
        })
    }

    get_track_instances(ancestor){
        if (ancestor !== undefined){
            return $(ancestor).find('[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.track_idx+'"]');
        }else{
            return $('[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.track_idx+'"]');
        }
    }
    
    /*
    Update the track button after a media event.
    */

    updateTrackClasses(event){
        
        var self = this;
        //var player_track = self.get_track_instances('#wpsstm-bottom');
        var track_instances = self.get_track_instances();

        switch(event) {
            case 'loadeddata':
            break;
            case 'error':
                track_instances.addClass('error');
            break;
            case 'play':
                track_instances.addClass('playing');
                track_instances.addClass('has-played');
                track_instances.removeClass('error buffering ended');
            break;
            case 'pause':
                track_instances.removeClass('playing');
            break;
            case 'ended':
                track_instances.removeClass('playing');
                track_instances.removeClass('active');
                track_instances.removeClass('buffering');
            break;
        }

    }

    /*
    Initialize a track : either play it if it has sources; or get the sources then call this function again (with after_ajax = true)
    */

    play_or_skip(source_idx){

        var self = this;
        var tracklist_obj = wpsstm_page_player.get_tracklist_obj(self.tracklist_idx);

        //cannot play this track
        if (!self.can_play) {
            tracklist_obj.play_next_track();
            return;
        }
        
        wpsstm_currentTrack = self;
        
        var all_tracks = $('[itemprop="track"]');
        all_tracks.removeClass('active');
        
        var track_instances = self.get_track_instances();
        track_instances.addClass('active buffering');
        
        self.set_bottom_trackinfo();
        
        $(document).trigger( "wpsstmTrackInit",[self] ); //custom event
        
        var deferredObject = self.get_sources_auto();
        
        deferredObject.done(function() {
            
            //set a small timeout so track does not play if user fast skip tracks
            setTimeout(function(){
                
                if ( self != wpsstm_currentTrack ) return false; //track has been switched since we've requested it

                if ( self.sources.length > 0 ){
                    self.load_in_player(source_idx);
                }else{
                    tracklist_obj.play_next_track();
                }
                
            }, 1000);

        })
        
        deferredObject.fail(function() {
            
            if ( self != wpsstm_currentTrack ) return false; //track has been switched since we've requested it

            tracklist_obj.play_next_track();
        })

        deferredObject.always(function(data, textStatus, jqXHR) {
            
            if ( self != wpsstm_currentTrack ) return false; //track has been switched since we've requested it
            
            self.get_next_tracks_sources_auto();
        })

    }
    
    get_sources_auto(){

        var self = this;

        var track_instances = self.get_track_instances();
        
        var deferredObject = $.Deferred();

        if ( self.sources.length > 0 ){ //we already have sources
            deferredObject.resolve();
            
        } else if ( !wpsstmPlayer.autosource ) {
            deferredObject.resolve();
            
        } else if ( self.did_sources_request ) {
            deferredObject.resolve();
        } else{
            
            self.debug("get_sources_auto");
            
            var promise = self.get_track_sources_request();
            track_instances.addClass('buffering');
            
            promise.fail(function() {

                track_instances.addClass('error');
                self.can_play = false;

                console.log("sources request failed for track #" + self.track_idx);
                
                deferredObject.reject();

            })
            
            promise.done(function() {
                self.debug("get_sources_auto - success");
                deferredObject.resolve();
            })
            
            promise.always(function(data, textStatus, jqXHR) {
                self.did_sources_request = true;
                track_instances.removeClass('buffering');
            })

        }

        return deferredObject.promise();
    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    get_next_tracks_sources_auto() {

        var self = this;
        var tracklist = wpsstm_page_player.tracklists[self.tracklist_idx];

        self.debug("get_next_tracks_sources_auto");

        var max_items = wpsstm_track_source_requests_limit;
        var rtrack_in = self.track_idx + 1;
        var rtrack_out = self.track_idx + max_items + 1;

        var tracks_slice = $(tracklist.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            track_to_preload.get_sources_auto();
        });
    }
    
    set_bottom_audio_el(){
        
        var self = this;
        
        var media_wrapper = $('<audio />');
        media_wrapper.attr({
            id:     'wpsstm-player-audio'
        });

        media_wrapper.prop({
            //autoplay:     true,
            //muted:        true
        });

        $( self.sources ).each(function(i, source_attr) {
            //media
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });

            media_wrapper.append(source_el);

        });
        
        $('#wpsstm-player').html(media_wrapper);
    }
    
    set_bottom_trackinfo(){
        var self = this;
        //track infos
        var trackinfo = self.track_el.clone();
        $(bottom_trackinfo_el).attr('data-wpsstm-tracklist-idx',self.tracklist_idx); //set tracklist ID for the player
        $(bottom_el).show();//show in not done yet
        $(trackinfo).find('td.trackitem_play_bt').remove();
        $(bottom_trackinfo_el).html(trackinfo);
    }

    load_in_player(source_idx){
        
        var self = this;

        self.set_bottom_audio_el(); //build <audio/> el
        self.debug("load_in_player");
        
        var audio_el = $('#wpsstm-player-audio');

        $(audio_el).mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            // Do not forget to put a final slash (/)
            pluginPath: 'https://cdnjs.com/libraries/mediaelement/',
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, player) {

                wpsstm_mediaElementPlayer = player;
                wpsstm_mediaElement = mediaElement;

                //handle source
                self.set_track_source(source_idx);
                
                self.debug("wpsstmMediaReady");
                $(document).trigger( "wpsstmMediaReady",[wpsstm_mediaElement,self] ); //custom event

                wpsstm_mediaElement.addEventListener('error', function(error) {
                    var source_obj = self.get_track_source(self.current_source_idx);
                    console.log('player event - source error for source: '+self.current_source_idx);
                    //self.debug(error);
                    self.updateTrackClasses('loadeddata');
                    self.skip_bad_source(self.current_source_idx);

                });

                wpsstm_mediaElement.addEventListener('loadeddata', function() {
                    self.debug('player event - loadeddata');
                    self.updateTrackClasses('loadeddata');
                    wpsstm_mediaElement.play();
                });

                wpsstm_mediaElement.addEventListener('play', function() {
                    if (wpsstm_mediaElement.duration <= 0) return; //quick fix because it was fired twice.
                    self.duration = Math.floor(mediaElement.duration);
                    self.playback_start = Math.round( $.now() /1000); //seconds - used by lastFM
                    self.debug('player event - play');
                    self.debug(wpsstm_mediaElement.src);
                    self.updateTrackClasses('play');
                });

                wpsstm_mediaElement.addEventListener('pause', function() {
                    self.debug('player - pause');
                    self.updateTrackClasses('pause');
                });

                wpsstm_mediaElement.addEventListener('ended', function() {
                    self.debug('MediaElement.js event - ended');
                    self.updateTrackClasses('ended');
                    wpsstm_mediaElement = undefined;
                    
                    //Play next song if any
                    var tracklist_obj = wpsstm_page_player.get_tracklist_obj(self.tracklist_idx);
                    tracklist_obj.play_next_track();
                });

            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });

    }
    
    /*
    Convert the track to an object (for ajax requests, etc)
    */
    build_request_obj(){
        var self = this;
        var track_obj = {
            artist:     self.artist,
            title:      self.title,
            album:      self.album,
            post_id:    self.post_id,
            mbid:       self.mbid
        }
        return track_obj;
    }
    
    get_track_sources_request() {

        var self = this;
        
        var track_el    = self.get_track_instances();
        $(track_el).find('.trackitem_sources').html('');
        
        var deferredObject = $.Deferred();

        //self.debug("get_track_sources_request()");

        var ajax_data = {
            'action':           'wpsstm_player_get_track_sources_auto',
            'track':            self.build_request_obj()
        };
        
        self.sources_request = $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
        });

        self.sources_request.done(function(data) {

            if ( (data.success === true) && ( data.new_html ) ){
                $(track_el).find('.trackitem_sources').html(data.new_html); //append new sources
                self.populate_html_sources();
                deferredObject.resolve();
            }else{
                deferredObject.reject();
            }

        });
        
        return deferredObject.promise();

    }
    
    populate_html_sources(){
        var self =      this;
        var track_el =  self.track_el; //page track

        var new_sources_items = $(track_el).find('.trackitem_sources li');

        //self.debug("found "+new_sources_items.length +" sources");
        
        self.sources = [];
        $.each(new_sources_items, function( index, li_item ) {
            var new_source = new WpsstmTrackSource(li_item,self);
            self.sources.push(new_source);            
        });

        if (self.sources.length){ //we've got sources
            self.can_play = true;
            //self.debug("populate_html_sources(): " +self.sources.length);
        }

        $(track_el).attr('data-wpsstm-sources-count',self.sources.length);
        
    }
    
    get_track_source(source_idx){
        var self = this;

        source_idx = Number(source_idx);
        var source_obj = self.sources[source_idx];
        if(typeof source_obj === 'undefined') return;
        return source_obj;
    }
    
    highligh_source(idx){
        var self = this;
        
        self.debug("highligh_source(): #" + idx);
        
        var source_obj = self.get_track_source(idx);
        var track_instances = self.get_track_instances();
        var trackinfo_sources = track_instances.find('.wpsstm-player-sources-list li');
        $(trackinfo_sources).removeClass('wpsstm-active-source');

        var source_li = source_obj.get_source_li_el();
        $(source_li).addClass('wpsstm-active-source');
    }
    
    set_track_source(idx){
        var self = this;
        
        if (idx === undefined) idx = 0;

        var new_source_obj = self.get_track_source(idx);
        var new_source = { src: new_source_obj.src, 'type': new_source_obj.type };

        if (self.current_source_idx == idx){
            self.debug("source #"+idx+" is already set");
            return false;
        }

        self.debug("set_track_source() #" + idx);
        new_source_obj.get_source_li_el().addClass('wpsstm-active-source');

        //player
        wpsstm_mediaElement.pause();
        wpsstm_mediaElement.setSrc(new_source.src);
        wpsstm_mediaElement.load();
        
        self.current_source_idx = idx;
        self.highligh_source(idx);

    }
    
    skip_bad_source(source_idx){
        //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
        
        var self = this;
        var source_obj = self.get_track_source(source_idx);
        
        self.debug("skip_bad_source(): #" + source_idx + ": " +source_obj.src);

        source_obj.can_play_source = false;
        self.current_source_idx = undefined;
        
        var source_el = source_obj.get_source_li_el();
        source_el.removeClass('wpsstm-active-source').addClass('wpsstm-bad-source');
        
        //
        var new_source_idx;
        
        //make a reordered array of sources
        var sources_before = self.sources.slice(0,source_idx);
        var sources_after = self.sources.slice(source_idx+1); //do not including this one
        var sources_reordered = sources_after.concat(sources_before);

        $( sources_reordered ).each(function(i, source_attr) {
            if (!source_attr.can_play_source) return true; //continue;
            new_source_idx = source_attr.source_idx;
            return false;//break
        });
        
        if (new_source_idx !== undefined){
            
            self.set_track_source(new_source_idx);
            
        }else{
           
            if (!self.did_sources_request){
                self.debug("skip_bad_source() - No valid sources found but no sources requested yet - unset sources and try again");
                self.debug(self);
                
                self.sources = []; //unset sources so autosource will work

                //try again
                self.play_or_skip();
                return;
                
            }else{
                self.debug("skip_bad_source() - No valid sources found - go to next track if possible");
                var track_instances = self.get_track_instances();
                track_instances.addClass('error');
                self.can_play = false;

                //No more sources - Play next song if any
                var tracklist = wpsstm_page_player.get_tracklist_obj(this.tracklist_idx);
                tracklist.play_next_track();
            }
           
       }

    }

}

class WpsstmTrackSource {
    constructor(source_html,track) {

        var self = this;
        self.tracklist_idx = track.tracklist_idx;
        self.track_idx = track.track_idx;
        self.source_idx = track.sources.length;
        $(source_html).attr('data-wpsstm-source-idx',this.source_idx);
        
        self.src =    $(source_html).attr('data-wpsstm-source-src');
        self.type =    $(source_html).attr('data-wpsstm-source-type');
        self.can_play_source = true;
        
        //self.debug("new WpsstmTrackSource");

    }
    
    debug(msg){
        var prefix = "WpsstmTrackSource #" + this.source_idx + " in playlist #"+ this.tracklist_idx +"; track #"+ this.track_idx +": ";
        wpsstm_debug(msg,prefix);
    }

    get_source_li_el(ancestor){
        
        var self = this;
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,self.track_idx);
        var track_el = track_obj.get_track_instances(ancestor);
        return $(track_el).find('[data-wpsstm-source-idx="'+self.source_idx+'"]');
    }
    
    /*
    get_player_source_el(){
        return $(bottom_el).find('audio source').eq(this.source_idx).get(0);
    }
    */

    select_player_source(){
        var self = this;
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,self.track_idx);

        var track_sources_count = track_obj.sources.length;
        if ( track_sources_count <= 1 ) return;
        
        self.debug("select_player_source()");

        var player_source_el = self.get_source_li_el(bottom_el);
        var ul_el = player_source_el.closest('ul');

        var sources_list = player_source_el.closest('ul');
        var sources_list_wrapper = sources_list.closest('td.trackitem_sources');

        if ( !player_source_el.hasClass('wpsstm-active-source') ){ //source switch

            var lis_el = player_source_el.closest('ul').find('li');
            lis_el.removeClass('wpsstm-active-source');
            player_source_el.addClass('wpsstm-active-source');

            track_obj.set_track_source(self.source_idx);
        }

    }

}

class WpsstmPagePlayer {
    constructor(){
        var self = this;
        self.debug("new WpsstmPagePlayer()");
        self.current_tracklist_idx;
        self.tracklists                 = [];
        self.tracklists_shuffle_order   = [];
        self.is_shuffle                 = ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        self.is_loop                    = ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
    }

    debug(msg){
        var prefix = "WpsstmPagePlayer: ";
        wpsstm_debug(msg,prefix);
    }

    populate_tracklists(all_tracklists){
        
        var self = this;

        if ( $(all_tracklists).length <= 0 ) return;
        
        self.debug("populate_tracklists()");

        $(all_tracklists).each(function( i, tracklist_el ) {

            var tracklist = new WpsstmTracklist(tracklist_el,i);
            self.tracklists.push(tracklist);
            self.tracklists_shuffle_order.push(i);  

        });
        
        $(document).trigger( "wpsstmDomReady"); //custom event

        //shuffle
        self.tracklists_shuffle_order = wpsstm_shuffle(self.tracklists_shuffle_order);

        //autoplay first tracklist
        if ( wpsstmPlayer.autoplay ){
            self.play_or_skip_tracklist(0);
        }
    }
    
    play_or_skip_tracklist(tracklist_idx){

        var self = this;
        
        self.debug("play_or_skip_tracklist #" + tracklist_idx);
        var tracklist_obj = self.get_tracklist_obj(tracklist_idx);

        //cannot play this tracklist
        if (!tracklist_obj.can_play) {
            self.play_next_tracklist();
            return;
        }
        
        tracklist_obj.play_tracklist_track();

    }

    get_tracklist_obj(tracklist_idx){
        
        var self = this;

        if(typeof tracklist_idx === 'undefined'){
            tracklist_idx = self.current_tracklist_idx;
        }

        tracklist_idx = Number(tracklist_idx);
        var tracklist_obj = this.tracklists[tracklist_idx];
        if(typeof tracklist_obj === 'undefined') return false;
        return tracklist_obj;
    }
    
    get_tracklist_track_obj(tracklist_idx,track_idx){
        var tracklist_obj = this.get_tracklist_obj(tracklist_idx);
        if (!tracklist_obj) return false;
        var track_obj = tracklist_obj.get_track_obj(track_idx);
        if (!track_obj) return false;
        return track_obj;
    }

    get_maybe_shuffle_tracklist_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var new_idx = self.tracklists_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
        
    }
    
    get_maybe_unshuffle_tracklist_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var shuffle_order = self.tracklists_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        self.debug("get_maybe_unshuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }

    play_previous_tracklist(){
        var self = this;
        
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);

        var queue_tracklist_idx = current_tracklist_idx; //get real track index
        var first_tracklist_idx = 0;
        var new_tracklist;

        //try to get previous track
        for (var i = 0; i < self.tracklists.length; i++) {

            if (queue_tracklist_idx == first_tracklist_idx){
                self.debug("play_previous_tracklist() : is page first tracklist");
                break;
            }
            
            queue_tracklist_idx = Number(queue_tracklist_idx) - 1;
            queue_tracklist_idx = self.get_maybe_shuffle_tracklist_idx(queue_tracklist_idx);
            var check_tracklist = self.get_tracklist_obj(queue_tracklist_idx);

            if (check_tracklist.can_play){
                new_tracklist = check_tracklist;
                break;
            }
        }

        if (new_tracklist){
            self.debug("play_previous_tracklist() #" + queue_tracklist_idx);
            var last_track_idx = new_tracklist.tracks.length -1;
            new_tracklist.play_tracklist_track(last_track_idx);
        }
    }
    
    play_next_tracklist(){
        var self = this;
        
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);
        
        var queue_tracklist_idx = current_tracklist_idx;
        var last_tracklist_idx = self.tracklists.length -1;
        var new_tracklist;

        //try to get next playlist
        for (var i = 0; i < self.tracklists.length; i++) {

            if (queue_tracklist_idx == last_tracklist_idx){ //this is the last page tracklist
                
                self.debug("play_next_tracklist() : is page last tracklist");
                
                if ( !self.is_loop ){
                    self.debug("play_next_tracklist() : Loop is disabled; ignore play_next_tracklist()");
                    return false;
                }else{
                    queue_tracklist_idx = 0;
                } 

            }else{
                queue_tracklist_idx = Number(queue_tracklist_idx) + 1;
            }

            queue_tracklist_idx = self.get_maybe_shuffle_tracklist_idx(queue_tracklist_idx);
            var check_tracklist = self.get_tracklist_obj(queue_tracklist_idx);

            if ( check_tracklist.can_play){
                new_tracklist = check_tracklist;
                break;
            }
        }
        
        if (check_tracklist){
            self.debug("play_next_tracklist() #" + queue_tracklist_idx);
            check_tracklist.play_tracklist_track();
        }
    }
    
    end_current_tracklist(){
        var self = this;
        var current_tracklist = self.get_tracklist_obj();
        if (current_tracklist !== false){
            self.debug("end_current_tracklist() #" + current_tracklist.tracklist_idx);
            current_tracklist.abord_tracks_sources_request(); //abord current requests
            current_tracklist.end_current_track();
            self.current_tracklist_idx = undefined;
        }
    }
    
}

function wpsstm_debug(msg,prefix){
    if (!wpsstmL10n.debug) return;
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
        console.log(prefix + msg);
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

wpsstm_page_player = new WpsstmPagePlayer();