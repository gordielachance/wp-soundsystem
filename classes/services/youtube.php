<?php

class WPSSTM_Youtube{
    
    static $mimetype = 'video/youtube';
    
    function __construct(){
        add_filter('wpsstm_get_source_mimetype',array(__class__,'get_youtube_source_type'),10,2);
    }

    public static function get_youtube_source_type($type,WPSSTM_Source $source){
        if ( self::get_youtube_id($source->permalink_url) ){
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

add_action('wpsstm_init','wpsstm_youtube_init');