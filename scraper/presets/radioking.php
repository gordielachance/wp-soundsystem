<?php
class WP_SoundSytem_Preset_RadioKing_Api extends WP_SoundSytem_Live_Playlist_Preset{

    var $preset_slug =      'radioking';
    var $preset_url =       'https://www.radioking.com';
    
    var $pattern =          '~^https?://(?:.*\.)?radioking.com/radio/([^/]+).*?$~i';
    var $redirect_url =     'https://www.radioking.com/api/radio/%radioking-id%/track/ckoi?limit=20';
    var $variables =        array(
        'radioking-slug'        => null,
        'radioking-id'          => null
    );

    var $options_default =  array(
        'datas_cache_min'   => 15,
        'selectors' => array(
            'tracks'            => array('path'=>'root > data'),
            'track_artist'      => array('path'=>'artist'),
            'track_album'       => array('path'=>'album'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
        )
    );
    
    var $station_data =     null;

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Radioking Stations','wpsstm');
    }
    
    function get_station_data(){
        if ( !$slug = $this->get_variable_value('radioking-slug') ) return;
        
        $transient_name = 'wpsstm-radioking-' . $slug . '-data';

        if ( false === ( $station_data = get_transient($transient_name ) ) ) {
            $response = wp_remote_get( sprintf('https://www.radioking.com/api/radio/slug/%s',$slug) );
            $json = wp_remote_retrieve_body($response);
            if ( is_wp_error($json) ) return $json;
            $api = json_decode($json,true);
            if ( $station_data = wpsstm_get_array_value(array('data'), $api) ){
                set_transient( $transient_name, $station_data, 1 * DAY_IN_SECONDS );
            }
        }
        
        return $station_data;
        
    }
    
    protected function get_request_url(){
        
        $this->station_data = $this->get_station_data();

        //set station ID
        if ( $station_id = wpsstm_get_array_value(array('idradio'), $this->station_data) ){
            $this->set_variable_value('radioking-id',$station_id);
        }
        
        return parent::get_request_url();

    }
    
    function get_tracklist_title(){
        return wpsstm_get_array_value(array('name'), $this->station_data);
    }
    
    protected function get_track_image($track_node){
        $selectors = $this->get_options(array('selectors','track_image'));
        
        if ( $image_id = $this->get_track_node_content($track_node,$selectors) ){
           $image = sprintf('https://www.radioking.com/api/track/cover/%s?width=55&height=55',$image_id);
        }
        
        
        
        return $image;
    }

}