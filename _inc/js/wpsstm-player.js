class WpsstmPagePlayer {
    constructor(){
        this.debug("new WpsstmPagePlayer()");
        this.current_track =            undefined;
        this.requested_track =          undefined;
        this.tracklists =               [];
        this.tracklists_shuffle_order = [];
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
        
        this.player_el =                $('#wpsstm-player');
        this.bottom_trackinfo_el =      this.player_el.find('#wpsstm-player-track');
        this.player_audio_el =          this.player_el.find('#wpsstm-audio-container audio');
        this.wpsstm_player_shuffle_el = $('#wpsstm-player-shuffle');
        this.wpsstm_player_loop_el =    $('#wpsstm-player-loop');
        
    }

    debug(msg){
        var prefix = "WpsstmPagePlayer";
        wpsstm_debug(msg,prefix);
    }
    
    init_player(){
        var self = this;
        var success = $.Deferred();
        
        wpsstm.player_audio_el.mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            pluginPath: wpsstmPlayer.plugin_path, //'https://cdnjs.com/libraries/mediaelement/'
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, player) {
                wpsstm.current_media = mediaElement;
                console.log("player has init");
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
                /*
                var source_instances = self.get_source_instances();
                source_instances.addClass('source-error');
                source_instances.removeClass('source-active');
                success.reject();
                */
            }
        });
        return success.promise();
    }
    
    init(){
        var self = this;
        self.init_player();
    }
    
    set_audio_sources(sources){
        
        var self = this;
        
        
        var old_sources = this.player_audio_el.find('source');
        
        //remove old sources
        old_sources.each(function(i) {
            $(this).remove();
        });
        
        if ( sources === undefined) return;
        
        //append new sources
        var new_sources = [];
        $( sources ).each(function(i, source_attr) {
            //create source element
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });
            new_sources.push(source_el);
        });
        this.player_audio_el.append(new_sources);
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
    
    /*
    Return the tracklists; in the shuffled order if is_shuffle is true.
    */
    get_ordered_tracklists(){
        
        self = this;

        if ( !self.is_shuffle ){
            return self.tracklists;
        }else{
            
            var shuffled_tracklists = [];

            $(self.tracklists_shuffle_order).each(function() {
                var idx = this;
                shuffled_tracklists.push(self.tracklists[idx]);
            });

            return shuffled_tracklists;
        }
        
    }

    previous_tracklist_jump(){

        var self = this;
        
        if ( self.current_track === undefined){
            self.debug('next_track_jump failed: no current tracklist');
            return;
        }
        
        var current_tracklist_idx = self.current_track.tracklist.index;
        current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(current_tracklist_idx); //shuffle ?

        var tracklists_before = self.get_ordered_tracklists().slice(0,current_tracklist_idx).reverse();

        //which one should we play?
        var tracklists_playable = tracklists_before.filter(function (tracklist_obj) {
            return (tracklist_obj.can_play !== false);
        });
        var tracklist_obj = tracklists_playable[0];
        
        if (tracklist_obj){
            tracklist_obj.start_tracklist();
        }else{
            
            self.debug("previous_tracklist_jump: can repeat ? " + wpsstm.can_repeat);

            if ( wpsstm.can_repeat ){
                
                var last_tracklist_idx = self.tracklists.length - 1;
                last_tracklist_idx = self.get_maybe_shuffle_tracklist_idx(last_tracklist_idx); //shuffle ?
                var last_tracklist = self.tracklists[last_tracklist_idx];
                
                var last_track_idx = last_tracklist.tracks.length - 1;
                last_track_idx = last_tracklist.get_maybe_shuffle_track_idx(last_track_idx); //shuffle ?
                
                var last_track = last_tracklist.tracks[last_track_idx];
                last_track.play_track();
                
            }
        }

    }
    
    next_tracklist_jump(){

        var self = this;
        var current_tracklist_idx = self.current_track.tracklist.index;
        current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(current_tracklist_idx); //shuffle ?

        var tracklists_after = self.get_ordered_tracklists().slice(current_tracklist_idx+1); 

        //which one should we play?
        var tracklists_playable = tracklists_after.filter(function (tracklist_obj) {
            return (tracklist_obj.can_play !== false);
        });
        var tracklist_obj = tracklists_playable[0];

        if (tracklist_obj){
            tracklist_obj.start_tracklist();
        }else{
            
            self.debug("next_tracklist_jump: can repeat ? " + wpsstm.can_repeat);
            
            if ( wpsstm.can_repeat ){
                
                var first_tracklist_idx = 0;
                first_tracklist_idx = self.get_maybe_shuffle_tracklist_idx(first_tracklist_idx); //shuffle ?
                var first_tracklist = self.tracklists[first_tracklist_idx];
                first_tracklist.start_tracklist();
            }
        }

    }
    
    showMoreLessActions(actions_container_el,options){
 
        return; //TOFIXGGG temporary disabled until it works as expected.

        // OPTIONS
        var defaults = {
            childrenToShow:     '.wpsstm-action:not(.wpsstm-advanced-action)',
            btMore:             '<li class="wpsstm-action"><i class="fa fa-chevron-right" aria-hidden="true"></i></li>',
            btLess:             false,
        };

        var options =  $.extend(defaults, options);

        return $(actions_container_el).toggleChildren(options);

    }

}

(function($){

    /*
    initialize player when page has loaded
    */
    $(document).on( "wpsstmStartTracklist", function( event ) {
        //TOUFIX TOUCHECK was hooked on PageTracklistsInit before
        
        /*
        Previous track bt
        */

        //previous track bt
        $('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            wpsstm.current_track.tracklist.previous_track_jump();
        });

        /*
        Next track bt
        */
        $('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            wpsstm.current_track.tracklist.next_track_jump();
        });

        /*
        Scroll to playlist track when clicking the player's track number
        */
        wpsstm.player_el.on( "click",'[itemprop="track"] .wpsstm-track-position', function(e) {
            e.preventDefault();
            var player_track_el = $(this).parents('[itemprop="track"]');
            var track_idx = Number(player_track_el.attr('data-wpsstm-track-idx'));

            var tracklist_el = player_track_el.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));
            var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;

            var track_obj = wpsstm.current_track;

            var track_el = track_obj.track_el;
            var newTracksCount = track_obj.index + 1;

            //https://stackoverflow.com/a/6677069/782013
            $('html, body').animate({
                scrollTop: track_el.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });

        /*
        Shuffle button
        */
        if ( wpsstm.is_shuffle ){
            wpsstm.wpsstm_player_shuffle_el.addClass('active');
        }

        wpsstm.wpsstm_player_shuffle_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !wpsstm.is_shuffle;
            wpsstm.is_shuffle = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-shuffle", true);
                wpsstm.wpsstm_player_shuffle_el.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-player-shuffle");
                wpsstm.wpsstm_player_shuffle_el.removeClass('active');
            }

        });

        /*
        Loop button
        */
        if ( wpsstm.can_repeat ){
            wpsstm.wpsstm_player_loop_el.addClass('active');
        }

        wpsstm.wpsstm_player_loop_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !wpsstm.can_repeat;
            wpsstm.can_repeat = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-loop", true);
                wpsstm.wpsstm_player_loop_el.addClass('active');
            }else{
                localStorage.setItem("wpsstm-player-loop", false);
                wpsstm.wpsstm_player_loop_el.removeClass('active');
            }

        });
        
    });

    /*
    Confirmation popup is a media is playing and that we leave the page
    //TO FIX TO improve ?
    */
    $(window).bind('beforeunload', function(){

        if (wpsstm.current_media && !wpsstm.current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }

    });
    

})(jQuery);

var wpsstm = new WpsstmPagePlayer();
wpsstm.init();