<?php

class WP_SoundSystem_URL_Preset extends WP_SoundSystem_Remote_Tracklist{
    var $preset_slug =      'default';
    var $preset_name =      null;
    var $preset_url =       null;
    var $preset_desc =      null;

    public function __construct($post_id = null){
        parent::__construct($post_id);
    }

    /*
    Checks that the preset can load the tracklist URL
    */
    protected function can_handle_url(){//TOFIXGGG should be abstract method
        return false;
    }


}
 