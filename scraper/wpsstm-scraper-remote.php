<?php

class WP_SoundSytem_Playlist_Scraper_Datas{
    
    //preset infos
    var $slug = 'default';
    var $name = null;
    var $description = null;

    //input
    public $options = array();
    public $url = null;
    
    //url stuff
    var $pattern = null; //pattern used to check if the scraper URL matches the preset.
    var $variables = array(); //list of variables that matches the regex groups from $pattern
    var $redirect_url = null; //if needed, a redirect URL.  Can use variables extracted from the pattern using the %variable% format.

    //response
    public $response = null;
    public $response_type = null;
    public $body_node = null;
    public $track_nodes = array();
    public $tracks = array();
    
    public $notices = array();

    //request
    static $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'auto',
        'convert_to_encoding'       => 'ISO-8859-1'
    );
    
    var $remote_get_options = array(
        'User-Agent'        => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML => like Gecko) Iron/31.0.1700.0 Chrome/31.0.1700.0'
    );
    
    public function __construct(){
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/autoload.php');
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/class-array2xml.php');
    }
    
    public function init($url,$options){
        $this->url = $url;
        if ($options) $this->options = array_replace_recursive($options, $this->options);
        
        //populate variables from URL
        if ($this->pattern){
            
            preg_match($this->pattern, $this->url, $url_matches);
            if ( $url_matches ){
                
                array_shift($url_matches); //remove first item (full match)
                $this->populate_variable_values($url_matches);
            }
        }
    }
    
    /**
    If $url_matches is empty, it means that the feed url does not match the pattern.
    **/
    
    public function can_load_tracklist_url($url){

        if (!$this->pattern) return true;
        
        //wpsstm()->debug_log(array('slug'=>$this->slug,'url'=>$url,'pattern'=>$this->pattern),"can_load_tracklist_url()"); 

        preg_match($this->pattern, $url, $url_matches);

        return (bool)$url_matches;
    }
    
    /**
    Fill the preset $variables.
    The array keys from the preset $variables and the input $values_arr have to match.
    **/

    protected function populate_variable_values($values_arr){
        
        $key = 0;

        foreach((array)$this->variables as $variable_slug=>$variable){
            $value = ( isset($values_arr[$key]) ) ? $values_arr[$key] : null;
            
            if ($value){
                $this->set_variable_value($variable_slug,$value);
            }
            
            $key++;
        }

    }

    protected function set_variable_value($slug,$value='null'){
        $this->variables[$slug] = $value;
    }
    
    public function get_variable_value($slug){

        foreach($this->variables as $variable_slug => $variable){
            
            if ( $variable_slug == $slug ){
                return $variable;
            }
        }

    }
    
    /*
    Update a string and replace all the %variable-key% parts of it with the value of that variable if it exists.
    */
    
    public function variables_fill_string($str){

        foreach($this->variables as $variable_slug => $variable_value){
            $pattern = '%' . $variable_slug . '%';
            $value = $variable_value;
            
            if ($value) {
                $str = str_replace($pattern,$value,$str);
            }
        }

        return $str;
    }
    
    public function get_tracks(){
        
        //url
        $url = $this->redirect_url = $this->get_remote_url();
        if ( is_wp_error($url) ) return $url;
        $this->url = $url;

        //response
        $response = $this->get_remote_content($url);
        if ( is_wp_error($response) ) return $response;
        $this->response = $response;
        
        //response type
        $response_type = $this->get_response_type($this->response);
        if ( is_wp_error($response_type) ) return $response_type;
        $this->response_type = $response_type;

        //response body
        $content = wp_remote_retrieve_body( $this->response ); 
        if ( is_wp_error($content) ) return $content;
        
        $body_node = $this->get_body_node($content);
        if ( is_wp_error($body_node) ) return $body_node;
        $this->body_node = $body_node;

        //tracks HTML
        $track_nodes = $this->get_track_nodes($this->body_node);
        if ( is_wp_error($track_nodes) ) return $track_nodes;
        $this->track_nodes = $track_nodes;
        
        //tracks
        $tracks = $this->get_tracks_array($track_nodes);
        if ( is_wp_error($tracks) ) return $tracks;
        $this->tracks = $tracks;
        
        return $this->tracks;
    }
    
    public function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    
    protected function get_remote_url(){
        
        if ($this->redirect_url){
            $this->redirect_url = $this->variables_fill_string($this->redirect_url);
            return $this->redirect_url;
        }else{
            return $this->url;
        }

    }
    
    protected function get_remote_content($url){

        $error = $remote_body = $source_content = null;

        $remote_args = apply_filters('spiff_get_response_args',$this->remote_get_options,$url );
        $response = wp_remote_get( $url, $remote_args );

        if ( !is_wp_error($response) ){

            $response_code = wp_remote_retrieve_response_code( $response );

            if ($response_code && $response_code != 200){
                $response_message = wp_remote_retrieve_response_message( $response );
                return new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
            }else{
                return $response;
            }
            
        }else{
            return $response;
        }

    }

    protected function get_response_type($response){

        $type = wp_remote_retrieve_header( $response, 'content-type' );

        //is JSON
        if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
            $type = 'application/json';
        }

        //remove charset if any
        $split = explode(';',$type);

        if ( !isset($split[0]) ){
            return new WP_Error( 'response_type', __('No response type found','wpsstm') );
        }
        
        return $split[0];

    }
    
    protected function get_body_node($content){

        $result = null;
        
        libxml_use_internal_errors(true);

        switch ($this->response_type){
            
            case 'application/xspf+xml':
            case 'text/xspf+xml':
            case 'application/xml':
            case 'text/xml':
                
                //check for XSPF
                if ($this->response_type=='application/xml' || $this->response_type=='text/xml'){
                    
                    $is_xspf = false;
                    
                    //QueryPath
                    try{
                        if ( qp( $content, 'playlist trackList track', self::$querypath_options )->length > 0 ){
                            $is_xspf = true;
                        }
                    }catch(Exception $e){}
                    
                    if ($is_xspf){
                        $this->response_type = 'text/xspf+xml';
                    }
                }

                $xml = simplexml_load_string($content);
                
                //maybe libxml will output error but will work; do not abord here.
                $xml_errors = libxml_get_errors();
                foreach( $xml_errors as $xml_error_obj ) {
                    $this->add_notice( 'wizard-header-advanced', 'xml_error', sprintf(__('simplexml Error [%1$s] : %2$s','wpsstm'),$xml_error_obj->code,$xml_error_obj->message), true );
      
                }

                //QueryPath
                try{
                    $result = qp( $xml, null, self::$querypath_options );
                }catch(Exception $e){
                    return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','wpsstm'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'application/json':
                
                $xml = null;

                try{
                    $data = json_decode($content, true);
                    $dom = Array2XML::createXML($data,'root','element');
                    $xml = $dom->saveXML($dom);
                    

                }catch(Exception $e){
                    return WP_Error( 'XML2Array', sprintf(__('XML2Array Error [%1$s] : %2$s','wpsstm'),$e->getCode(),$e->getMessage()) );
                }
                
                if ($xml){

                    $this->add_notice( 'wizard-header-advanced', 'json2xml', __("The json input has been converted to XML.",'wpsstm') );
 
                    $this->response_type = 'text/xml';
                    return $this->get_body_node($xml);
                }

            break;

            case 'text/html': 

                //QueryPath
                try{
                    $result = htmlqp( $content, null, self::$querypath_options );
                }catch(Exception $e){
                    return WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;
        
            //TO FIX seems to put a wrapper around our content + bad content type
        
            default: //text/plain
                //QueryPath
                try{
                    $result = qp( $content, 'body', self::$querypath_options );
                }catch(Exception $e){
                    return WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }
                
            break;
        
        }
        
        libxml_clear_errors();

        if ( (!$result || ($result->length == 0)) ){
            return new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }

        return $result;

    }
    
    protected function get_track_nodes($body_node){
        
        //update options for XSPF
        if ($this->response_type == 'text/xspf+xml'){
            $xspf_options = array(
                'selectors' => array(
                    'tracks'            => array('path'=>'trackList track'),
                    'track_artist'      => array('path'=>'creator'),
                    'track_title'       => array('path'=>'title'),
                    'track_album'       => array('path'=>'album'),
                    'track_location'    => array('path'=>'location'),
                    'track_image'       => array('path'=>'image')
                )
            );
            $this->options = array_replace_recursive($this->options, $xspf_options);
        }

        $selector = $this->get_options( array('selectors','tracks','path') );
        if (!$selector) return new WP_Error( 'no_track_selector', __('Required tracks selector is missing','spiff') );

        //QueryPath
        try{
            $track_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }
        
        if ( $track_nodes->length == 0 ){
            return new WP_Error( 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','spiff') );
        }

        return $track_nodes;

    }

    protected function get_tracks_array($track_nodes){

        $selector_artist = $this->get_options( array('selectors','track_artist') );
        if (!$selector_artist) return new WP_Error( 'no_track_selector', __('Required track artist selector is missing','xspf') );
        
        $selector_title = $this->get_options( array('selectors','track_title') );
        if (!$selector_title) return new WP_Error( 'no_track_selector', __('Required track title selector is missing','xspf') );

        $tracks_arr = array();
        
        foreach($track_nodes as $key=>$single_track_node) {

            $args = array(
                'artist'    => $this->get_track_node_content($single_track_node,'artist'),
                'title'     => $this->get_track_node_content($single_track_node,'title'),
                'album'     => $this->get_track_node_content($single_track_node,'album'),
                'location'  => $this->get_track_node_content($single_track_node,'location'),
                'image'     => $this->get_track_node_content($single_track_node,'image')
            );
            
            $tracks_arr[] = $args;

        }
        
        //sort
        if ($this->get_options('tracks_order') == 'asc'){
            $tracks_arr = array_reverse($tracks_arr);
        }

        return $tracks_arr;

    }

    protected function get_track_node_content($track_node,$slug){
        
        $node = $track_node;
        $result = null;
        $pattern = null;
        $string = null;
        
        $selector_slug  = 'track_'.$slug;
        $selector_css   = $this->get_options(array('selectors',$selector_slug,'path'));
        $selector_regex = $this->get_options(array('selectors',$selector_slug,'regex'));

        //abord
        if ( !$selector_css && !$selector_regex ){
            return false;
        }

        //QueryPath
        try{
            if ($selector_css) $node = $track_node->find($selector_css);
            $string = $node->innerHTML();
            
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }
        
        if (!$string = trim($string)) return;

        if( ($slug == 'image' ) || ($slug == 'location' ) ){

            if ($url = $node->attr('src')){ //is an image or audio tag
                $string = $url;
            }elseif ($url = $node->attr('href')){ //is a link
                $string = $url;
            }

            if (filter_var((string)$string, FILTER_VALIDATE_URL) === false) {
                $string = '';
            }
            
        }

        //CDATA fix
        $string = $this->sanitize_cdata_string($string);

        //regex pattern
        if ( $selector_regex ){
            $pattern = $selector_regex;
        }

        if(!$pattern) {
            $result = $string;
        }else{

            $pattern = sprintf('~%s~m',$pattern);
            preg_match($pattern, $string, $matches);
            
            if (isset($matches[1])){
                $result = strip_tags($matches[1]);
            }
                
        }
        
        return $result;
    }
    
    protected function sanitize_cdata_string($string){
        $string = str_replace("//<![CDATA[","",$string);
        $string = str_replace("//]]>","",$string);

        $string = str_replace("<![CDATA[","",$string);
        $string = str_replace("]]>","",$string);

        return trim($string);
    }

    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    We'll get those back in WP_SoundSytem_Playlist_Scraper.
    */
    function add_notice($slug,$code,$message,$error = false){
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSytem_Playlist_Scraper_Datas notice]: ' . $message ); 
        
        $this->notices[] = array(
            'slug'      => $slug,
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );
    }

}