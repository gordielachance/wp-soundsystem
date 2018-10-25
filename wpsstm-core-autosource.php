<?php

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