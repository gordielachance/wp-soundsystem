<?php

/*
How the scraper works :

It would be too much queries to save the real tracks history for a live playlist : 
it would require to query each remote playlist URL every ~30sec to check if a new track has been played, 
which would use a LOT of resources; and create thousands and thousands of track posts.

Also, we want live playlists tracklists to be related to an URL rather than to a post ID, allowin us to setup 'services'.
Services are playlists were the sources URL contains regular expressions.  
They are meant to fetch different tracklists depending on the input URL (eg. for Spotify or Last.FM)

So live playlists do not create track posts, but we use the WordPress Transients API to temporary store the tracklist.
When the live playlist is viewed, we refresh the tracklist only if the transient is expired.

The transient name is based on the parser URL, not on the parser ID; which allows us more flexibility.

*/

class WP_SoundSytem_Playlist_Scraper{

    public $tracklist;
    

    
    public $notices = array();

    
    function __construct($post_id_or_feed_url = null) {
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-live-tracklist-class.php');

        $this->setup_globals();
        $this->setup_actions();
        

    }

    function setup_globals(){
        $this->tracklist = new WP_SoundSytem_Remote_Tracklist();
        print_r($this->tracklist);die();
    }
    
    function setup_actions(){
        
    }
    
    function init_tracklist($url){
        //load page preset
        if ( $live_tracklist_preset = $this->get_live_tracklist_preset($this) ){
            $this->tracklist = $live_tracklist_preset;
            $this->add_notice( 'wizard-header', 'preset_loaded', sprintf(__('The preset %s has been loaded','wpsstm'),'<em>'.$live_tracklist_preset->preset_name.'</em>') );
        }
    }

    static function get_available_presets(){
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-presets.php');
        
        $available_presets = array();
        $available_presets = apply_filters( 'wpsstm_get_scraper_presets',$available_presets );
        
        foreach((array)$available_presets as $key=>$preset){
            if ( !$preset->can_use_preset ) unset($available_presets[$key]);
        }

        return $available_presets;
    }

    function get_live_tracklist_preset($scraper){
        
        $enabled_presets = array();

        $available_presets = self::get_available_presets();

        //get matching presets
        foreach((array)$available_presets as $preset){

            if ( $preset->can_load_tracklist_url($scraper->feed_url) ){
                $enabled_presets[] = $preset;
            }

        }
        
        //return last (highest priority) preset
        return end($enabled_presets);

    }
    


}
