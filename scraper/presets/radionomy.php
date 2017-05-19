<?php
class WP_SoundSytem_Playlist_Radionomy_Scraper extends WP_SoundSytem_Live_Playlist_Preset{

    var $preset_slug = 'radionomy';
    
    var $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~';
    var $redirect_url = 'http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%radionomy-id%';

    var $variables = array(
        'radionomy-slug' => null,
        'radionomy-id' => null
    );

    var $options_default = array(
        'selectors' => array(
            'tracks'            => array('path'=>'div.titre'),
            'track_artist'      => array('path'=>'table td','regex'=>'^(.*?)(?:<br ?/?>)'),
            'track_title'       => array('path'=>'table td i'),
            'track_image'       => array('path'=>'img','attr'=>'src')
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Radionomy Station','wpsstm');
    }
    
    protected function get_request_url(){

        //set station ID
        if ( $station_id = $this->get_station_id() ){
            $this->set_variable_value('radionomy-id',$station_id);
        }
        
        return parent::get_request_url();

    }

    function get_station_id(){
        
        $slug = $this->get_variable_value('radionomy-slug');
        if (!$slug) return false;

        $transient_name = 'wpsstm-radionomy-' . $slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$slug);
            $response = wp_remote_get( $station_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );

            libxml_use_internal_errors(true);

            //QueryPath
            try{
                $title = htmlqp( $content, 'head meta[property="og:title"]', WP_SoundSytem_Remote_Tracklist::$querypath_options )->attr('content');
                if ($title) $this->radionomy_title = $title;
            }catch(Exception $e){
            }

            //QueryPath
            try{
                $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WP_SoundSytem_Remote_Tracklist::$querypath_options )->attr('content');
            }catch(Exception $e){
                return false;
            }

            libxml_clear_errors();

            $image_file = basename($imagepath);

            $pattern = '~^([^.]+)~';
            preg_match($pattern, $image_file, $matches);
            
            if ( isset($matches[1]) ){
                $station_id = $matches[1];
                set_transient( $transient_name, $station_id, 1 * DAY_IN_SECONDS );
            }

        }
        
        return $station_id;

    }
    
    function get_tracklist_title(){
        if ( !$slug = $this->get_variable_value('radionomy-slug') ) return;
        return sprintf(__('Radionomy : %s','wppstm'),$slug);
    }

}