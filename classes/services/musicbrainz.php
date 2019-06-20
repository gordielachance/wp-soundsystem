<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPSSTM_MusicBrainz {
    static $mbz_options_meta_name = 'wpsstm_musicbrainz_options';
    public $options = array();

    function __construct(){
        
        $options_default = array(
            'enabled' =>    true
        );
        
        $this->options = wp_parse_args(get_option( self::$mbz_options_meta_name),$options_default);
        
        add_filter('wpsstm_remote_presets',array($this,'register_musicbrainz_preset'));
        add_filter('wpsstm_wizard_service_links',array($this,'register_musicbrainz_service_links'), 8);

        //backend
        add_action( 'admin_init', array( $this, 'mbz_settings_init' ) );
        add_filter( 'wpsstm_get_music_detail_engines',array($this,'register_details_engine'), 5 );

    }
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    
    function register_musicbrainz_preset($presets){
        $presets[] = new WPSSTM_Musicbrainz_Release_ID_Preset();
        return $presets;
    }

    function mbz_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$mbz_options_meta_name, // Option name
            array( $this, 'mbz_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'mbz_service', // ID
            'MusicBrainz', // Title
            array( $this, 'mbz_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'mbz_enabled_callback' ), 
            'wpsstm-settings-page', // Page
            'mbz_service'//section
        );
        
    }
    
    function mbz_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;
        
        //MusicBrainz
        $new_input['enabled'] = isset($input['enabled']);
        
        return $new_input;
    }
    
    function mbz_settings_desc(){
        $mb_link = '<a href="https://musicbrainz.org/" target="_blank">MusicBrainz</a>';
        printf(__('%s is an open data music database.  By enabling it, the plugin will fetch various informations about the tracks, artists and albums you post.','wpsstm'),$mb_link);
    }

    function mbz_enabled_callback(){
        $option = $this->get_options('enabled');
        
        $el = sprintf(
            '<input type="checkbox" name="%s[enabled]" value="on" %s /> %s',
            self::$mbz_options_meta_name,
            checked( $option,true, false ),
            __("Enable MusicBrainz.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }
    
    static function register_musicbrainz_service_links($links){
        $item = sprintf('<a href="https://www.musicbrainz.org" target="_blank" title="%s"><img src="%s" /></a>','Musicbrainz',wpsstm()->plugin_url . '_inc/img/musicbrainz-icon.png');
        $links[] = $item;
        return $links;
    }

    public function register_details_engine($engines){
        $engines[] = new WPSSTM_Musicbrainz_Data();
        return $engines;
    }
    
}

class WPSSTM_Musicbrainz_Data extends WPSSTM_Music_Data{
    public $slug = 'musicbrainz';
    public $name = 'MusicBrainz';
    public $entries_table_classname = 'WPSSTM_MB_Entries';
            
    protected function get_supported_post_types(){
        return array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
    }
       
    /**
    Get the link of an item (artist/track/album).
    **/
    public function get_music_item_url($post_id = null){

        if ( !$music_id = $this->get_post_music_id($post_id) ) return;
            
        $remote_type = null;
        $post_type = get_post_type($post_id);
            
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $remote_type = 'artist';
            break;
            case wpsstm()->post_type_track:
                $remote_type = 'recording';
            break;
            case wpsstm()->post_type_album:
                $remote_type = 'release';
            break;
        }
            
        if (!$remote_type) return;
        return sprintf('https://musicbrainz.org/%s/%s',$remote_type,$music_id);

    }
            
    protected function query_music_entries( $artist,$album = null,$track = null ){

        $api_url = null;
        
        //url encode
        $artist = urlencode($artist);
        $album = ($album) ? $album : '_';
        $album = urlencode($album);
        $track = urlencode($track);
        
        if($artist && $track){//track
            $api_url = sprintf('services/musicbrainz/search/%s/%s/%s',$artist,$album,$track);
        }elseif($artist && $album){//album
            $api_url = sprintf('services/musicbrainz/search/%s/%s',$artist,$album);
        }elseif($artist){//artist
            $api_url = sprintf('services/musicbrainz/search/%s',$artist);
        }

        if (!$api_url){
            return new WP_Error('wpsstmapi_no_api_url',__("We were unable to build the API url",'wpsstm'));
        }

        return WPSSTM_Core_API::api_request($api_url);
    }
            
    public function get_item_auto_id($artist,$album=null,$track=null){
        $entries = $this->query_music_entries($artist,$album,$track);
        if ( is_wp_error($entries) || !$entries ) return $entries;
        
        $entry = wpsstm_get_array_value(array(0),$entries);

        $score = wpsstm_get_array_value(array('score'),$entry);
        $music_id = wpsstm_get_array_value(array('id'),$entry);

        if ($score < 90) return; //only if we got a minimum score
        return $music_id;
    }
    
    protected function get_music_data_for_post($post_id){
        
        if ( !$post_type = get_post_type($post_id) ) return false;
        
        if ( !$music_id = $this->get_post_music_id($post_id) ){
            return new WP_Error('wpsstm_missing_music_id',__("Missing music ID",'wpsstm'));
        }
        
        //remote API type
        $endpoint = null;
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $endpoint = 'artists';
            break;
            //album
            case wpsstm()->post_type_album:
                $endpoint = 'releases';
            break;
            //track
            case wpsstm()->post_type_track:
                $endpoint = 'recordings';
            break;
        }
        
        $api_url = sprintf('services/musicbrainz/data/%s/%s',$endpoint,$music_id);
        return WPSSTM_Core_API::api_request($api_url);
    }

    protected function artistdata_get_artist($data){
        return wpsstm_get_array_value(array('name'), $data);
    }
    protected function trackdata_get_artist($data){
        return wpsstm_get_array_value(array('artist-credit',0,'name'), $data);
    }
    protected function trackdata_get_track($data){
        return wpsstm_get_array_value(array('title'), $data);
    }
    protected function trackdata_get_album($data){
        return wpsstm_get_array_value(array('releases',0,'title'), $data);
    }
    protected function trackdata_get_length($data){
        return wpsstm_get_array_value(array('length'), $data);
    }
    protected function albumdata_get_artist($data){
        return wpsstm_get_array_value(array('artist-credit',0,'name'), $data);
    }
    protected function albumdata_get_album($data){
        return wpsstm_get_array_value(array('title'), $data);
    }     
}

class WPSSTM_MB_Entries extends WPSSTM_Music_Entries {
    
    function display_tablenav($which){
        
    }
    
    function prepare_items() {
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
    }
    
    function get_columns(){
        global $post;
        
        $columns = array(
            'cb'            => '',//<input type="checkbox" />', //Render a checkbox instead of text
        );
        
        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $columns['mbitem_artist'] = __('Artist','wpsstm');
            break;
                
            case wpsstm()->post_type_track:
                $columns['mbitem_track'] = __('Track','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                $columns['mbitem_album'] = __('Album','wpsstm');
            break;
                
            case wpsstm()->post_type_album:
                $columns['mbitem_album'] = __('Album','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                
            break;

        }
        
        //mbid
        $columns['mbitem_mbid'] = __('MusicBrainz ID','wpsstm');
        
        if ( WPSSTM_Music_Data::is_entries_switch() ){
            $columns['mbitem_score'] = __('Score','wpsstm');
        }

        return $columns;
    }
    
	public function column_mbitem_artist( $item ) {
        global $post;
        
        $output = '—';
        $artist = null;

        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $artist = $item;
            break;
                
            case wpsstm()->post_type_track:
            case wpsstm()->post_type_album:
                $artist = $item['artist-credit'][0]['artist'];
            break;

        }
        
        if (!$artist) return $output;
        
        $output = $artist['name'];

        if ( isset($artist['disambiguation']) ){
            $output.=' '.sprintf('<small>%s</small>',$artist['disambiguation']);
        }

        return $output;
    }
    
	public function column_mbitem_track( $item ) {
        global $post;
        
        $output = '—';
        
        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                $output = $item['title'];
            break;

        }
        
        return $output;
    }
    
	public function column_mbitem_album( $item ) {
        global $post;
        
        $album = null;
        $output = '—';

        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                 $album = wpsstm_get_array_value(array('releases',0),$item);
            break;
                
            case wpsstm()->post_type_album:
                $album = $item;
            break;

        }
        
        if (!$album) return $output;

        $output = $album['title'];
        $small_title_arr = array();
        
        
        //date
        if ( isset($album['date']) ){
            $small_classes = array('item-info-title');
            $small_classes_str = wpsstm_get_classes_attr($small_classes);
            $small_title_arr[]=' '.sprintf('<small %s>%s</small>',$small_classes_str,$album['date']);
        }
        
        $output .= implode("",$small_title_arr);

        return $output;
    }

	public function column_mbitem_score( $item ) {
        echo wpsstm_get_percent_bar($item['score']);
	}
    
}

function wpsstm_musicbrainz_init(){
    global $wpsstm_musicbrainz;
    $wpsstm_musicbrainz = new WPSSTM_MusicBrainz();
}

add_action('wpsstm_load_services','wpsstm_musicbrainz_init');