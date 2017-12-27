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
        var prefix = "WpsstmPagePlayer";
        wpsstm_debug(msg,prefix);
    }

    init_page_tracklists(){
        
        var self = this;
        
        var all_tracklists = $( ".tracklist-playable" );

        if ( all_tracklists.length <= 0 ) return;

        self.debug("init_page_tracklists()");
        
        var preload_promises = [];
        
        all_tracklists.each(function(index,tracklist_el) {
            var tracklist = new WpsstmTracklist(tracklist_el,index);
            self.tracklists.push(tracklist);
        });

        self.tracklists_shuffle_order = wpsstm_shuffle( Object.keys(self.tracklists).map(Number) );
        
        /*
        autoplay
        */
        //which one should we autoplay play?
        var tracklists_autoplay = wpsstm.get_ordered_tracklists().filter(function (tracklist_obj) {
            return (tracklist_obj.tracklist_el.hasClass('tracklist-autoplay') );
        });
        
        //first to autoplay
        var play_tracklist = tracklists_autoplay[0];
        
        if ( play_tracklist ){
            play_tracklist.debug("autoplay");
            play_tracklist.start_tracklist();
        }
        


        /*
        autoload
        */
        var tracklists_autoload = self.tracklists.filter(function (tracklist_obj) {
            var has_autoload = (tracklist_obj.options.autoload === true);
            var already_populated = (tracklist_obj.tracks_count > -1); //has already been populated through PHP
            return (has_autoload && !already_populated);
        });

        $(tracklists_autoload).each(function(index,tracklist_obj) {
            var promise = tracklist_obj.maybe_refresh();
            preload_promises.push(promise);
        });      
        
        $(document).trigger("PageTracklistsInit"); //custom event

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

/*
WPSSM init
*/
var wpsstm = new WpsstmPagePlayer();

(function($){
    
    wpsstm.init_page_tracklists();

    //scroll to playlist track when clicking the player's track number
    wpsstm.bottom_el.on( "click",'[itemprop="track"] .wpsstm-track-position', function(e) {
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
    Player : shuffle
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
    Player : loop
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

    /*
    PLAYER BUTTONS
    */
    $(document).on( "PageTracklistsInit", function( event ) {
        
        /*
        Player : previous / next
        */

        $('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            wpsstm.current_track.tracklist.previous_track_jump();
        });

        $('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            wpsstm.current_track.tracklist.next_track_jump();
        });
        
    });

    //Confirmation popup is a media is playing and that we leave the page
    //TO FIX TO improve ?

    $(window).bind('beforeunload', function(){

        if (wpsstm.current_media && !wpsstm.current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }

    });
    

})(jQuery);