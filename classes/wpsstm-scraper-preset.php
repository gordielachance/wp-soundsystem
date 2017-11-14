<?php

abstract class WP_SoundSystem_Live_Playlist_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      null;
    var $preset_url =       null;
    var $preset_name =      null;
    var $preset_desc =      null;
    var $preset_options =   array();
    var $pattern =          null; //regex pattern that would match an URL
    var $remote_url =     null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables =        array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    var $can_use_preset =   true;
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

}
 