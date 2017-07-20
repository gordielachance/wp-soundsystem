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
        
        $(document).trigger( "wpsstmPageDomReady"); //custom event

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

wpsstm_page_player = new WpsstmPagePlayer();