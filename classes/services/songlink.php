<?php

class WPSSTM_SongLink{
    function __construct(){
        
    }
    static function get_track_autosources($track){
        global $wpsstm_spotify;
        
        $valid = $track->validate_track();
        if ( is_wp_error( $valid ) ) return $valid;
        
        $auto_sources = array();
        
        if (!$track->spotify_id){
            $spotify_id = $wpsstm_spotify->auto_spotify_id( $track->post_id );
            if ( is_wp_error($spotify_id) ) return $spotify_id;
            $track->spotify_id = $spotify_id;
        }
        
        if (!$track->spotify_id){
            return new WP_Error('missing_spotify_id',__('Autosourcing requires a Spotify Track ID','wpsstm'));
        }

        $sources = array();
        $url = sprintf('https://song.link/s/%s',$track->spotify_id);
        $track->track_log($url, "Getting SongLink page..." ); 

        $response = wp_remote_get($url);
        $body = wp_remote_retrieve_body($response);
        
        if (!$body){
            $body = new WP_Error('empty_songlink_response',__('Empty SongLink response','wpsstm'));
        }
        
        if ( is_wp_error($body) ) return $body;
        
        /*
        parse HTML elements
        */
        
        $dom = new DOMDocument();
        
        $internalErrors = libxml_use_internal_errors(true);//set error level
        $dom->loadHTML($body);
        libxml_use_internal_errors($internalErrors);//restore error level
        
        $xpath = new DOMXPath($dom);
        $listen_link_els = $xpath->query("//a[starts-with(@data-test, 'click:')]");
        
        foreach ($listen_link_els as $link_el) {

            $provider_attr = $link_el->getAttribute('data-test');
            $provider = preg_replace('/^' . preg_quote('click:', '/') . '/', '', $provider_attr); //remove 'click:' prefix
            
            $url = $link_el->getAttribute('href');
            $title = $link_el->getAttribute('aria-label');
            $title = preg_replace('/^' . preg_quote('Listen to ', '/') . '/', '', $title); //remove 'Listen to' prefix
            
            $source = array(
                'title' =>          $title,
                'permalink_url' =>  $url,
            );

            $sources[] = $source;
            
        }

        return $sources;
        
    }

}

function wpsstm_songlink_init(){
    new WPSSTM_SongLink();
}

add_action('wpsstm_load_services','wpsstm_songlink_init');