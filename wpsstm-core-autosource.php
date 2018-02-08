<?php

class WPSSTM_Autosource_Provider{
    /*
    Default source object when autosourcing
    */
    protected static function get_auto_source_obj(WPSSTM_Track $track){
        $source = new WPSSTM_Source();
        $source->is_community = true;
        $source->track_id = $track->post_id;
        return $source;
    }
}

class WPSSTM_Core_Autosource{
    
    function __construct(){
        if ( ( wpsstm()->get_options('autosource') == 'on' ) && (WPSSTM_Core_Sources::can_autosource() === true) ){
            $this->include_autosource_providers();
        }
    }
    /*
    Register scraper presets.
    */
    private function include_autosource_providers(){
        
        $presets = array();

        $presets_path = trailingslashit( wpsstm()->plugin_dir . 'classes/autosource-providers' );

        //get all files in /presets directory
        $preset_files = glob( $presets_path . '*.php' ); 

        foreach ($preset_files as $file) {
            require_once($file);
        }
    }
    
    //TOFIXKKK below
    
    /*
    Source have one word of the banned words list in their titles (eg 'cover').
    */
    
    public static function source_has_banned_word(WPSSTM_Source $source,WPSSTM_Track $track){

        $ban_words = wpsstm()->get_options('autosource_filter_ban_words');

        foreach((array)$ban_words as $word){
            
            //this track HAS the word in its title; (the cover IS a cover), abord
            $ignore_this_word = stripos($track->title, $word);//case insensitive
            if ( $ignore_this_word ) continue;
            
            //check source for the word
            $source_has_word = stripos($source->title, $word);//case insensitive
            if ( !$source_has_word ) continue;
            
            wpsstm()->debug_log( json_encode( array('source_title'=>$source->title,'word'=>$word),JSON_UNESCAPED_UNICODE ), "WPSSTM_Source::source_has_banned_word()");
            
            return true;
        }

        return false;
    }
    
    /*
    The track artist is not contained in the source title
    https://stackoverflow.com/questions/44791191/how-to-use-similar-text-in-a-difficult-context
    */
    
    public static function source_lacks_track_artist(WPSSTM_Source $source,WPSSTM_Track $track){

        /*TO FIX check if it works when artist has special characters like / or &
        What if the artist is written a little differently ?
        We should compare text somehow here and accept a certain percent match.
        */

        //sanitize data so it is easier to compare
        $source_sanitized = sanitize_title($source->title);
        $artist_sanitized = sanitize_title($track->artist);

        if (strpos($source_sanitized, $artist_sanitized) === false) {
            wpsstm()->debug_log( json_encode( array('artist'=>$track->artist,'artist_sanitized'=>$artist_sanitized,'title'=>$track->title,'source_title'=>$source->title,'source_title_sanitized'=>$source_sanitized),JSON_UNESCAPED_UNICODE ), "WPSSTM_Source::source_lacks_track_artist()");
            return true;
        }

        return false;

    }
    
    /*
    $has_banned_word = $lacks_artist = false;

    if ( wpsstm()->get_options('autosource_filter_ban_words') == 'on' ){
        $has_banned_word = $this->source_has_banned_word();
    }

    if ( wpsstm()->get_options('autosource_filter_requires_artist') == 'on' ){
        $lacks_artist = $this->source_lacks_track_artist();
    }

    //this is not a good source.  Set it as pending.
    if ( $has_banned_word || $lacks_artist ){
        $args['post_status'] = 'pending';
    }
    */
    
}