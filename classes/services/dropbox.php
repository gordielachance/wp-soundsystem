<?php

class WPSSTM_Dropbox{
    function __construct(){
        add_filter('wpsstm_live_tracklist_url',array(__class__,'wpsstm_filter_dropbox_url'), 5); //priority before presets
    }
    static function wpsstm_filter_dropbox_url($url){

        $domain = wpsstm_get_url_domain($url );

        //dropbox : convert to raw link
        if ($domain=='dropbox.com'){
            $url_no_args = strtok($url, '?');
            $url = add_query_arg(array('raw'=>1),$url_no_args); //http://stackoverflow.com/a/11846251/782013
        }

        return $url;
    }
}

function wpsstm_dropbox(){
    new WPSSTM_Dropbox();
}

add_action('wpsstm_init','wpsstm_dropbox');