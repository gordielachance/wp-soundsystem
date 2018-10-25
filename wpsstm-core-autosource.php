<?php

class WPSSTM_Core_Autosource{
    public static $max_autosource = 5;

    function __construct(){
        add_action( 'wp', array($this,'debug_autosource'));
    }

    /*
    ?debug_autosource=XXX
    */
    function debug_autosource(){
        if ( is_admin() ) return;
        
        //TOUFIX TOREMOVE
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
        //TOUFIX useful ?
        
        if ($track->post_id && !$track->duration && !$track->mbid){
            if ( $mbid = WPSSTM_Core_MusicBrainz::auto_mbid($track->post_id) ){
                //repopulate track to load the new datas
                $track = new WPSSTM_Track($track->post_id);
            }
        }
        
        $source_engine = new WPSSTM_Tuneefy_Source_Digger($track);
        $autosources = $source_engine->sources;
        if ( is_wp_error($autosources) ) return $autosources;

        //remove some bad sources
        foreach((array)$autosources as $key=>$source){

            //cannot play this source, skip it.
            if ( !$source->get_source_mimetype() ){

                $source->source_log(json_encode(
                    array(
                        'track'=>sprintf('%s - %s',$track->artist,$track->title),
                        'source'=>array('title'=>$source->title,'url'=>$source->permalink_url),
                        'error'=>__('Source excluded because it has no mime type','wpsstm'))
                    )
                );
                unset($autosources[$key]);
                
            }

        }
        
        //limit autosource results
        $autosources = array_slice($autosources, 0, self::$max_autosource);
        
        return apply_filters('find_sources_for_track',$autosources);

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
        
        $autosources = self::find_sources_for_track($track);
        
        if ( is_wp_error($autosources) ) return $autosources;
        if (!$autosources ) return $autosources;


        //insert sources
        foreach($autosources as $source){

            $source_id = $source->save_unique_source();

            if ( is_wp_error($source_id) ){
                $code = $source_id->get_error_code();
                $error_msg = $source_id->get_error_message($code);
                $track->track_log( $error_msg,"WPSSTM_Core_Autosource::store_sources_for_track - error while saving source");
                continue;
            }
        }

        //reload sources
        $track->populate_sources();

        return $autosources;
        
    }

}

/*
Engine used to discover sources
*/
class WPSSTM_Source_Digger{
    var $track;
    var $sources = array();
    
    function __construct(WPSSTM_Track $track){
        $this->track = $track;
    }
    
    function is_valid_track(){
        if ( !$this->track->artist ){
            return new WP_Error( 'wpsstm_track_no_artist', __('Autosourcing requires track artist.','wpsstm') );
        }

        if ( !$this->track->title ){
            return new WP_Error( 'wpsstm_track_no_title', __('Autosourcing requires track title.','wpsstm') );
        }
        return true;
    }
}

class WPSSTM_Tuneefy_Source_Digger extends WPSSTM_Source_Digger{
    
    var $tuneefy_providers = array('youtube','soundcloud');
    
    function __construct(WPSSTM_Track $track){
        parent::__construct($track);        
        $this->sources = $this->find_sources();
    }
    
    static function can_tuneefy(){
        if ( !wpsstm()->get_options('tuneefy_client_id') ){
            return new WP_Error( 'wpsstm_tuneefy_auth', __('Required Tuneefy client id missing.','wpsstm') );
        }
        if ( !wpsstm()->get_options('tuneefy_client_secret') ){
            return new WP_Error( 'wpsstm_tuneefy_auth', __('Required Tuneefy client secret missing.','wpsstm') );
        }
        return true;
    }
    
    static function get_tuneefy_token(){
        
        $can_tuneefy = self::can_tuneefy();
        if( is_wp_error($can_tuneefy) ) return $can_tuneefy;
        
        $transient_name = 'tuneefy_token';
        
        $client_id = wpsstm()->get_options('tuneefy_client_id');
        $client_secret = wpsstm()->get_options('tuneefy_client_secret');

        if ( false === ( $token = get_transient($transient_name ) ) ) {
            
            $args = array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                ),
                'body' => sprintf('client_id=%s&client_secret=%s&grant_type=client_credentials',$client_id,$client_secret),
            );
            $response = wp_remote_post('https://data.tuneefy.com/v2/auth/token', $args );
            $body = wp_remote_retrieve_body($response);

            if ( is_wp_error($body) ) return $body;
            $api_response = json_decode( $body, true );
            
            wpsstm()->debug_log( json_encode($api_response), "WPSSTM_Core_Sources::get_tuneefy_token"); 

            if ( isset($api_response['access_token']) &&  isset($api_response['expires_in']) ) {
                $token = $api_response['access_token'];
                $time = $api_response['expires_in']; //TO FIX TO CHECK is seconds ?
                set_transient( $transient_name, $token, $time );
            }elseif( isset($api_response['error_description']) ){
                return new WP_Error( 'wpsstm_tuneefy_auth', sprintf(__('Unable to get Tuneefy Token: %s','wpsstm'),$data['error_description']) );
            }else{
                return new WP_Error( 'wpsstm_tuneefy_auth', __('Unable to get Tuneefy Token.','wpsstm') );
            }
            
        }
        
        return $token;
        
    }
    
    private function find_sources(){

        $is_valid_track = $this->is_valid_track();
        if ( is_wp_error($is_valid_track) ) return $is_valid_track;

        $auto_sources = array();

        $tuneefy_args = array(
            'q' =>          urlencode($this->track->artist . ' ' . $this->track->title),
            'mode' =>       'lazy',
            'aggressive' => 'true', //merge tracks (ignore album)
            'include' =>    implode(',',$this->tuneefy_providers),
            'limit' =>      5
        );

        $api = self::tuneefy_api_aggregate('track',$tuneefy_args);
        if ( is_wp_error($api) ) return $api;
        $items = wpsstm_get_array_value(array('results'),$api);
        
        if ($items){
            //wpsstm()->debug_log( json_encode($items), "get_sources_auto");

            //TO FIX have a more consistent extraction of datas ?
            foreach( (array)$items as $item ){

                $links_by_providers =   wpsstm_get_array_value(array('musical_entity','links'),$item);
                $first_provider =       reset($links_by_providers);
                $first_link =           reset($first_provider);

                $source = new WPSSTM_Source();
                $source->track_id = $this->track->post_id;
                $source->permalink_url = $first_link;
                $source->title = wpsstm_get_array_value(array('musical_entity','title'),$item);

                $auto_sources[] = $source;

            }
        }
        


        //allow plugins to filter this
        return $auto_sources;
        
    }
    
    /*
    Get track/album informations using Tuneefy API
    See https://data.tuneefy.com/#search-aggregate-get
    */
    
    static function tuneefy_api_aggregate($type,$url_args){
        
        $error = null;
        $url = null;
        $request_args = null;
        $api_response = null;
        
        //TO FIX use a transient to store results for a certain time ?

        $token = self::get_tuneefy_token();
        
        if ( is_wp_error($token) ){
            $error = $token;
        }else{
            $request_args = array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s',$token),
                ),
            );


            $url = sprintf('https://data.tuneefy.com/v2/aggregate/%s',$type);
            $url = add_query_arg($url_args,$url);


            wpsstm()->debug_log( json_encode(array('url'=>$url,'request_args'=>$request_args)), "WPSSTM_Core_Sources::tuneefy_api_aggregate"); 

            $response = wp_remote_get($url,$request_args);
            $body = wp_remote_retrieve_body($response);

            if ( is_wp_error($body) ){
                $error = $body;
            }else{

                $api_response = json_decode( $body, true );

                if( !empty($api_response['errors']) ){
                    $errors = $api_response['errors'];
                    $error = new WP_Error( 'wpsstm_tuneefy_aggregate', sprintf( __('Unable to aggregate using Tuneefy : %s','wpsstm'),json_encode($errors) ) );
                }
            }
        }

        if($error){
            wpsstm()->debug_log( json_encode(array('error'=>$error->get_error_message(),'url'=>$url,'request_args'=>$request_args)), "WPSSTM_Core_Sources::tuneefy_api_aggregate"); 
            return $error;
        }

        return $api_response;
    }
    
}

/*
*/