var bottom_block_el;
var bottom_player_el;
var wpsstm_player;
var wpsstm_current_media;
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track
var wpsstm_player_shuffle_el; //shuffle button
//those are the globals for autoplay and tracks navigation
var wpsstm_page_player;

var $ = jQuery.noConflict();

$(document).ready(function(){
    
    bottom_block_el =           $('#wpsstm-bottom');
    bottom_player_el =          $(bottom_block_el).find('#wpsstm-bottom-player');
    wpsstm_player_shuffle_el =  $('#wpsstm-player-shuffle');
    wpsstm_player_loop_el =     $('#wpsstm-player-loop');
    bt_prev_track =             $('#wpsstm-player-extra-previous-track');
    bt_next_track =             $('#wpsstm-player-extra-next-track');

    //init tracklists
    var all_tracklists = $( ".wpsstm-tracklist" );
    wpsstm_page_player.populate_tracklists(all_tracklists);

    /*
    page buttons
    */

    $( ".wpsstm-play-track" ).live( "click", function(e) {
        e.preventDefault();

        var tracklist_el = $(this).closest('.wpsstm-tracklist');
        var tracklist_idx = $(tracklist_el).attr('data-wpsstm-tracklist-idx');

        var track_el = $(this).closest('tr');
        var track_idx = $(track_el).attr('data-wpsstm-track-idx');

        if ( wpsstm_current_media && $(track_el).hasClass('active') ){
            if ( $(track_el).hasClass('playing') ){
                wpsstm_current_media.pause();
            }else{
                wpsstm_current_media.play();
            }
        }else{
            var tracklist_obj = wpsstm_page_player.get_tracklist_obj(tracklist_idx);
            tracklist_obj.init_tracklist_track(track_idx);
        }

    });

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

    //love/unlove track (either for page tracks or player track)
    $('[itemprop="track"] .wpsstm-wp-love-unlove-track-links a').live( "click", function(e) {
        e.preventDefault();

        var link = $(this);
        var link_wrapper = link.closest('.wpsstm-love-unlove-track-links');
        var do_love = !link_wrapper.hasClass('wpsstm-is-loved');

        var tracklist_el = link.closest('[data-wpsstm-tracklist-idx]');
        var tracklist_idx = tracklist_el.attr('data-wpsstm-tracklist-idx');

        var track_el = link.closest('[itemprop="track"]');
        var track_idx = track_el.attr('data-wpsstm-track-idx');

        var track_obj = wpsstm_page_player.get_tracklist_track_obj(tracklist_idx,track_idx);
        track_obj.love_unlove(do_love);

    });

    //bottom player : click on source item
    $( ".wpsstm-player-sources-list li .wpsstm-source-title" ).live( "click", function(e) {

        e.preventDefault();

        var track_el = $(this).closest('[itemprop="track"]');
        var track_idx = Number( track_el.attr('data-wpsstm-track-idx') );

        var tracklist_obj = wpsstm_page_player.get_tracklist_obj();
        var track_obj = tracklist_obj.get_track_obj(track_idx);

        var source_el = $(this).closest('li');
        var source_idx = Number( source_el.attr('data-wpsstm-source-idx') );
        var source = tracklist_obj.get_track_obj(track_idx).get_track_source(source_idx);
        source.select_source_list();

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
    if (wpsstm_current_media && !wpsstm_current_media.paused){
        return wpsstmPlayer.leave_page_text;
    }
});

class WpsstmTracklist {
    constructor(tracklist_el,tracklist_index) {

        var self = this;
        self.current_track_idx;
        self.tracklist_request;
        self.refresh_timer;
        self.tracklist_idx =            tracklist_index;
        self.tracks =                   [];
        self.tracks_shuffle_order =     [];
        self.did_tracklist_request =    true;
        self.expire_sec =               null;
        self.can_play =                 true;

        self.populate_tracklist(tracklist_el);

    }
    
    populate_tracklist(tracklist_el){
        
        var self = this;

        console.log("WpsstmTracklist:populate_tracklist() #" + self.tracklist_idx);

        $(tracklist_el).attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id = Number( $(tracklist_el).attr('data-wpsstm-tracklist-id') );
        var expire_sec_attr =  $(tracklist_el).attr('data-wpsstm-expire-sec');

        if (typeof expire_sec_attr !== typeof undefined && expire_sec_attr !== false) { // For some browsers, `attr` is undefined; for others, `attr` is false.  Check for both.
            
            self.expire_sec = Number(expire_sec_attr);
            
            if ( self.expire_sec <= 0 ){
                self.did_tracklist_request = false; 
            }
        }
        
        var tracks_html = $(tracklist_el).find('[itemprop="track"]');
        
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

        //tracklist has expired
        if ( !self.did_tracklist_request ){  //UTC timestamp
            self.get_tracklist_request();
            return;
        }
        
        self.init_tracklist_dom();

    }
    
    init_tracklist_dom(){
        var self = this;
        var tracklist_el = self.get_tracklist_el();

        /*
        Tracklist actions
        */
        
        //refresh
        var refresh_link = $(tracklist_el).find("a.wpsstm-refresh-playlist");

        $(refresh_link).click(function(e) {
            e.preventDefault();
            //unset request status
            console.log("WpsstmTracklist - clicked 'refresh' link");
            self.did_tracklist_request = false; 
            wpsstm_page_player.end_current_tracklist();
            self.init_tracklist_track(); //initialize but do not set track to play
            
        });
        
        //share
        $(tracklist_el).find('a.wpsstm-tracklist-action-share').click(function(e) {
          e.preventDefault();
          var text = $(this).attr('href');
          wpsstm_clipboard_box(text);
        });

        //toggle love/unlove
        $(tracklist_el).find('.wpsstm-love-unlove-playlist-links a').click(function(e) {
            e.preventDefault();

            var link = $(this);
            var link_wrapper = link.closest('.wpsstm-love-unlove-playlist-links');
            var tracklist_wrapper = link.closest('.wpsstm-tracklist-table');
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');
            var do_love = !link_wrapper.hasClass('wpsstm-is-loved');

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
                post_id:        tracklist_id,
                do_love:        do_love,
            };

            console.log("toggle_love_tracklist:" + do_love);

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
    }

    get_tracklist_request(){
        
        var self = this;
        var deferredObject = $.Deferred();

        if (!self.tracklist_request){
            
            console.log("WpsstmTracklist:get_tracklist_request() #" + self.tracklist_idx);
            
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
            
            var tracklist_el = self.get_tracklist_el();
            var refresh_notice = null;

            var refresh_notice = self.get_refresh_notice();
            var refresh_notice_table = $(refresh_notice).clone();
            refresh_notice_table.find('em').remove();
            //refresh_notice_table = $( refresh_notice_table.html() );

            $(bottom_block_el).prepend(refresh_notice);
            //replace 'not found' text by refresh notice
            $(tracklist_el).find('tr.no-items td').append( refresh_notice_table );
            $(tracklist_el).addClass('loading');
            
        }else{ 
            //already requesting
        }

        self.tracklist_request.done(function(data) {
            if (data.success === false) {
                deferredObject.reject();
            }else{
                var new_tracklist_el = $(data.new_html);
                $(tracklist_el).replaceWith(new_tracklist_el);
                self.populate_tracklist( new_tracklist_el );
                deferredObject.resolve();
            }

        });
        
         self.tracklist_request.fail(function(jqXHR, textStatus, errorThrown) {
             deferredObject.reject();
         });
        
        self.tracklist_request.always(function() {
            self.tracklist_request = null;
            self.did_tracklist_request = true; //so we can avoid running this function several times
            $('#wpsstm-bottom-refresh-notice-' + self.tracklist_idx).remove();
            $(tracklist_el).removeClass('loading');
            
            //refresh timer
            if (self.expire_sec !== null){
                self.init_refresh_timer();
            }
            
            
        });
        
        return deferredObject.promise();

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
    
    get_tracklist_el(){
        var tracklist_el = $('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"]');
        return tracklist_el;
    }

    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        console.log("WpsstmTracklist:get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
        var shuffle_order = self.tracks_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        console.log("WpsstmTracklist:get_maybe_unshuffle_track_idx() : " + idx + "-->" + new_idx);
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
            
            console.log(i + " VS " + first_track_idx);

            if (queue_track_idx == first_track_idx){
                console.log("WpsstmTracklist:play_previous_track() : is tracklist first track");
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
            console.log("WpsstmTracklist:play_previous_track() #" + queue_track_idx + " in playlist#" + self.tracklist_idx);
            self.init_tracklist_track(check_track.track_idx);
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
                console.log("WpsstmTracklist:play_next_track() : is tracklist last track");
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
            console.log("WpsstmTracklist:play_next_track() #" + queue_track_idx + " in playlist#" + self.tracklist_idx);
            self.init_tracklist_track(check_track.track_idx);
        }else{
            wpsstm_page_player.play_next_tracklist();
        }
    }

    end_current_track(){
        var self = this;
        var current_track = self.get_track_obj();
        if (current_track){
            current_track.end_track();
            self.current_track_idx = null;
        }
    }
    
    /*
    timer notice
    */
    
    init_refresh_timer(){
        var self = this;

        //expire countdown
        if (self.expire_sec === null) return;
        if (self.expire_sec <= 0) return;

        console.log("WpsstmTracklist:init_refresh_timer for playlist #" + self.tracklist_idx);
        
        console.log("set timer for " + self.expire_sec);
        if (self.refresh_timer){ //stop current timer if any
            clearInterval(self.refresh_timer);
            self.refresh_timer = null;
        }
        
        console.log("this tracklist could refresh in "+ self.expire_sec +" seconds");

        self.refresh_timer = setInterval ( function(){
                return self.update_refresh_timer();
        }, 1000 );
    }
    
    update_refresh_timer(){
        var self = this;
        self.expire_sec = self.expire_sec - 1;
        
        //console.log("WpsstmTracklist:update_refresh_timer() - " + self.expire_sec);

        if (self.expire_sec <= 0){
            clearInterval(self.refresh_timer);
            self.expire_sec = 0;
            var tracklist_el = self.get_tracklist_el();
            $(tracklist_el).attr('data-wpsstm-expire-sec',0); //for CSS
            self.did_tracklist_request = false;
        }

    }

    get_refresh_notice(){
        
        console.log("get_refresh_notice");
        
        var self = this;
        var notice_el = $('<p />');
        var tracklist = this.get_tracklist_el();
        var tracklist_title = $(tracklist).find('[itemprop="name"]').first().text();
        
        notice_el.attr({
            id:     'wpsstm-bottom-refresh-notice-' + self.tracklist_idx,
            class:  'wpsstm-bottom-notice wpsstm-bottom-refresh-notice active'
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
    
    init_tracklist(){
        var self = this;
        var deferredObject = $.Deferred();

        if ( (wpsstm_page_player.current_tracklist_idx !== null) && ( self.tracklist_idx == wpsstm_page_player.current_tracklist_idx ) ){ //no tracklist change
            deferredObject.resolve();
        }else{
            if (wpsstm_page_player.current_tracklist_idx !== null){
                wpsstm_page_player.end_current_tracklist();
            }
            console.log("WpsstmTracklist:init_tracklist() #" + self.tracklist_idx);
            wpsstm_page_player.current_tracklist_idx = self.tracklist_idx;
            
            //maybe repopulate tracklist
            if (self.did_tracklist_request){
                deferredObject.resolve();
            }else{
                self.end_current_track();
                deferredObject = self.get_tracklist_request();
            }
            
        }

        return deferredObject.promise();
        
    }
    
    init_tracklist_track(track_idx){

        var self = this;

        var tracklist_el = self.get_tracklist_el();
        var deferredObject = self.init_tracklist();
        
        deferredObject.fail(function(jqXHR, textStatus, errorThrown) {
            self.can_play = false;
            $(tracklist_el).addClass('error');
            console.log("WpsstmTracklist:get_tracklist_el() failed");
            console.log({jqXHR:jqXHR,textStatus:textStatus,errorThrown:errorThrown});
        });

        deferredObject.done(function() {

            if (track_idx === undefined){
                track_idx = self.get_maybe_shuffle_track_idx(0);
            }
            
            //set active track
            if ( self.current_track_idx !== null ){
                if ( self.current_track_idx !== track_idx ){
                    self.end_current_track();
                }
            }
            
            self.current_track_idx = track_idx;

            console.log("WpsstmTracklist:init_tracklist_track() #" +  self.current_track_idx);
            var play_track = self.get_track_obj(self.current_track_idx);
            
            if (play_track){
                play_track.play_or_skip();
            }else{
                console.log("Track #"+self.current_track_idx+" not found");
                return;
            }

        })

        deferredObject.always(function(data, textStatus, jqXHR) {
            //item.statusText = null;
            //self.sources_request.$apply();
            
        })

        //set active track
        if (track_idx === undefined){
            track_idx = self.get_maybe_shuffle_track_idx(0);
        }
        
        var real_track_idx = self.get_maybe_unshuffle_track_idx(track_idx);
        
        

    }
}

class WpsstmTrack {
    constructor(track_html,tracklist_idx,track_idx) {

        var self =                  this;
        self.tracklist_idx =        tracklist_idx; //cast to number;
        self.track_idx =            track_idx;
        self.artist =               $(track_html).find('[itemprop="byArtist"]').text();
        self.title =                $(track_html).find('[itemprop="name"]').text();
        self.album =                $(track_html).find('[itemprop="inAlbum"]').text();
        self.post_id =              $(track_html).attr('data-wpsstm-track-id');
        self.sources_request =      null;
        self.did_sources_request =  false;
        self.can_play =             true; //false when no source have been populated or that none are playable
        self.sources =              [];
       
        //console.log("new WpsstmTrack #" + this.track_idx + " in tracklist #" + this.tracklist_idx);
        
        $(track_html).attr('data-wpsstm-track-idx',this.track_idx);
        
        //populate existing sources
        self.populate_html_sources();

    }
    
    love_unlove(do_love){

        var self = this;
        var track_instances = $('[data-wpsstm-track-id="'+self.post_id+'"]');
        
        var track_el = self.get_track_el();
        var track_id = self.post_id;
        
        var link_wrappers = track_instances.find('.wpsstm-love-unlove-track-links');

        var track = {
            artist:     self.artist,
            title:      self.title,
            album:      self.album,
            post_id:    self.post_id
        }

        var ajax_data = {
            action:         'wpsstm_love_unlove_track',
            do_love:        do_love,
            track:          track
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
                        $.each(track_instances, function( index, track_instance ) {
                            link_wrappers.addClass('wpsstm-is-loved');
                        });
                    }else{
                        $.each(track_instances, function( index, track_instance ) {
                            link_wrappers.removeClass('wpsstm-is-loved');
                        });
                    }

                }
            },
            complete: function() {
                link_wrappers.removeClass('loading');
                $( document ).trigger( "wpsstmTrackLove", [self,do_love] ); //register custom event - used by lastFM for the track.updateNowPlaying call
            }
        })
    }

    get_track_el(){
        var track_el = $('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.track_idx+'"]');
        return track_el;
    }
    
    /*
    Update the track button after a media event.
    */

    update_button(event){

        var track_el = this.get_track_el();

        switch(event) {
            case 'loadeddata':
            break;
            case 'error':
                track_el.addClass('error');
            break;
            case 'play':
                track_el.addClass('playing');
                track_el.addClass('has-played');
                track_el.removeClass('error buffering ended');
            break;
            case 'pause':
                track_el.removeClass('playing');
            break;
            case 'ended':
                track_el.removeClass('playing');
                track_el.removeClass('active');
                track_el.removeClass('buffering');
            break;
        }

    }

    /*
    Initialize a track : either play it if it has sources; or get the sources then call this function again (with after_ajax = true)
    */

    play_or_skip(){

        var self = this;
        var tracklist_obj = wpsstm_page_player.get_tracklist_obj(self.tracklist_idx);

        //cannot play this track
        if (!self.can_play) {
            tracklist_obj.play_next_track();
            return;
        }
        
        var track_el = self.get_track_el();
        $(track_el).addClass('buffering');

        var deferredObject = self.get_sources_auto();
        
        deferredObject.done(function() {
            
            if ( self.sources.length > 0 ){
                self.send_to_player();
            }else{
                tracklist_obj.play_next_track();
            }
            
        })
        
        deferredObject.fail(function() {
            tracklist_obj.play_next_track();
        })

        deferredObject.always(function(data, textStatus, jqXHR) {
            self.get_next_tracks_sources_auto();
        })

    }
    
    get_sources_auto(){

        var self = this;

        var track_el = self.get_track_el();
        
        var deferredObject = $.Deferred();
        
        if ( self.sources.length > 0 ){ //we already have sources
            deferredObject.resolve();
            
        } else if ( !wpsstmPlayer.autosource ) {
            deferredObject.resolve();
            
        } else if ( self.did_sources_request ) {
            deferredObject.resolve();
        } else{
            
            console.log("WpsstmTrack:get_sources_auto #" + this.track_idx);
            
            var promise = self.get_track_sources_request();
            $(track_el).addClass('buffering');
            
            promise.fail(function() {

                $(track_el).addClass('error');
                self.can_play = false;

                console.log("sources request failed for track #" + self.track_idx);
                
                deferredObject.reject();

            })
            
            promise.done(function() {
                //console.log("WpsstmTrack:get_sources_auto success #" + self.track_idx);
                deferredObject.resolve();
            })
            
            promise.always(function(data, textStatus, jqXHR) {
                self.did_sources_request = true;
                $(track_el).removeClass('buffering');
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

        console.log("WpsstmTrack::get_next_tracks_sources_auto()");

        var max_items = wpsstm_track_source_requests_limit;
        var rtrack_in = self.track_idx + 1;
        var rtrack_out = self.track_idx + max_items + 1;
        
        //TO FIX
        //get X tracks that have .did_sources_request = false

        var tracks_slice = $(tracklist.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            track_to_preload.get_sources_auto();
        });
    }

    send_to_player(){
        var self = this;
        var tracklist = wpsstm_page_player.tracklists[self.tracklist_idx];
        console.log("send_to_player()  tracklist#" + tracklist.tracklist_idx + ", track#" + self.track_idx);

        var track_el    = self.get_track_el();
        
        $(track_el).addClass('active');

        //track infos
        var trackinfo = $(track_el).clone();
        trackinfo.attr('data-wpsstm-tracklist-idx',self.tracklist_idx); //set tracklist ID for the player
        trackinfo.show();
        trackinfo.find('td.trackitem_play_bt').remove();
        $('#wpsstm-player-trackinfo').html(trackinfo);

        //player sources

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

        new MediaElementPlayer('wpsstm-player-audio', {
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug: true,
                autoStartLoad: false
            },
            // Do not forget to put a final slash (/)
            pluginPath: 'https://cdnjs.com/libraries/mediaelement/',
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(media, node, player) {
                console.log("MediaElementPlayer ready");
                
                //display bottom block if not done yet
                bottom_player_el.show();
 
                wpsstm_player = player;
                wpsstm_current_media = media;

                $(wpsstm_current_media).on('error', function(error) {
                    var current_source = $(wpsstm_current_media).find('audio').attr('src');
                    console.log('player event - source error: '+current_source);
                    self.update_button('loadeddata');
                    self.skip_bad_source(wpsstm_current_media);

                });

                $(wpsstm_current_media).on('loadeddata', function() {
                    console.log('player event - loadeddata');
                    self.update_button('loadeddata');
                    $( document ).trigger( "wpsstmPlayerMediaEvent", ['loadeddata',media, node, player,self] ); //register custom event - used by lastFM for the track.updateNowPlaying call

                    wpsstm_player.play();

                });

                $(wpsstm_current_media).on('play', function() {
                    if (media.duration <= 0) return; //quick fix because it was fired twice.
                    self.duration = Math.floor(media.duration);
                    self.playback_start = Math.round( $.now() /1000); //seconds - used by lastFM
                    console.log('player event - play');
                    self.update_button('play');
                });

                $(wpsstm_current_media).on('pause', function() {
                    console.log('player - pause');
                    self.update_button('pause');
                });

                $(wpsstm_current_media).on('ended', function() {
                    console.log('MediaElement.js event - ended');
                    self.update_button('ended');
                    wpsstm_current_media = null;

                    $( document ).trigger( "wpsstmPlayerMediaEvent", ['ended',media, node, player,self] ); //register custom event - used by lastFM for the track.scrobble call

                    //Play next song if any
                    wpsstm_page_player.tracklists[self.tracklist_idx].play_next_track();
                });

            },error(media) {
                // Your action when media had an error loading
                //TO FIX is this required ?
                console.log("player error");
            }
        });

    }
    
    get_track_sources_request() {

        var self = this;
        var track_el    = self.get_track_el();
        if (!track_el) return;
        var deferredObject = $.Deferred();

        var track = {
            artist: self.artist,
            title:  self.title,
            album:  self.album
        }
        
        //console.log("WpsstmTrack:get_track_sources_request(): #" + this.track_idx);

        var ajax_data = {
            'action':           'wpsstm_player_get_track_sources_auto',
            'track':            track
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
        var track_el =  self.get_track_el();

        var new_sources_items = $(track_el).find('.trackitem_sources li');

        //console.log("found "+new_sources_items.length +" sources for track#" + this.track_idx);
        
        var sources = [];
        $.each(new_sources_items, function( index, li_item ) {
            var new_source = new WpsstmTrackSource(li_item,self);
            self.sources.push(new_source);            
        });
        
        if (self.sources.length){ //we've got sources
            self.can_play = true;
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
    
    switch_track_source(idx){
        var self = this;

        var new_player_source = $(wpsstm_current_media).find('audio source').eq(idx);
        var new_player_source_url = new_player_source.attr('src');
        var current_player_source_url = $(wpsstm_current_media).find('audio').attr('src');

        if (current_player_source_url == new_player_source_url) return false;
        
        var new_source_obj = self.sources[idx];
        console.log("WpsstmTrack:switch_track_source():" + new_player_source_url);

        //player
        wpsstm_current_media.pause();
        wpsstm_current_media.setSrc(new_player_source);
        wpsstm_current_media.load();
        wpsstm_current_media.play();

        //trackinfo
        var trackinfo_sources = $(bottom_player_el).find('#wpsstm-player-sources-wrapper li');
        $(trackinfo_sources).removeClass('wpsstm-active-source');

        var new_source_el = new_source_obj.get_player_source_el();
        $(new_source_el).addClass('wpsstm-active-source');
    }
    
    skip_bad_source(media){
        
        console.log("WpsstmTrack:skip_bad_source()");
        
       //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
        
        var self = this;
        var current_source_url = $(media).find('audio').attr('src');
        var source_els = $(media).find('source');
        var source_els_clone = $(media).find('source').clone();
        var new_source_idx = -1;

        source_els_clone.each(function(i, val) {

            var source = $(this);
            var source_url = source.attr('src');
            
            if (!source_url) return true; //continue
            if (source.hasClass('wpsstm-bad-source')) return true; //continue;

            if ( source_url == current_source_url ) {
                
                $(source_els_clone).eq(i).remove(); //remove from loop
                $(source_els).eq(i).addClass('wpsstm-bad-source'); //add class to source
                $('#wpsstm-player-sources-wrapper li').eq(i).addClass('wpsstm-bad-source');//add class to trackinfo source
                
                console.log("skip; is current source: "+source_url);
                return true; //continue
            }
            
            new_source_idx = i;
            return false;  //break

        });
        
        if (new_source_idx > -1){
            self.skip_bad_source(new_source_idx);
        }else{
            if (!self.did_sources_request){
                console.log("WpsstmTrack:skip_bad_source() - No valid sources found but no sources requested yet - unset sources and try again");
                console.log(self);
                //remove sources
                self.sources = [];
                
                //try again
                self.play_or_skip();
                return;
                
            }else{
                console.log("WpsstmTrack:skip_bad_source() - No valid sources found - go to next track if possible");
                var track_el = self.get_track_el();
                $(track_el).addClass('error');
                self.can_play = false;

                //No more sources - Play next song if any
                var tracklist = wpsstm_page_player.get_tracklist_obj(this.tracklist_idx);
                tracklist.play_next_track();
            }

        }

    }
    
    end_track(){
        var self =      this;
        var track_el =  self.get_track_el();

        console.log("WpsstmTrack:end_track() #" + self.track_idx + " in playlist " + self.tracklist_idx);

        //mediaElement
        if (wpsstm_current_media){
            console.log("there is an active media, stop it");
            wpsstm_current_media.pause();
            wpsstm_current_media.currentTime = 0;
            self.update_button('ended');

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
        
        self.src =    $(source_html).find('a').attr('href');
        self.type =    $(source_html).attr('data-wpsstm-source-type');
        
        //console.log("new WpsstmTrackSource #" + this.source_idx + " in track #" + track.track_idx + " from tracklist track #" + track.tracklist_idx);

    }

    get_player_source_el(){
        return $(bottom_player_el).find('[data-wpsstm-source-idx="'+this.source_idx+'"]');
    }
    
    select_source_list(){
        var self = this;
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,self.track_idx);

        var track_sources_count = track_obj.sources.length;
        if ( track_sources_count <= 1 ) return;
        
        console.log("clicked source #" + this.source_idx + " of track #" + this.track_idx + " in playlist #" + self.tracklist_idx);
        console.log(self);
        
        var player_source_el = self.get_player_source_el();
        var ul_el = player_source_el.closest('ul');

        var sources_list = player_source_el.closest('ul');
        var sources_list_wrapper = sources_list.closest('td.trackitem_sources');

        if ( !player_source_el.hasClass('wpsstm-active-source') ){ //source switch

            var lis_el = player_source_el.closest('ul').find('li');
            lis_el.removeClass('wpsstm-active-source');
            player_source_el.addClass('wpsstm-active-source');

            track_obj.switch_track_source(self.source_idx);
        }

    }

}

class WpsstmPagePlayer {
    constructor(){
        var self = this;
        console.log("new WpsstmPagePlayer()");
        self.current_tracklist_idx;
        self.tracklists                 = [];
        self.tracklists_shuffle_order   = [];
        self.is_shuffle                 = ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        self.is_loop                    = ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
    }

    populate_tracklists(all_tracklists){
        
        var self = this;

        if ( $(all_tracklists).length <= 0 ) return;
        
        console.log("WpsstmPagePlayer:populate_tracklists()");

        $(all_tracklists).each(function( i, tracklist_el ) {

            var tracklist = new WpsstmTracklist(tracklist_el,i);
            self.tracklists.push(tracklist);
            self.tracklists_shuffle_order.push(i);  

        });
        
        $( document ).trigger( "wpsstmDomReady"); //custom event

        //shuffle
        self.tracklists_shuffle_order = wpsstm_shuffle(self.tracklists_shuffle_order);

        //autoplay first tracklist
        if ( wpsstmPlayer.autoplay ){
            self.play_or_skip_tracklist(0);
        }
    }
    
    play_or_skip_tracklist(tracklist_idx){

        var self = this;
        
        console.log("WpsstmPagePlayer:play_or_skip_tracklist #" + tracklist_idx);
        var tracklist_obj = self.get_tracklist_obj(tracklist_idx);

        //cannot play this tracklist
        if (!tracklist_obj.can_play) {
            self.play_next_tracklist();
            return;
        }
        
        tracklist_obj.init_tracklist_track();

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
        
        console.log("WpsstmPagePlayer:get_maybe_shuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
        
    }
    
    get_maybe_unshuffle_tracklist_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var shuffle_order = self.tracklists_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        console.log("WpsstmPagePlayer:get_maybe_unshuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }

    play_previous_tracklist(){
        var self = this;
        
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);
        var queue_tracklist_idx = current_tracklist_idx; //get real track index
        var first_tracklist_idx = 0;
        var new_tracklist;
        
        console.log("WpsstmPagePlayer:play_previous_tracklist()");

        //try to get previous track
        for (var i = 0; i < self.tracklists.length; i++) {

            if (queue_tracklist_idx == first_tracklist_idx){
                console.log("WpsstmPagePlayer:play_previous_tracklist() : is page first tracklist");
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
            console.log("WpsstmPagePlayer:play_previous_tracklist() #" + queue_tracklist_idx);
            var last_track_idx = new_tracklist.tracks.length -1;
            new_tracklist.init_tracklist_track(last_track_idx);
        }
    }
    
    play_next_tracklist(){
        var self = this;
        
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);
        var queue_tracklist_idx = current_tracklist_idx;
        var last_tracklist_idx = self.tracklists.length -1;
        var new_tracklist;

        //try to get previous track
        for (var i = 0; i < self.tracklists.length; i++) {

            if (queue_tracklist_idx == last_tracklist_idx){
                
                console.log("WpsstmPagePlayer:play_next_tracklist() : is page last tracklist, go back to first tracklist");
                self.end_current_tracklist();
                
                if ( !self.is_loop ){
                    break;
                }else{
                    queue_tracklist_idx = 0;
                }

            }else{
                queue_tracklist_idx = Number(queue_tracklist_idx) + 1;
            }

            if (queue_tracklist_idx == current_tracklist_idx){
                console.log("WpsstmPagePlayer:play_next_tracklist() : Playlist loop, abord");
                return false;
            }

            queue_tracklist_idx = self.get_maybe_shuffle_tracklist_idx(queue_tracklist_idx);
            var check_tracklist = self.get_tracklist_obj(queue_tracklist_idx);

            if ( check_tracklist.can_play){
                new_tracklist = check_tracklist;
                break;
            }
        }
        
        if (check_tracklist){
            console.log("WpsstmPagePlayer:play_next_tracklist() #" + queue_tracklist_idx);
            check_tracklist.init_tracklist_track();
        }
    }
    
    end_current_tracklist(){
        var self = this;
        var current_tracklist = self.get_tracklist_obj();
        if (current_tracklist !== false){
            console.log("WpsstmPagePlayer:end_current_tracklist() #" + current_tracklist.tracklist_idx);
            current_tracklist.abord_tracks_sources_request(); //abord current requests
            current_tracklist.end_current_track();
            self.current_tracklist_idx = null;
        }
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