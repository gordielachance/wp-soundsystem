<?php

class WP_SoundSytem_Playlist_Scraper_Datas{
    var $parser;
    var $feed_url;
    var $id;
    var $transient_name_cache;

    public $response;
    public $response_type;
    public $response_body;
    public $track_nodes;
    

    public $datas_cache = null;
    public $datas_remote = null;
    public $datas = null;

    static $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'auto',
        'convert_to_encoding'       => 'ISO-8859-1'
    );
    
    var $remote_get_options = array(
        'User-Agent'        => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML => like Gecko) Iron/31.0.1700.0 Chrome/31.0.1700.0'
    );
    
    public function __construct(WP_SoundSytem_Playlist_Scraper $parser){
        $this->parser = $parser;
    }

    public function get_datas($cache_only = false){

        if ($this->datas === null){
            
            $feed_url = $this->parser->feed_url;
            if (!$feed_url) return;

            //preset redirect url
            if ( ($preset = $this->parser->preset) && isset($preset->redirect_url) ){

                $feed_url = $preset->get_redirect_url();
            }

            if ( is_wp_error($feed_url) ){
                return $feed_url;
            }

            if ( !$feed_url ){
                return new WP_Error( 'empty_feed_url', __('No feed url','wpsstm') );
            }
            
            $this->feed_url = $feed_url;
            
            
            
            $this->id = md5( $this->feed_url );
            $this->transient_name_cache = 'wpsstm_'.$this->id; //WARNING this must be 40 characters max !  md5 returns 32 chars.

            //try to get cache first
            $this->datas = $this->get_datas_cache();
            
            if ( count($this->datas['tracks']) && $this->parser->is_wizard ){
                add_settings_error( 'wizard-header-advanced', 'cache_tracks_loaded', sprintf(__('A cache entry with %1$s tracks was found (%2$s); but is ignored within the wizard.','spiff'),count($this->datas['tracks']),gmdate(DATE_ISO8601,$this->datas['time'])),'updated inline' );
            }

            if ( ( !$this->datas && (!$cache_only) ) || $this->parser->is_wizard ){

                $this->datas = $this->get_datas_remote();

                //repopulate author & title as we might change them depending of the page content
                //$this->title = $this->get_station_title();
                //$this->author = $this->get_station_author();

            }
            
        }

        return $this->datas;
        
    }

    private function get_datas_cache(){

        if ( !$cache = $this->get_cache() ) return false;

        $this->datas_cache = $cache;
        return $this->datas_cache;

    }
    
    public function get_cache(){
        if ( !$this->parser->get_options('datas_cache_min') ){
            if ( is_admin() ){
                add_settings_error( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'spiff'),'updated inline' );
            }
            return false;
        }

        if ( $cache = get_transient( $this->transient_name_cache ) ){
            wpsstm()->debug_log(array('transient'=>$this->transient_name_cache,'cache'=>json_encode($cache)),"WP_SoundSytem_Playlist_Scraper_Datas::get_cache()"); 
        }
        
        return $cache;

    }
    
    function set_cache(){

        if ( !$duration_min = $this->parser->get_options('datas_cache_min') ) return;
        
        $duration = $duration_min * MINUTE_IN_SECONDS;
        $success = set_transient( $this->transient_name_cache, $this->datas_remote, $duration );

        wpsstm()->debug_log(array('success'=>$success,'transient'=>$this->transient_name_cache,'duration_min'=>$duration_min,'cache'=>json_encode($this->datas_remote)),"WP_SoundSytem_Playlist_Scraper_Datas::set_cache()"); 
        
    }

    function delete_cache(){
        delete_transient( $this->transient_name_cache );
    }

    private function get_datas_remote(){

        $nodes = $this->populate_track_nodes(); //must be before the selectors check as requesting the response can modify get_options

        $empty_artist_selector  = ( !$this->parser->get_options('selectors','track_artist') );
        $empty_title_selector   = ( !$this->parser->get_options('selectors','track_title') );


        if ( $empty_artist_selector && !$this->parser->is_wizard ){
                return false;
        }
        
        if ( $empty_title_selector && !$this->parser->is_wizard ){
                return false;
        }

        if ( !$nodes ) return false;

        // Get all tracks
        $tracks_arr = array();
        foreach($nodes as $key=>$track_node) {

            $args = array(
                'artist'    => $this->get_track_node_content($track_node,'artist'),
                'title'     => $this->get_track_node_content($track_node,'title'),
                'album'     => $this->get_track_node_content($track_node,'album'),
                'location'  => $this->get_track_node_content($track_node,'location'),
                'image'     => $this->get_track_node_content($track_node,'image')
            );
            
            $tracks_arr[] = $args;

        }
        
        //sort
        if ($this->parser->get_options('tracks_order') == 'asc'){
            $tracks_arr = array_reverse($tracks_arr);
        }


        //lookup
        /*
        if ( ($this->parser->get_options('musicbrainz')) && ( !$this->parser->is_wizard ) ){
            foreach ($this->tracklist->tracks as $track){
                $track->musicbrainz();
            }
        }
        */
        
        //format response
        $this->datas_remote = array(
            'tracks'    => $tracks_arr,
            'time'      => current_time( 'timestamp' )
        );

        //set cache if there is none
        if ( !$this->get_datas_cache() ){
            $this->set_cache();
        }
        
        
        return $this->datas_remote;

    }
    
    private function populate_track_nodes(){

        $error = null;

        if ( !$this->response_body ){
            $this->populate_feed();
        }

        if ( $this->response_body ){

            $selector = $this->parser->get_options( array('selectors','tracks','path') );

            if ( !$selector ) return;
       
            //QueryPath
            try{

                $track_nodes = qp( $this->response_body, null, self::$querypath_options  )->find($selector);

                if ($track_nodes->length == 0){
                    $error = new WP_Error( 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','spiff') );
                }

            }catch(Exception $e){
                $error = new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
            }
     
            if ( $error ) {
                if ( is_admin() ){
                        add_settings_error( 'wizard-step-tracks_selector', 'no_track_nodes', $error->get_error_message(),'error inline' );
                }
            }else{

                if ($track_nodes->length > 0){
                    $this->track_nodes = $track_nodes;
                    return $this->track_nodes;
                }
                

            }

        }

    }
    
    private function populate_feed(){
        $error = $remote_body = $source_content = null;

        $remote_args = apply_filters('spiff_get_response_args',$this->remote_get_options,$this->feed_url );
        $response = wp_remote_get( $this->feed_url, $remote_args );

        if ( !is_wp_error($response) ){
            
            $this->response = $response;
            $this->response_type = $this->get_response_datatype($response);
            
            $response_code = wp_remote_retrieve_response_code( $response );

            if ($response_code && $response_code != 200){

                $response_message = wp_remote_retrieve_response_message( $response );
                $error = new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
                
            }else{

                $remote_body = wp_remote_retrieve_body( $response ); 

                if ( !is_wp_error($remote_body) ){

                    $source_content = $this->parse_source_body($remote_body);

                    if ( is_wp_error($source_content ) ){
                        $error = $source_content;
                    }
                    
                }

            }

        }else{
            $error = $response;
        }

        if ($error){
            if ( is_admin() ){
                $error_msg = sprintf(__('Error while trying to reach %1$s : %2$s','spiff'),'<em>'.$this->feed_url.'</em>','<strong>'.$error->get_error_message().'</strong>' );
                add_settings_error( 'wizard-header', 'no_response', $error_msg,'error inline' );
            }
        }else{
            
            $this->response_body = $source_content;

            if ( is_admin() ){
                if ( $this->feed_url != $this->parser->feed_url ){
                    add_settings_error( 'wizard-header', 'scrapped_from', sprintf(__('Scraped from : %s','wpsstm'),'<em>'.$this->feed_url.'</em>'),'updated inline' );
                }
            }
        }

    }
    
    /**
     * Get response content-type, filtered by us
     * @return type
     */
    
    function get_response_datatype($response){

        $type = wp_remote_retrieve_header( $response, 'content-type' );

        //is JSON
        if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
            $type = 'application/json';
        }

        //remove charset if any
        $split = explode(';',$type);

        return $split[0];

    }
    
    function parse_source_body($content){

        //$content = apply_filters('xspf_parse_source_body_pre',$content,$this->parser);

        $error = null;
        
        libxml_use_internal_errors(true);

        switch ($this->response_type){
            
            case 'application/xspf+xml':
            case 'text/xspf+xml':
            case 'application/xml':
            case 'text/xml':
                
                //check for XSPF
                if ($this->response_type=='application/xml' || $this->response_type=='text/xml'){
                    //QueryPath
                    //should be "trackList" instead of "tracklist", but it does not work.
                    try{
                        if ( qp( $content, 'playlist tracklist track', self::$querypath_options )->length > 0 ){
                            $this->response_type = 'text/xspf+xml';
                        }
                    }catch(Exception $e){

                    }
                }

                $xml = simplexml_load_string($content);
                
                //do not set the $error var as this would abord the process.
                //maybe libxml will output error but still have it working.
                $xml_errors = libxml_get_errors();
                foreach( $xml_errors as $xml_error_obj ) {
                    $xml_error = new WP_Error( 'simplexml', sprintf(__('simplexml Error [%1$s] : %2$s','spiff'),$xml_error_obj->code,$xml_error_obj->message) );
                    
                    if (is_admin()){
                        add_settings_error( 'wizard-header', 'simplexml', $xml_error->get_error_message(),'error inline' );
                    }
                    
                }

                //QueryPath
                try{
                    $result = qp( $xml, null, self::$querypath_options );
                }catch(Exception $e){
                    $error = new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'application/json':

                try{
                    $data = json_decode($content, true);

                    $dom = Array2XML::createXML($data,'root','element');
                    $xml = $dom->saveXML($dom);
                    $this->response_type = 'text/xml';
                    
                    if ( is_admin() ){
                        add_settings_error( 'wizard-row-feed_content_type', 'json2xml', __("The json input has been converted to XML.",'spiff'),'updated inline');
                    }

                    $result = $this->parse_source_body($xml);

                }catch(Exception $e){
                    $error = WP_Error( 'XML2Array', sprintf(__('XML2Array Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'text/html': 

                //QueryPath
                try{
                    $result = htmlqp( $content, null, self::$querypath_options );
                }catch(Exception $e){
                    $error = WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;
        
            //TO FIX seems to put a wrapper around our content + bad content type
        
            default: //text/plain
                //QueryPath
                try{
                    $result = qp( $content, 'body', self::$querypath_options );
                }catch(Exception $e){
                    $error = WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }
                
            break;
        
        }
        
        libxml_clear_errors();

        if ( !$error && (!$result || ($result->length == 0)) ){
            $error = new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }
        
        if ($error){
            if (is_admin()){
                add_settings_error( 'wizard-header', 'no_page_html', $error->get_error_message(),'error inline' );
            }
            
            return false;
        }

        return $result;
        
    }
    
    function get_track_node_content($track_node,$slug){
        
        $node = $track_node;
        $result = null;
        $pattern = null;
        $string = null;
        
        $selector_slug  = 'track_'.$slug;
        $selector_css   = $this->parser->get_options(array('selectors',$selector_slug,'path'));
        $selector_regex = $this->parser->get_options(array('selectors',$selector_slug,'regex'));

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
            //flags
            $flags = 'm';
            //add delimiters
            $pattern = '~'.$pattern.'~'.$flags;
            //add beginning slash
            //$pattern = strrev($pattern); //reverse string
            //$pattern = trailingslashit($pattern);
            //$pattern = strrev($pattern);

            preg_match($pattern, $string, $matches);
            if (isset($matches[1])){
                $result = strip_tags($matches[1]);
            }
                
        }
        
        return $result;
    }
    
    function sanitize_cdata_string($string){
        $string = str_replace("//<![CDATA[","",$string);
        $string = str_replace("//]]>","",$string);

        $string = str_replace("<![CDATA[","",$string);
        $string = str_replace("]]>","",$string);

        return trim($string);
    }

    
}