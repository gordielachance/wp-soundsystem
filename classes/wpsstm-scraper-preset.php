<?php

abstract class WP_SoundSystem_Live_Playlist_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      null;
    var $preset_url =       null;
    var $preset_name =      null;
    var $preset_desc =      null;
    var $preset_options =   array();

    var $wizard_suggest =   true; //suggest or not this preset in the wizard

    public function __construct($post_id = null){
        
        parent::__construct($post_id);
        
    }

    function get_default_options(){
        $defaults = parent::get_default_options();
        return array_replace_recursive((array)$defaults,(array)$this->preset_options); //last one has priority
    }

    /*
    Override this functions if your preset needs to filter the tracks.  
    Don't forget the call to the parent function at the end.
    */
    
    protected function validate_tracks($tracks){
        return parent::validate_tracks($tracks);
    }
    
    /*
    Checks that the preset can be used (eg. check for an API client ID)
    */
    static function can_use_preset(){
        return true;
    }
    
    /*
    Checks that the preset can handle $this->feed_url
    */
    function can_load_feed(){
        return true;
    }

}
 