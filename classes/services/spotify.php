<?php
class WPSSTM_Spotify{
    
    static $spotify_options_meta_name = 'wpsstm_spotify_options';
    static $spotify_id_meta_key = '_wpsstm_spotify_id';
    static $spotify_data_meta_key = '_wpsstm_spotify_data';
    static $spotify_data_time_metakey = '_wpsstm_spotify_data_time';
    static $spotify_no_auto_id_metakey = '_wpsstm_spotify_no_auto_id';
    static $spotify_data_by_url_transient_prefix = 'wpsstm_spotify_by_url_'; //to cache the musicbrainz API results
    
    public $options = array();
    
    function __construct(){
        
        $options_default = array(
            'client_id' =>          null,
            'client_secret' =>      null,
            'spotify_auto_id' =>    true,
        );
        
        $this->options = wp_parse_args(get_option( self::$spotify_options_meta_name),$options_default);
        
        /*backend*/
        add_action( 'admin_init', array( $this, 'spotify_settings_init' ) );
        
        if ( $this->can_spotify_api() === true ){
            
            //presets
            add_filter('wpsstm_feed_url', array($this, 'spotify_playlist_bang_to_url'));
            add_filter('wpsstm_remote_presets',array($this,'register_spotify_presets'));
            
            
            add_action( 'add_meta_boxes', array($this, 'metabox_spotify_register'),55);
            
            //backend columns
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_artist), array(__class__,'spotify_columns_register'), 10, 2 );
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'spotify_columns_register'), 10, 2 );
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'spotify_columns_register'), 10, 2 );

            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_artist), array(__class__,'spotify_columns_content'), 10, 2 );
            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'spotify_columns_content'), 10, 2 );
            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'spotify_columns_content'), 10, 2 );

            add_filter('wpsstm_wizard_service_links',array($this,'register_spotify_service_links'), 6);
            add_filter('wpsstm_wizard_bang_links',array($this,'register_spotify_bang_links'));
            
            add_action( 'save_post', array($this,'metabox_spotify_id_save'), 7);
            add_action( 'save_post', array($this,'auto_spotify_id_on_post_save'), 8);
            add_action( 'save_post', array($this,'metabox_spotify_data_save'), 9);
        }
    }
    
    function spotify_playlist_bang_to_url($url){
        $pattern = '~^spotify:user:([^:]+):playlist:([\w\d]+)~i';
        preg_match($pattern,$url, $matches);
        $user = isset($matches[1]) ? $matches[1] : null;
        $playlist = isset($matches[2]) ? $matches[2] : null;
        
        if ($user && $playlist){
            $url = sprintf('https://open.spotify.com/user/%s/playlist/%s',$user,$playlist);
        }
        
        return $url;
    }
    
    function register_spotify_presets($presets){
        $presets[] = new WPSSTM_Spotify_Playlist_Api_Preset();
        return $presets;
    }
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
        
    public function can_spotify_api(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        if ( !$client_id ) return new WP_Error( 'spotify_no_client_id', __( "Required Spotify client ID missing", "wpsstm" ) );
        if ( !$client_secret ) return new WP_Error( 'spotify_no_client_secret', __( "Required Spotify client secret missing", "wpsstm" ) );
        
        return true;
        
    }
    
    function spotify_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$spotify_options_meta_name, // Option name
            array( $this, 'spotify_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'spotify_service', // ID
            'Spotify', // Title
            array( $this, 'spotify_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'spotify_client', 
            __('API','wpsstm'), 
            array( $this, 'spotify_api_settings' ), 
            'wpsstm-settings-page', // Page
            'spotify_service'//section
        );
        
    }

    function spotify_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;

        $new_input['client_id'] = ( isset($input['client_id']) ) ? trim($input['client_id']) : null;
        $new_input['client_secret'] = ( isset($input['client_secret']) ) ? trim($input['client_secret']) : null;

        return $new_input;
    }
    
    function spotify_settings_desc(){
        $new_app_link = 'https://developer.spotify.com/my-applications/#!/applications/create';
        
        printf(__('Required for the Live Playlists Spotify preset.  Create a Spotify application %s to get the required informations.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
    }

    function spotify_api_settings(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_secret
        );
    }

    function register_spotify_service_links($links){
        $item = sprintf('<a href="https://www.spotify.com" target="_blank" title="%s"><img src="%s" /></a>','Spotify',wpsstm()->plugin_url . '_inc/img/spotify-icon.png');
        $links[] = $item;
        return $links;
    }
    
    function register_spotify_bang_links($links){
        $bang_playlist = '<label><code>spotify:user:USER:playlist:PLAYLIST_ID</code></label>';
        //$bang_playlist .= sprintf('<div id="wpsstm-spotify-playlist-bang" class="wpsstm-bang-desc">%s</div>',$desc);
        $links[] = $bang_playlist;
        return $links;
    }
    
    private function get_access_token(){
        
        $can_api = $this->can_spotify_api();
        if ( is_wp_error($can_api) ) return $can_api;
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');


        $token = false;

        $args = array(
            'headers'   => array(
                'Authorization' => 'Basic '.base64_encode($client_id.':'.$client_secret)
            ),
            'body'      => array(
                'grant_type'    => 'client_credentials'
            )
        );


        $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

        if ( is_wp_error($response) ){
            return new WP_Error('spotify_missing_token',$response->get_error_message());
        }
            
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);

        if ( property_exists($body, 'access_token') ){
            return $body->access_token;
        }elseif ( property_exists($body, 'error') ){
            return new WP_Error('spotify_missing_token',$body->error);
        }else{
            return new WP_Error('spotify_missing_token','Error getting Spotify Token');
        }

    }
    
    public function get_spotify_request_args(){
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) return $token;
        
        $request_args = array(
            'headers'=>array(
                'Authorization' =>  'Bearer ' . $token,
                'Accept' =>         'application/json',
            )
        );
        return $request_args;
        
    }

    function metabox_spotify_register(){
        global $post;
        if (!$post) return;

        $entries_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );

        //MBID Metabox
        $sid_callback = array($this,'metabox_spotify_id_content');
        
        //TOUFIX
        /*
        if ( self::is_entries_switch() ){  
            $sid_callback = array(__class__,'metabox_spotify_entries_content');
        }
        */

        add_meta_box( 
            'wpsstm-spotify-id', 
            __('Spotify ID','wpsstm'),
            $sid_callback,
            $entries_post_types,
            'after_title', 
            'high' 
        );
        
        //MB datas Metabox
        if ( $sid = wpsstm_get_post_spotify_id($post->ID) ){
            add_meta_box( 
                'wpsstm-spotify_data', 
                __('Spotify Data','wpsstm'),
                array(__class__,'metabox_spotify_data_content'),
                $entries_post_types,
                'after_title', 
                'high' 
            );
        }

    }
    
    /*
    Checks if the post contains enough information to do an API lookup
    */

    function can_spotify_search_entries($post_id){
        $post_type = get_post_type($post_id);
        
        $spotify_id = wpsstm_get_post_spotify_id($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);
        
        $can = false;

        switch($post_type){
            case wpsstm()->post_type_artist:
                $can = ($spotify_id || $artist);
            break;
            case wpsstm()->post_type_track:
                $can = ($spotify_id || ($artist && $track) );
            break;
            case wpsstm()->post_type_album:
                $can = ($spotify_id || ($artist && $album) );
            break;
        }
        
        return $can;

    }
    
    private function get_edit_spotify_id_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        
        $input_el = $desc_el = null;
        
        $input_attr = array(
            'id' => 'wpsstm-spotify_id',
            'name' => 'wpsstm_spotify_id',
            'value' => wpsstm_get_post_spotify_id($post_id),
            'icon' => '<i class="fa fa-key" aria-hidden="true"></i>',
            'label' => __("Spotify ID",'wpsstm'),
            'placeholder' => __("Enter Spotify ID here",'wpsstm')
        );
        
        $input_el = wpsstm_get_backend_form_input($input_attr);
        
        if ( $this->get_options('spotify_auto_id') ){
            $is_ignore = ( get_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true ) );
            $input_auto_id_el = sprintf('<input type="checkbox" value="on" name="wpsstm-ignore-auto-spotify-id" %s/>',checked($is_ignore,true,false));
            $desc_el .= $input_auto_id_el . ' ' .__("Do not auto-identify",'wpsstm');
        }
        
        return $input_el . $desc_el;
    }

    public function metabox_spotify_id_content($post){

        $spotify_id = wpsstm_get_post_spotify_id($post->ID);
        $can_search_entries = $this->can_spotify_search_entries($post->ID);

        ?>
        <p>
            <?php echo $this->get_edit_spotify_id_input($post->ID);?>
        </p>
        <table class="form-table">
            <tbody>
                <?php 
                if ( $can_search_entries ){
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Spotify Lookup','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            submit_button( __('Spotify Lookup','wpsstm'), null, 'wpsstm-spotify-id-lookup');
                            _e('Search ID from track title, artist & album.','wpsstm');
                            ?>

                        </td>
                    </tr>
                    <?php
                }
                ?>
                <?php 
                /*
                TOUFIX
                
                if ($can_search_entries && $spotify_id) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Switch entry','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            $entries_url = get_edit_post_link();
                            $entries_url = add_query_arg(array('spotify-list-entries'=>true),$entries_url);
                            printf('<a class="button" href="%s">%s</a>',$entries_url,__('Switch entry','wpsstm'));
                            ?>
                        </td>
                    </tr>
                    <?php
                }
                */
                ?>
            </tbody>
        </table>
        <?php

        /*
        form
        */

        wp_nonce_field( 'wpsstm_spotify_id_meta_box', 'wpsstm_spotify_id_meta_box_nonce' );
        
        
    }
    
    public static function metabox_spotify_data_content($post){

        $spotify_id = wpsstm_get_post_spotify_id($post->ID);
        $spotify_data = wpsstm_get_spotify_data($post->ID);

        ?>
        <table class="form-table">
            <tbody>
                <?php 
                if ($spotify_data) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Data','wpsstm');?></label>
                        </th>
                        <td>
                            <p>
                                <?php
                                /* 
                                Entry data 
                                */
                                $list = wpsstm_get_list_from_array($spotify_data);
                                printf('<div id="wpsstm-mbdata">%s</div>',$list);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                <?php 
                if ($spotify_data) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Refresh data','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            submit_button( __('Refresh data','wpsstm'), null, 'wpsstm-spotify-data-reload');
                    
                            if ( $then = get_post_meta( $post->ID, self::$spotify_data_time_metakey, true ) ){
                                $now = current_time( 'timestamp' );
                                $refreshed = human_time_diff( $now, $then );
                                printf(__('Data was refreshed %s ago.','wpsstm'),$refreshed);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                    /*
                    TOUFIX
                    
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Fill post','wpsstm');?></label>
                        </th>
                        <td>
                            <p>
                            <?php
                            $fields = self::get_fillable_fields($post->ID);
                    
                            foreach ($fields as $slug=>$field){
                                
                                //mismatch check
                                $mismatch  = $db = $mb = null;
                                
                                $meta = get_post_meta($post->ID,$field['metaname'],true);
                                $mb = wpsstm_get_array_value($field['mbpath'], $spotify_data);
                                
                                //exceptions
                                if($slug=='track_length'){
                                   $mb = round($mb / 1000);
                                }

                                $mismatch_icon = ($meta != $mb) ? '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' : null;

                                $input_el = sprintf('<input type="checkbox" name="wpsstm-spotify-data-fill-fields[]" value="%s"/> %s<label>%s</label><br/>',$slug,$mismatch_icon,$field['name']);
                                echo $input_el;
                            }
                            submit_button( __('Fill with data','wpsstm'), null, 'wpsstm-spotify-data-fill');
                            ?>
                            </p>
                            <?php
                            _e('Fill post with various datas from MusicBrainz (eg. artist, length, ...).','wpsstm');
                            ?>
                        </td>
                    </tr>
                    <?php
                    */
                }
                ?>
            </tbody>
        </table>
        <?php

        /*
        form
        */

        wp_nonce_field( 'wpsstm_spotify_data_meta_box', 'wpsstm_spotify_data_meta_box_nonce' );
        
        
    }
    
    //TOUFIX
    public static function metabox_spotify_entries_content($post){

        settings_errors('wpsstm_spotify-entries');

        $entries = null;

        $entries = $this->search_spotify_entries_for_post($post->ID);

        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm-spotify-entries', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table = new WPSSTM_Spotify_Entries();
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }

        //same nonce than in metabox_mbid_content()
        wp_nonce_field( 'wpsstm_spotify_id_meta_box', 'wpsstm_spotify_id_meta_box_nonce' );
    }
    
    public static function get_spotify_type_by_post_id($post_id = null,$plural = false){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        $post_type = get_post_type($post_id);
        
        $type = null;
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $type = 'artist';
            break;
            case wpsstm()->post_type_track:
                $type = 'track';
            break;
            case wpsstm()->post_type_album:
                $type = 'album';
            break;
        }
        
        if ($plural){
            $type.='s';
        }
        
        return $type;
        
    }
    
    //TOUFIX
    private function search_spotify_entries_for_post($post_id = null ){
        
        global $post;
        if (!$post_id) $post_id = $post->ID;
        if (!$post_id) return;

        $api_type = self::get_spotify_type_by_post_id($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);

        $api_lookup = null;
        $api_query = null;
        $result_keys = null;

        switch($api_type){
                
            case 'artist':
                
                if ( !$artist ) break;
                
                $api_query = sprintf('artist:%s',$artist);
                $result_keys = array('artists');
                
            break;
                
            case 'track':
                
                if ( !$artist || !$track ) break;
                
                $api_query = sprintf('artist:%s track:%s',$artist,$track);
                $result_keys = array('tracks');
                
            break;
                
            case 'album':
                
                if ( !$artist || !$album ) break;
                
                $api_query = sprintf('artist:%s album:%s',$artist,$album);
                $result_keys = array('albums');
                
            break;

        }

        if (!$api_query) return;
        
        $data = $this->get_spotify_api_entry($api_type,null,$api_query);
        if ( is_wp_error($data) ) return $data;
        
        $result_keys[] = 'items';
        return wpsstm_get_array_value($result_keys, $data);
        
    }
    
    function get_spotify_api_entry($type,$spotify_id = null,$query = null,$offset = null){
        global $post;

        $api_results = null;
        $cached_results = null;
        
        if ($spotify_id){
            
            $allowed_types = array('artists', 'albums', 'tracks','playlists');

            if ( !in_array($type,$allowed_types) ) 
                return new WP_Error('spotify_invalid_type',__("invalid Spotify type",'wpsstm'));
            
            $url = sprintf('https://api.spotify.com/v1/%s/%s',$type,$spotify_id);
        }elseif($query){
            
            $allowed_types = array('artist', 'album', 'track');

            if ( !in_array($type,$allowed_types) ) 
                return new WP_Error('spotify_invalid_type',__("invalid Spotify type",'wpsstm'));
            
            $url_args = array(
                'q' =>      rawurlencode($query),
                'type' =>   $type,
                'limit' =>  10,
            );
            
            $url = add_query_arg($url_args,'https://api.spotify.com/v1/search');
        }else{
            return;
        }

        //define the transient name for this MB url
        $transient_url_name = str_replace('https://api.spotify.com/v1/','',$url);
        $transient_url_name = self::$spotify_data_by_url_transient_prefix.md5($transient_url_name); //WARNING should be 172 characters or less or less !  md5 returns 32 chars.
        
        // check if we should try to load cached data

        if ( $days_cache = wpsstm()->get_options('cache_api_results') ){
            $api_results = $cached_results = get_transient( $transient_url_name );
        }

        
        if ( !$api_results ){
            
            $spotify_args = $this->get_spotify_request_args();
            if (is_wp_error($spotify_args) ) return $spotify_args;
            
            $request = wp_remote_get($url,$spotify_args);
            if (is_wp_error($request)) return $request;

            $response = wp_remote_retrieve_body( $request );
            if (is_wp_error($response)) return $response;

            if ( $api_results = json_decode($response, true) ){
                if ( $days_cache ){
                    set_transient( $transient_url_name, $api_results, $days_cache * DAY_IN_SECONDS );
                }
            }

        }
        
        //debug
        $cached = ($cached_results) ? '(loaded from transient)' : null;
        wpsstm()->debug_log(sprintf("get_spotify_api_entry():%s %s",$url,$cached)); 

        return $api_results;

    }
    
    /**
    Try to guess the MusicBrainz ID of a post, based on its artist / album / title.
    **/
    
    public function auto_spotify_id( $post_id ){

        $sid = null;
        $entries = array();

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return false;

        $entries = $this->search_spotify_entries_for_post($post_id);
        if ( is_wp_error($entries) ) return $entries;
        if (!$entries) return;
        
        $first_entry = $entries[0];

        //get ID of first entry
        $sid = $first_entry['id'];

        wpsstm()->debug_log( json_encode(array('post_id'=>$post_id,'spotify'=>$sid)),"Auto Spotify ID" ); 
        
        if ($sid){
            $success = update_post_meta( $post_id, self::$spotify_id_meta_key, $sid );
            $this->reload_spotify_datas($post_id);
            return $sid;
        }
        
    }
    
    /*
    Reload Spotify entry data for an MBID.
    */
    
    private function reload_spotify_datas($post_id){

        //delete existing
        if ( delete_post_meta( $post_id, self::$spotify_data_meta_key ) ){
            delete_post_meta( $post_id, self::$spotify_data_time_metakey ); //delete timestamp
        }


        if ( !$id = wpsstm_get_post_spotify_id($post_id) ) return;

        //get API data
        $api_type = self::get_spotify_type_by_post_id($post_id,true);
        $data = $this->get_spotify_api_entry($api_type,$id);
        if ( is_wp_error($data) ) return $data;

        if ( $success = update_post_meta( $post_id, self::$spotify_data_meta_key, $data ) ){

            //save timestamp
            $now = current_time('timestamp');
            update_post_meta( $post_id, self::$spotify_data_time_metakey, $now );
        }
        
        return $success;
        
    }
    
    public function metabox_spotify_id_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_spotify_id_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_spotify_id_meta_box_nonce'], 'wpsstm_spotify_id_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;
        
        unset($_POST['wpsstm_spotify_id_meta_box_nonce']); //so we avoid the infinite loop

        //clicked a musicbrainz action button
        $action = null;
        if ( isset($_POST['wpsstm-spotify-id-lookup']) ){
            $action = 'autoguess-id';
        }
        
        
        //update ID
        $old_id = wpsstm_get_post_spotify_id($post_id);
        $id = ( isset($_POST[ 'wpsstm_spotify_id' ]) ) ? $_POST[ 'wpsstm_spotify_id' ] : null;
        $is_id_update = ($old_id != $id);

        if (!$id){
            delete_post_meta( $post_id, self::$spotify_id_meta_key );
            delete_post_meta( $post_id, self::$spotify_data_meta_key ); //delete mdbatas
            delete_post_meta( $post_id, self::$spotify_data_time_metakey ); //delete mdbatas timestamp
        }else{
            update_post_meta( $post_id, self::$spotify_id_meta_key, $id );
            
            if ($is_id_update){
                $this->reload_spotify_datas($post_id);
            }
        }
        
        //ignore auto MBID
        if ( $this->get_options('spotify_auto_id') ){
            $do_ignore = ( isset($_POST['wpsstm-ignore-auto-spotify-id']) ) ? true : false;
            if ($do_ignore){
                update_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true );
            }else{
                delete_post_meta( $post_id, self::$spotify_no_auto_id_metakey );
            }
        }

        switch ($action){
            case 'autoguess-id':
                $id = $this->auto_spotify_id( $post_id );
                if ( is_wp_error($id) ) break;
            break;
        }

    }
    
    public function metabox_spotify_data_save( $post_id ){

        $mbid = null;
        $mbdata = null;

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );

        $is_metabox = isset($_POST['wpsstm_spotify_data_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_spotify_data_meta_box_nonce'], 'wpsstm_spotify_data_meta_box' ) );
        if ( !$is_valid_nonce ) return;

        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_spotify_data_meta_box_nonce']);

        //clicked a musicbrainz action button
        $action = null;
        if ( isset($_POST['wpsstm-spotify-data-reload']) ){
            $action = 'reload';
        }elseif ( isset($_POST['wpsstm-spotify-data-fill']) ){
            $action = 'fill';
        }

        switch ($action){

            case 'reload':
                $this->reload_spotify_datas($post_id);
            break;
        }
        
    }
    
    /*
    When saving an artist / track / album and that no Spotify ID exists, guess it - if option enabled
    */
    
    public function auto_spotify_id_on_post_save( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $skip_status = in_array( get_post_status( $post_id ),array('auto-draft','trash') );
        $is_revision = wp_is_post_revision( $post_id );

        if ( $is_autosave || $skip_status || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;
        
        //ignore if global option disabled
        if ( !$this->get_options('spotify_auto_id') ) return false;

        //ignore if option disabled
        $is_ignore = ( get_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true ) );
        if ($is_ignore) return false;
        
        $track = new WPSSTM_Track($post_id);
        
        //ignore if value exists
        if ($track->spotify_id) return;
        
        //get auto mbid
        $id = $this->auto_spotify_id( $post_id );
        if ( is_wp_error($id) ) return $id;
        
        if($id){
            add_filter( 'redirect_post_location', array(__class__,'after_auto_id_redirect') );
        }

        return $id;
    }

    static function is_entries_switch(){
        return ( isset($_GET['spotify-list-entries'])) ? true : false;
    }
    
    public static function after_auto_id_redirect($location){
        $location = add_query_arg(array('spotify-list-entries'=>true),$location);
        return $location;
    }
    
    public static function spotify_columns_register($defaults) {
        $defaults['spotifyid'] = __('Spotify ID','wpsstm');
        return $defaults;
    }
    
    public static function spotify_columns_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'spotifyid':
                $link = ($link = wpsstm_get_post_spotify_link_for_post($post_id)) ? $link : '-';
                echo $link;
                
            break;
        }
    }
    
    public static function get_user_from_url($url){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)/playlist/([\w\d]+)~i';
        preg_match($pattern,$url, $matches);

        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

class WPSSTM_Spotify_Playlist_Api_Preset extends WPSSTM_Remote_Tracklist{
    
    var $playlist_id;
    var $playlist_data;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'           => array('path'=>'root > items'),
                'track_artist'     => array('path'=>'track > artists > name'),
                'track_album'      => array('path'=>'track > album > name'),
                'track_title'      => array('path'=>'track > name'),
            )
        );
        
        parent::__construct($url,$options);
        
        $this->request_pagination['tracks_per_page'] = 100; //spotify API
        

    }
    
    function init_url($url){
        global $wpsstm_spotify;

        if ( $this->playlist_id = self::get_playlist_id_from_url($url) ){
            $this->playlist_data = $wpsstm_spotify->get_spotify_api_entry('playlists',$this->playlist_id);
            
            //update pagination
            $total_tracks = wpsstm_get_array_value(array('tracks','total'),$this->playlist_data);
            $this->request_pagination['total_pages'] = ceil($total_tracks / $this->request_pagination['tracks_per_page']);

        }

        return (bool)$this->playlist_id;

    }
    
    static function get_playlist_id_from_url($url){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)/playlist/([\w\d]+)~i';
        preg_match($pattern,$url, $matches);

        $user_id =  isset($matches[1]) ? $matches[1] : null;
        $playlist_id = isset($matches[2]) ? $matches[2] : null;
        
        return $playlist_id;
        
    }

    function get_remote_request_url(){
        $url = sprintf('https://api.spotify.com/v1/playlists/%s/tracks',$this->playlist_id);

        $pagination_args = array(
            'limit'     => $this->request_pagination['tracks_per_page'],
            'offset'    => $this->request_pagination['current_page'] * $this->request_pagination['tracks_per_page']
        );

        $url = add_query_arg($pagination_args,$url);

        return $url;
    }

    function get_remote_request_args(){
        global $wpsstm_spotify;
        
        $args = parent::get_remote_request_args();
        $spotify_args = $wpsstm_spotify->get_spotify_request_args();
        
        if (is_wp_error($spotify_args) ) return $spotify_args;
        return array_merge($args,$spotify_args);
    }

    function get_remote_title(){
        $title = wpsstm_get_array_value('name', $this->playlist_data);
        return $title;

    }
    
    function get_remote_author(){
        $author = wpsstm_get_array_value(array('owner','id'), $this->playlist_data);
        return $author;
    }

}


function wpsstm_spotify_init(){
    global $wpsstm_spotify;
    $wpsstm_spotify = new WPSSTM_Spotify();
}

add_action('wpsstm_init','wpsstm_spotify_init');