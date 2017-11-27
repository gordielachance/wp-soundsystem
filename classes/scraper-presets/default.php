<?php

class WP_SoundSystem_URL_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      'default';
    var $preset_name =      null;
    var $preset_url =       null;
    var $preset_desc =      null;

    static $wizard_suggest =   true; //suggest or not this preset in the wizard

    public function __construct($post_id = null){
        parent::__construct($post_id);
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
 