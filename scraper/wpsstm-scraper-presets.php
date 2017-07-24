<?php

abstract class WP_SoundSystem_Live_Playlist_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      null;
    var $preset_url =       null;
    var $preset_name =      null;
    var $preset_desc =      null;
    var $preset_options =   array();
    var $pattern =          null; //regex pattern that would match an URL
    var $redirect_url =     null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables =        array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    var $can_use_preset =   true; //if this preset requires special conditions (eg. an API key or so), override this in your preset class.
    var $wizard_suggest =   true; //suggest or not this preset in the wizard

    public function __construct($post_id = null){
        
        parent::__construct($post_id);
        
        //populate variables from URL
        if ($this->feed_url && $this->pattern){
            
            preg_match($this->pattern, $this->feed_url, $url_matches);
            if ( $url_matches ){
                
                array_shift($url_matches); //remove first item (full match)
                $this->populate_variable_values($url_matches);
            }
        }
        
    }
    
    function get_default_options(){
        $defaults = parent::get_default_options();
        return array_replace_recursive((array)$defaults,(array)$this->preset_options); //last one has priority
    }

    /**
    If $url_matches is empty, it means that the feed url does not match the pattern.
    **/
    
    public function can_load_tracklist_url($url){

        if (!$this->pattern) return true;

        preg_match($this->pattern, $url, $url_matches);

        return (bool)$url_matches;
    }
    
    /**
    Fill the preset $variables.
    The array keys from the preset $variables and the input $values_arr have to match.
    **/

    protected function populate_variable_values($values_arr){
        
        $key = 0;

        foreach((array)$this->variables as $variable_slug=>$variable){
            $value = ( isset($values_arr[$key]) ) ? $values_arr[$key] : null;
            
            if ($value){
                $this->set_variable_value($variable_slug,$value);
            }
            
            $key++;
        }

    }

    protected function set_variable_value($slug,$value='null'){
        $this->variables[$slug] = $value;
    }
    
    public function get_variable_value($slug){

        foreach($this->variables as $variable_slug => $variable){
            
            if ( $variable_slug == $slug ){
                return $variable;
            }
        }

    }
    
    protected function get_request_url(){
        
        if ($this->redirect_url){
            $this->redirect_url = $this->variables_fill_string($this->redirect_url);
            return $this->redirect_url;
        }else{
            return $this->feed_url;
        }

    }
    
    /*
    Update a string and replace all the %variable-key% parts of it with the value of that variable if it exists.
    */
    
    public function variables_fill_string($str){

        foreach($this->variables as $variable_slug => $variable_value){
            $pattern = '%' . $variable_slug . '%';
            $value = $variable_value;
            
            if ($value) {
                $str = str_replace($pattern,$value,$str);
            }
        }

        return $str;
    }
    

}

/*
Register scraper presets.
*/
function wpsstm_register_scraper_presets($presets){
    
    $presets_path = wpsstm()->plugin_dir . 'scraper/presets/';
    
    require_once( $presets_path . 'bbc.php' );
    require_once( $presets_path . 'deezer.php' );
    require_once( $presets_path . 'hypem.php' );
    require_once( $presets_path . 'lastfm.php' );
    require_once( $presets_path . 'radionomy.php' );
    require_once( $presets_path . 'rtbf.php' );
    require_once( $presets_path . 'slacker.php' );
    require_once( $presets_path . 'somafm.php' );
    require_once( $presets_path . 'soundcloud.php' );
    require_once( $presets_path . 'soundsgood.php' );
    require_once( $presets_path . 'spotify.php' );
    require_once( $presets_path . 'twitter.php' );
    require_once( $presets_path . 'reddit.php' );
    require_once( $presets_path . 'indieshuffle.php' );
    require_once( $presets_path . 'radioking.php' );
    
    $presets[] = new WP_SoundSystem_Preset_LastFM_Scraper();
    $presets[] = new WP_SoundSystem_Preset_Spotify_Playlists_Api();
    $presets[] = new WP_SoundSystem_Preset_Radionomy_Playlists_Api();
    $presets[] = new WP_SoundSystem_Preset_SomaFM_Stations();
    $presets[] = new WP_SoundSystem_Preset_BBC_Stations();
    $presets[] = new WP_SoundSystem_Preset_BBC_Playlists();
    $presets[] = new WP_SoundSystem_Preset_Slacker_Stations();
    $presets[] = new WP_SoundSystem_Preset_Soundcloud_Api();
    $presets[] = new WP_SoundSystem_Preset_Soundsgood_Playlists_Api();
    $presets[] = new WP_SoundSystem_Preset_Deezer_Playlists();
    $presets[] = new WP_SoundSystem_Preset_Hypem_Scraper();
    $presets[] = new WP_SoundSystem_Preset_Twitter_Timelines();
    $presets[] = new WP_SoundSystem_Preset_RTBF_Stations();
    $presets[] = new WP_SoundSystem_Preset_Reddit_Api();
    $presets[] = new WP_SoundSystem_Preset_IndieShuffle_Scraper();
    $presets[] = new WP_SoundSystem_Preset_RadioKing_Api();
    
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','wpsstm_register_scraper_presets');

    