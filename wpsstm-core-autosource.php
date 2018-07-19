<?php

class WPSSTM_Core_Autosource{
    
    static private $providers = array();
    public static $max_autosource = 5;

    function __construct(){
        //hook autosource providers
        self::$providers = apply_filters('wpsstm_get_autosource_providers',self::$providers);
        add_action( 'wp', array($this,'debug_autosource'));
    }

    /*
    ?debug_autosource=XXX
    */
    function debug_autosource(){
        if ( is_admin() ) return;
        $test_track_id = isset($_GET['debug_autosource']) ? $_GET['debug_autosource'] : null;
        if (get_post_type($test_track_id) != wpsstm()->post_type_track ) return;
        if (!$test_track_id) return;
        $track = new WPSSTM_Track($test_track_id);
        //$sources = $this->find_sources_for_track($track);
        $sources = WPSSTM_Core_Autosource::store_sources_for_track($track);
        print_r(json_encode($sources));die();
    }

    /*
    Retrieve autosources for a track and populate each source weight
    */
    
    private static function find_sources_for_track(WPSSTM_Track $track){

        if ( wpsstm()->get_options('autosource') != 'on' ){
            return new WP_Error( 'wpsstm_autosource_disabled', __("Track autosource is disabled.",'wpsstm') );
        }
        
        $can_autosource = WPSSTM_Core_Sources::can_autosource();
        if ( $can_autosource !== true ) return $can_autosource;

        if ( !$track->artist ){
            return new WP_Error( 'wpsstm_track_no_artist', __('Autosourcing requires track artist.','wpsstm') );
        }
        
        if ( !$track->title ){
            return new WP_Error( 'wpsstm_track_no_title', __('Autosourcing requires track title.','wpsstm') );
        }
        
        //if track does not have a duration, try to find it using MusicBrainz.
        //Being able to compare track & source duration will improve the way we compute the source weight.
        
        if ($track->post_id && !$track->duration && !$track->mbid){
            if ( $mbid = WPSSTM_Core_MusicBrainz::auto_mbid($track->post_id) ){
                //repopulate track to load the new datas
                $track = new WPSSTM_Track($track->post_id);
            }
        }
        
        $sources = array();
        foreach((array)self::$providers as $slug=>$provider){
            
            $provider->track = $track;
            $provider->populate_track_autosources();
            
            if ( is_wp_error($provider->sources) ){
                wpsstm()->debug_log($sources->get_error_message(),'WPSSTM_Core_Autosource::find_sources_for_track - unable to populate provider sources');
                continue;
            }
                
            foreach((array)$provider->sources as $source){
                //compute source weight
                $provider->populate_weight($source);
            }

            $sources = array_merge($sources,(array)$provider->sources);

        }

        return $sources;

    }

    public static function store_sources_for_track(WPSSTM_Track $track){
        
        //track does not exists yet, create it
        if ( !$track->post_id ){

            $tracks_args = array( //as community tracks
                'post_author'   => wpsstm()->get_options('community_user_id'),
            );
            
            $success = $track->save_track($tracks_args);
            if ( is_wp_error($success) ) return $success;
            
        }
        
        //save time autosourced (we need post ID here)
        $now = current_time('timestamp');
        update_post_meta( $track->post_id, WPSSTM_Core_Tracks::$autosource_time_metakey, $now );
        
        $sources = self::find_sources_for_track($track);
        if ( is_wp_error($sources) ) return $sources;
        
        $sources_auto = array();
        
        //remove some bad sources
        foreach((array)$sources as $source){
            
            $errors = array();

            //cannot play this source, skip it.
            if ( !$source->get_source_mimetype() ){
                $errors[] = new WP_Error('autosource_no_mimetype','Source excluded because it has no mime type');
            }

            //negative tags
            $negative_tags = array_intersect(WPSSTM_Track_Autosource::$negative_tags,$source->tags);
            if ($negative_tags){
                $errors[] = new WP_Error('autosource_negative_tags','Source excluded because it has negative tags',$negative_tags);
            }
            
            if($errors){
                wpsstm()->debug_log(json_encode(
                    array(
                        'track'=>sprintf('%s - %s',$track->artist,$track->title),
                        'source'=>array('title'=>$source->title,'url'=>$source->permalink_url),
                        'errors'=>$errors)
                    ),
                    "WPSSTM_Core_Autosource::store_sources_for_track - source excluded");
                continue;
            }
            
            $sources_auto[] = $source;
        }

        $sources_auto = self::sort_sources_by_weight($sources_auto);
        $sources_auto = array_values($sources_auto); //reindex

        if ($sources_auto){
            
            //limit autosource results
            $sources_auto = array_slice($sources_auto, 0, self::$max_autosource);

            //insert sources
            foreach($sources_auto as $source){

                $source_id = $source->save_unique_source();

                if ( is_wp_error($source_id) ){
                    $code = $source_id->get_error_code();
                    $error_msg = $source_id->get_error_message($code);
                    wpsstm()->debug_log( $error_msg, "WPSSTM_Core_Autosource::store_sources_for_track - error while saving source");
                    continue;
                }
            }

            //reload sources
            $track->populate_sources();
        }

        return $sources_auto;
        
    }

    public static function sort_sources_by_weight($sources){
        function sort_weight($a, $b) {
            if($a->weight == $b->weight){ return 0 ; }
            return ($a->weight > $b->weight) ? -1 : 1;
        }
        usort($sources, 'sort_weight');
        return $sources;
    }

}

/*
*/