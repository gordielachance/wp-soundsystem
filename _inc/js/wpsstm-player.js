(function($){

    $(document).ready(function(){

        //scroll to playlist track when clicking the player's track number
        wpsstm_page_player.bottom_el.on( "click",'[itemprop="track"] .wpsstm-track-position', function(e) {
            e.preventDefault();
            var player_track_el = $(this).parents('[itemprop="track"]');
            var track_idx = Number(player_track_el.attr('data-wpsstm-track-idx'));

            var tracklist_el = player_track_el.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));
            var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;

            var track_obj = wpsstm_page_player.current_track;

            var track_el = track_obj.track_el;
            var newTracksCount = track_obj.index + 1;

            //https://stackoverflow.com/a/6677069/782013
            $('html, body').animate({
                scrollTop: track_el.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });

        /*
        //TO FIX
        instead of the default thickbox popup (link has the 'thickbox' class), we should call it 'manually' so we can check user is logged before displaying it.
        //tracklist selector popup.
        $(document).on( "click",'[itemprop="track"] #wpsstm-track-action-playlists a', function(e){

            e.preventDefault();
            if ( !wpsstm_get_current_user_id() ) return;

            var popup_title = $(this).attr('title');
            var popup_url = $(this).attr('href');
            tb_show(popup_title, popup_url + '&TB_iframe=true');

        });
        */

        /*
        Player : shuffle
        */

        if ( wpsstm_page_player.is_shuffle ){
            wpsstm_page_player.wpsstm_player_shuffle_el.addClass('active');
        }

        wpsstm_page_player.wpsstm_player_shuffle_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !wpsstm_page_player.is_shuffle;
            wpsstm_page_player.is_shuffle = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-shuffle", true);
                wpsstm_page_player.wpsstm_player_shuffle_el.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-player-shuffle");
                wpsstm_page_player.wpsstm_player_shuffle_el.removeClass('active');
            }

        });

        /*
        Player : loop
        */

        if ( wpsstm_page_player.can_repeat ){
            wpsstm_page_player.wpsstm_player_loop_el.addClass('active');
        }

        wpsstm_page_player.wpsstm_player_loop_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !wpsstm_page_player.can_repeat;
            wpsstm_page_player.can_repeat = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-loop", true);
                wpsstm_page_player.wpsstm_player_loop_el.addClass('active');
            }else{
                localStorage.setItem("wpsstm-player-loop", false);
                wpsstm_page_player.wpsstm_player_loop_el.removeClass('active');
            }

        });

    });
    
    /*
    PLAYER BUTTONS
    */
    $(document).on( "PageTracklistsInit", function( event, track_obj ) {
        
        /*
        Player : previous / next
        */

        $('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            wpsstm_page_player.current_track.tracklist.previous_track_jump();
        });

        $('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            wpsstm_page_player.current_track.tracklist.next_track_jump();
        });
        
    });
    
    /*
    AUTOPLAY
    */
    $(document).on( "PageTracklistsInit", function( event, tracklist_obj ) {

        //which one should we autoplay play?
        var tracklists_autoplay = wpsstm_page_player.tracklists.filter(function (tracklist_obj) {
            return (tracklist_obj.tracklist_el.hasClass('tracklist-autoplay') );
        });
        
        //first to autoplay
        var play_tracklist = tracklists_autoplay[0];
        
        if ( !play_tracklist ) return;
        
        play_tracklist.debug("autoplay");
        play_tracklist.start_tracklist();
        
    });


    //Confirmation popup is a media is playing and that we leave the page
    //TO FIX TO improve ?

    $(window).bind('beforeunload', function(){

        if (wpsstm_page_player.current_media && !wpsstm_page_player.current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }

    });
    

})(jQuery);


class WpsstmPagePlayer {
    constructor(){
        this.debug("new WpsstmPagePlayer()");
        this.current_track =            undefined;
        this.requested_track =          undefined;
        this.tracklists =               [];
        this.tracklists_shuffle_order = [];
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
        
        this.bottom_wrapper_el =         $('#wpsstm-bottom-wrapper');
        this.bottom_el =                 this.bottom_wrapper_el.find('#wpsstm-bottom');
        this.bottom_track_wraper_el =    this.bottom_el.find('#wpsstm-bottom-track-wrapper');
        this.bottom_trackinfo_el =       this.bottom_track_wraper_el.find('#wpsstm-bottom-track-info');
        this.wpsstm_player_shuffle_el =  $('#wpsstm-player-shuffle');
        this.wpsstm_player_loop_el =     $('#wpsstm-player-loop');
        
    }

    debug(msg){
        var prefix = "WpsstmPagePlayer: ";
        wpsstm_debug(msg,prefix);
    }

    init_page_tracklists(){
        
        var self = this;
        
        var all_tracklists = $( ".wpsstm-tracklist" );

        if ( all_tracklists.length <= 0 ) return;

        self.debug("init_page_tracklists()");
        
        var preload_promises = [];
        
        //should we show it by default ?
        all_tracklists.each(function(index,tracklist_el) {
            var tracklist = new WpsstmTracklist(tracklist_el,index);
            self.tracklists.push(tracklist);
            self.tracklists_shuffle_order.push(index);
            
            //preload tracklist on startup
            var promise = tracklist.maybe_refresh();
            preload_promises.push(promise);
        });
        
        $(document).trigger("PageTracklistsInit"); //custom event

        /*
        //shuffle
        self.tracklists_shuffle_order = wpsstm_shuffle(self.tracklists_shuffle_order);
        */
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

    previous_tracklist_jump(){

        var self = this;
        
        if ( self.current_track === undefined){
            self.debug('next_track_jump failed: no current tracklist');
            return;
        }
        
        var tracklist_idx = self.current_track.tracklist.index;
        tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(tracklist_idx);
        var first_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(0);
        


        var tracklists = $(self.tracklists).get();
        var tracklists_before = tracklists.slice(0,tracklist_idx).reverse();

        //which one should we play?
        var tracklists_playable = tracklists_before.filter(function (tracklist_obj) {
            return (tracklist_obj.can_play !== false);
        });
        var tracklist_obj = tracklists_playable[0];
        
        if (!tracklist_obj){
            self.debug("previous_tracklist_jump: is first tracklist");
            if ( !wpsstm_page_player.can_repeat ){
                self.debug("previous_tracklist_jump: can_repeat is disabled.");
            }else{
                var last_tracklist = self.tracklists[self.tracklists.length - 1];
                var last_track = last_tracklist.tracks;
                last_track.play_track();
            }
            return;
        }else{
            tracklist_obj.start_tracklist();
        }

    }
    
    next_tracklist_jump(){

        var self = this;
        var current_tracklist = self.current_track.tracklist;
        var tracklist_idx = current_tracklist.index;
        tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(tracklist_idx);
        
        var last_tracklist = self.tracklists[self.tracklists.length-1];
        var last_tracklist_idx = last_tracklist.index;
        last_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(last_tracklist_idx);

        var tracklists = self.tracklists;
        var tracklists_after = tracklists.slice(tracklist_idx+1); 
        var tracklists_before = [];

        if ( wpsstm_page_player.can_repeat ){
            tracklists_before = tracklists.slice(0,tracklist_idx);
        }

        //which one should we play?
        var tracklists_reordered = tracklists_after.concat(tracklists_before);
        var tracklists_playable = tracklists_reordered.filter(function (tracklist_obj) {
            return (tracklist_obj.can_play !== false);
        });
        var tracklist_obj = tracklists_playable[0];

        if (!tracklist_obj){
            self.debug("next_tracklist_jump: is last tracklist");
            if ( !wpsstm_page_player.can_repeat ){
                self.debug("next_tracklist_jump: can_repeat is disabled.");
                return;
            }else{
                tracklist_obj = self.tracklists[0];
            }
        }
        
        tracklist_obj.start_tracklist();

    }

}

var wpsstm_page_player = new WpsstmPagePlayer();
wpsstm_page_player.init_page_tracklists();