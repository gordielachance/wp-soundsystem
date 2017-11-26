<?php

class WP_SoundSystem_URL_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_name =      null;
    var $preset_url =       null;
    var $preset_desc =      null;
    var $preset_options =   array();
    var $tracklist;

    static $wizard_suggest =   true; //suggest or not this preset in the wizard

    public function __construct($feed_url = null){
        parent::__construct();
        $this->feed_url = $feed_url;
    }
    
    /*
    Checks that the preset can be used (eg. check for an API client ID)
    */
    public function can_use_preset(){
        return true;
    }

    /*
    Checks that the preset can load the tracklist URL
    */
    protected function can_handle_url(){//TOFIXGGG should be abstract method
        return false;
    }


}
 