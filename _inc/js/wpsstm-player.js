var bottom_wrapper_el;
var bottom_el;
var bottom_track_wraper_el;

var wpsstm_mediaElement;
var wpsstm_mediaElementPlayer;
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

$(document).on( "wpsstmPageDomReady", function( event ) {
    
    //Autoplay at init
    wpsstm_page_player.page_autoplay();

    
});

//Confirmation popup is a media is playing and that we leave the page

$(window).bind('beforeunload', function(){
    if (wpsstm_mediaElement && !wpsstm_mediaElement.paused){
        return wpsstmPlayer.leave_page_text;
    }
});

class WpsstmPagePlayer {
    constructor(){
        var self = this;
        self.debug("new WpsstmPagePlayer()");
        self.current_tracklist_idx;
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
            self.tracklists.push(tracklist);
            self.tracklists_shuffle_order.push(i);  

        });
        
        $(document).trigger( "wpsstmPageDomReady"); //custom event

        //shuffle
        self.tracklists_shuffle_order = wpsstm_shuffle(self.tracklists_shuffle_order);
        
    }
    
    page_autoplay(){
        //get playlists where autoplay is enabled
        var autoplay_tracklists = [];

        $(wpsstm_page_player.tracklists).each(function( i, tracklist_obj ) {
            if ( tracklist_obj.options.autoplay ){
                autoplay_tracklists.push(tracklist_obj);
            }
        });

        if (autoplay_tracklists.length === 0) return;

        /*
        wpsstm_page_player.debug("page autoplay : trying to start first autoplay tracklist available...");
        var idx_list = autoplay_tracklists.map(function(a) {return a.index;});
        wpsstm_page_player.debug(idx_list);
        */

        //play first playable one
        wpsstm_page_player.get_first_playable_tracklist(autoplay_tracklists).then(
            function(tracklist_obj) {
                //play first track
                tracklist_obj.play_subtrack();
            }, function(error_msg) {
                self.debug(error_msg);
            }
        );
    }

    
    play_tracklist(tracklist_idx,track_idx,source_idx){

        var self = this;
        
        var debug_msg = "play_tracklist()";
        if(typeof tracklist_idx !== 'undefined') debug_msg += " #" + tracklist_idx;
        if(typeof track_idx !== 'undefined') debug_msg += " track #" + track_idx;
        if(typeof source_idx !== 'undefined') debug_msg += " source #" + source_idx;
        self.debug(debug_msg);
        
        var tracklist_obj = self.get_page_tracklist(tracklist_idx);
        return tracklist_obj.play_subtrack(track_idx,source_idx);

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
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);
        var current_tracklist = self.get_page_tracklist();
        
        var tracklists = $(self.tracklists).get();
        var tracklists_before = tracklists.slice(0,current_tracklist_idx).reverse();
        var tracklists_after = [];
        
        if ( wpsstm_page_player.can_repeat ){
            tracklists_after = tracklists.slice(current_tracklist_idx+1).reverse(); 
        }
        
        //find first playable in reverse order
        var tracklists_reordered = tracklists_before.concat(tracklists_after);
        
        //no other tracklist to play; but repeat is enabled
        if ( (tracklists_reordered.length == 0) && ( wpsstm_page_player.can_repeat ) ){
            tracklists_reordered.push(current_tracklist);
        }

        self.get_first_playable_tracklist(tracklists_reordered).then(
            function(tracklist_obj) {
                self.debug("previous_tracklist_jump() : jumped to tracklist #" + tracklist_obj.index);
                
                //find first playable in reverse order
                var tracks_reversed = $(tracklist_obj.tracks).get().reverse();
                tracklist_obj.get_first_playable_track(tracks_reversed).then(
                    function(track_obj) {
                        tracklist_obj.play_subtrack(track_obj.index);
                    }, function(error) {
                        self.debug("previous_tracklist_jump() : unable to find any playable track for tracklist #" + tracklist_obj.index);
                        wpsstm_page_player.previous_tracklist_jump();
                    }
                );
                
                
            }, function(error) {
                console.log("previous_tracklist_jump() : unable to find any playable tracklist");
            }
        );
        
    }
    
    next_tracklist_jump(){
        
        var self = this;
        var current_tracklist_idx = self.get_maybe_unshuffle_tracklist_idx(self.current_tracklist_idx);
        var current_tracklist = self.get_page_tracklist();

        var tracklists = $(self.tracklists).get();
        var tracklists_after = tracklists.slice(current_tracklist_idx+1); 
        var tracklists_before = [];
        
        if ( wpsstm_page_player.can_repeat ){
            tracklists_before = tracklists.slice(0,current_tracklist_idx);
        }
        
        var tracklists_reordered = tracklists_after.concat(tracklists_before);
        
        //no other tracklist to play; but repeat is enabled
        if ( (tracklists_reordered.length == 0) && ( wpsstm_page_player.can_repeat ) ){
            tracklists_reordered.push(current_tracklist);
        }

        //find first playable
        self.get_first_playable_tracklist(tracklists_reordered).then(
            function(tracklist_obj) {
                tracklist_obj.play_subtrack();
            }, function(error) {
                self.debug("next_tracklist_jump() : unable to find any playable tracklist");
            }
        );
        

    }
    
    get_first_playable_tracklist(tracklists){
        
        var self = this;
        var hasPlayable = $.Deferred();

        if (typeof tracklists === 'undefined'){
            tracklists = self.tracklists;
        }
        
        if (tracklists.length === 0) hasPlayable.reject('empty tracklists input');

        /*
        This function will loop until a promise is resolved
        */
        
        (function iterateTracklist(index) {

            if (index >= tracklists.length) {
                hasPlayable.reject("unable to find a playable tracklist");
                return;
            }
            
            var tracklist_obj = tracklists[index];
            
            //maybe refresh tracklist
            if ( tracklist_obj.is_expired ){
                tracklist_obj.get_tracklist_request().then(
                    function(success){
                        tracklist_obj.get_first_playable_track().then(
                            function(success_msg){
                                hasPlayable.resolve(tracklist_obj);
                            },
                            function(error_msg){
                                iterateTracklist(index + 1);
                            }
                        );
                    },
                    function(error){
                        iterateTracklist(index + 1);
                    }
                );
                
            }else{
                tracklist_obj.get_first_playable_track().then(
                    function(success_msg){
                        hasPlayable.resolve(tracklist_obj);
                    },
                    function(error_msg){
                        iterateTracklist(index + 1);
                    }
                );
                
            }

        })(0);

        return hasPlayable.promise();

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