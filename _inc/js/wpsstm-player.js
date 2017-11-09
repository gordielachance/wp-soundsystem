var bottom_wrapper_el;
var bottom_el;
var bottom_track_wraper_el;

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
    var all_tracklists = $( ".wpsstm-tracklist" );

    wpsstm_page_player.populate_tracklists(all_tracklists);

    /*
    Player : previous / next
    */

    bt_prev_track.click(function(e) {
        e.preventDefault();
        var tracklist_obj = wpsstm_page_player.get_page_tracklist();
        tracklist_obj.previous_track_jump();
    });

    bt_next_track.click(function(e) {
        e.preventDefault();
        var tracklist_obj = wpsstm_page_player.get_page_tracklist();
        tracklist_obj.next_track_jump();
    });
    
    //scroll to playlist track when clicking the player's track number
    $('#wpsstm-bottom').on( "click",'[itemprop="track"] .wpsstm-track-position', function(e) {
        e.preventDefault();
        var player_track_el = $(this).parents('[itemprop="track"]');
        var track_idx = Number(player_track_el.attr('data-wpsstm-track-idx'));
        
        var tracklist_el = player_track_el.closest('[data-wpsstm-tracklist-idx]');
        var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));
        var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;
        
        var tracklist_obj =  wpsstm_page_player.get_page_tracklist(tracklist_idx);
        var track_obj = tracklist_obj.get_track_obj(track_idx);
        
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

    if ( wpsstm_page_player.can_repeat ){
        $(wpsstm_player_loop_el).addClass('active');
    }

    $(wpsstm_player_loop_el).find('a').click(function(e) {
        e.preventDefault();

        var is_active = !wpsstm_page_player.can_repeat;
        wpsstm_page_player.can_repeat = is_active;

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
//TO FIX TO improve ?

$(window).bind('beforeunload', function(){

    if (wpsstm_page_player.current_media && !wpsstm_page_player.current_media.paused){
        return wpsstmPlayer.leave_page_text;
    }

});

class WpsstmPagePlayer {
    constructor(){
        var self = this;
        self.debug("new WpsstmPagePlayer()");
        self.current_tracklist_idx      = undefined;
        self.tracklists                 = [];
        self.tracklists_shuffle_order   = [];
        self.is_shuffle                 = ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        self.can_repeat                    = ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
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

        });
        
        $(document).trigger( "wpsstmPageDomReady"); //custom event

        //shuffle
        self.tracklists_shuffle_order = wpsstm_shuffle(self.tracklists_shuffle_order);
        
    }

    get_page_tracklist(tracklist_idx){
        
        var self = this;

        if(typeof tracklist_idx === 'undefined'){
            tracklist_idx = self.current_tracklist_idx;
        }

        tracklist_idx = Number(tracklist_idx);
        var tracklist_obj = this.tracklists[tracklist_idx];
        if(typeof tracklist_obj === 'undefined') return false;
        return tracklist_obj;
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
        var current_tracklist_idx = self.current_tracklist_idx;
        current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(current_tracklist_idx);
        var first_track_idx = self.get_maybe_unshuffle_tracklist_idx(0);
        
        if ( self.current_tracklist_idx === 'undefined'){
            self.debug('next_track_jump failed: no current tracklist');
            return;
        }

        var tracklists = $(self.tracklists).get();
        var tracklists_before = tracklists.slice(0,current_tracklist_idx).reverse();
        var tracklists_after = [];
        
        if ( wpsstm_page_player.can_repeat ){
            tracklists_after = tracklists.slice(current_tracklist_idx+1).reverse(); 
        }

        //which should we play ?
        var tracklists_reordered = tracklists_before.concat(tracklists_after);
        var tracklist_obj = self.get_first_availablelist(tracklists_reordered);
        
        if (!tracklist_obj){
            console.log("next_track_jump: unable to identify next tracklist in page");
            return;
        }

        if ( tracklist_obj.index !== first_track_idx ){
            tracklist_obj.play_subtrack();
        }else{ //current tracklist is first tracklist
            if ( wpsstm_page_player.can_repeat ){
                tracklist_obj.play_subtrack();
                return;
            }else{
                self.debug("previous_tracklist_jump is the first tracklist, and can_repeat is disabled.");
                return;
            }
        }

    }
    
    next_tracklist_jump(){
        
        var self = this;
        var current_tracklist_idx = self.current_tracklist_idx;
        current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(current_tracklist_idx);
        var last_tracklist = self.tracklists[self.tracklists.length-1];
        
        if ( self.current_tracklist_idx === 'undefined'){
            self.debug('next_tracklist_jump failed: no current tracklist');
            return;
        }

        var tracklists = $(self.tracklists).get();
        var tracklists_after = tracklists.slice(current_tracklist_idx+1); 
        var tracklists_before = [];

        if ( wpsstm_page_player.can_repeat ){
            tracklists_before = tracklists.slice(0,current_tracklist_idx);
        }

        var tracklists_reordered = tracklists_after.concat(tracklists_before);
        
        //which is the first tracklist of tracklistlist ?
        var tracklist_obj = self.get_first_availablelist(tracklists_reordered);
        
        if (!tracklist_obj){
            console.log("next_tracklist_jump: unable to identify next tracklist in tracklistlist #" + self.index);
            return;
        }
       
        //current tracklist is last tracklist
        if ( tracklist_obj.index !== last_tracklist.index ){
            self.play_subtracklist();
        }else{ //current tracklist is last tracklist
            if ( wpsstm_page_player.can_repeat ){
                wpsstm_page_player.next_tracklistlist_jump();
                return;
            }else{
                self.debug("next_tracklist_jump for tracklistlist #"+self.index+" is the last tracklist, and can_repeat is disabled.");
                return;
            }
        }

    }
    
    /*
    Get the first playable track from an array - which is useful if we reverse; slice, etc. tracks.
    */
    
    get_first_availablelist(tracklists){
        
        var self = this;
        var first = undefined;

        if (typeof tracklists === 'undefined'){
            tracklists = self.tracklists;
        }
        
        if (tracklists.length === 0){
            self.debug('get_first_availablelist - empty tracklists input');
            return;
        }

        //reject only if we CANNOT play this track. If we don't know yet or that we can, return track.
        $.each(tracklist_obj, function( index, tracklist_obj ) {
            if (tracklist_obj.can_play !== false){ 
                first = tracklist_obj;
                return false; //break
            }
        });
        
        return first;
        
    }
    
    end_current_tracklist(){
        var self = this;
        var current_tracklist = self.get_page_tracklist();
        if (current_tracklist !== false){
            self.debug("end_current_tracklist() #" + current_tracklist.index);
            current_tracklist.abord_tracks_sources_request(); //abord current requests
            current_tracklist.end_current_track();
            self.current_tracklist_idx = undefined;
        }
    }
    
}

wpsstm_page_player = new WpsstmPagePlayer();