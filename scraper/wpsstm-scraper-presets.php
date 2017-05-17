<?php

abstract class WP_SoundSytem_Playlist_Scraper_Preset extends WP_SoundSytem_Playlist_Scraper_Datas{
    var $slug = null;
    var $name = null;
    
    var $pattern = null; //regex pattern that would match an URL
    var $redirect_url = null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables = array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    var $can_use_preset = true; //if this preset requires special conditions (eg. an API key or so), override this in your preset class.
    var $wizard_suggest = true; //suggest or not this preset in the wizard

    public function init($url,$options){
        parent::init($url,$options);
        
        //populate variables from URL
        if ($this->pattern){
            
            preg_match($this->pattern, $this->url, $url_matches);
            if ( $url_matches ){
                
                array_shift($url_matches); //remove first item (full match)
                $this->populate_variable_values($url_matches);
            }
        }
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
    
    protected function get_remote_url(){
        
        if ($this->redirect_url){
            $this->redirect_url = $this->variables_fill_string($this->redirect_url);
            return $this->redirect_url;
        }else{
            return $this->url;
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
    
    $presets[] = new WP_SoundSytem_Playlist_LastFM_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_Spotify_Playlist_Api();
    $presets[] = new WP_SoundSytem_Playlist_Reddit_Api();
    $presets[] = new WP_SoundSytem_Playlist_Radionomy_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_SomaFM_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_BBC_Station_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_BBC_Playlist_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_Slacker_Station_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_Soundcloud_Api();
    $presets[] = new WP_SoundSytem_Playlist_Soundsgood_Api();
    $presets[] = new WP_SoundSytem_Playlist_Deezer_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_Hypem_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_Twitter_Scraper();
    $presets[] = new WP_SoundSytem_Playlist_RTBF_Scraper();
    
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','wpsstm_register_scraper_presets');

    