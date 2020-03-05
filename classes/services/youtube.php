<?php

class WPSSTM_Youtube{
    
    static $mimetype = 'video/youtube';
    
    function __construct(){
        add_filter('wpsstm_get_link_mimetype',array(__class__,'get_youtube_link_type'),10,2);
    }

    public static function get_youtube_link_type($type,WPSSTM_Track_Link $link){
        if ( self::get_youtube_id($link->url) ){
            $type = self::$mimetype;
        }
        return $type;
    }
    public static function get_youtube_id($url){
        //youtube
        $pattern = '~http(?:s?)://(?:www.)?youtu(?:be.com/watch\?v=|.be/)([\w\-\_]*)(&(amp;)?[\w\?=]*)?~i';
        preg_match($pattern, $url, $url_matches);
        
        if ( !isset($url_matches[1]) ) return;
        
        return $url_matches[1];
    }
    public static function get_youtube_permalink($id){
        return sprintf('https://youtube.com/watch?v=%s',$id);
    }
}

function wpsstm_youtube_init(){
    new WPSSTM_Youtube();
}

add_action('wpsstm_load_services','wpsstm_youtube_init');