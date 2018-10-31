<?php
class WPSSTM_Spotify{
    static $spotify_id_meta_key = '_wpsstm_spotify_id';
    static $spotify_data_meta_key = '_wpsstm_spotify_data';
    static $spotify_data_time_metakey = '_wpsstm_spotify_data_time';
    static $spotify_no_auto_id_metakey = '_wpsstm_spotify_no_auto_id';
    static $spotify_data_by_url_transient_prefix = 'wpsstm_spotify_by_url_'; //to cache the musicbrainz API results
    function __construct(){
        if ( wpsstm()->get_options('spotify_client_id') && wpsstm()->get_options('spotify_client_secret') ){
            add_filter('wpsstm_wizard_services_links',array($this,'register_spotify_service_links'));
            add_action('wpsstm_live_tracklist_init',array($this,'register_spotify_presets'));
            add_action( 'add_meta_boxes', array($this, 'metabox_spotify_register'),55);
            add_action( 'save_post', array(__class__,'metabox_spotify_id_save'), 7);
            add_action( 'save_post', array(__class__,'auto_spotify_id_on_post_save'), 8);
            add_action( 'save_post', array(__class__,'metabox_spotify_data_save'), 9);
            
            //backend columns
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_artist), array(__class__,'spotify_columns_register'), 10, 2 );
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'spotify_columns_register'), 10, 2 );
            add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'spotify_columns_register'), 10, 2 );

            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_artist), array(__class__,'spotify_columns_content'), 10, 2 );
            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'spotify_columns_content'), 10, 2 );
            add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'spotify_columns_content'), 10, 2 );
            
        }
    }
    //register presets
    function register_spotify_presets($tracklist){
        new WPSSTM_Spotify_URL_Api_Preset($tracklist);
        new WPSSTM_Spotify_URI_Api_Preset($tracklist);
    }
    function register_spotify_service_links($links){
        $links[] = array(
            'slug'      => 'spotify',
            'name'      => 'Spotify',
            'url'       => 'https://www.spotify.com',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'https://open.spotify.com/user/USER_SLUG/playlist/PLAYLIST_ID',
                ),
            )
        );
        return $links;
    }
    
    static function get_access_token(){
        
        $client_id = wpsstm()->get_options('spotify_client_id');
        $client_secret = wpsstm()->get_options('spotify_client_secret');


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
            wpsstm()->debug_log($response->get_error_message(),'Error getting Spotify Token' ); 
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        $token = $body->access_token;

        return $token;

    }
    
    static function get_spotify_request_args(){
        $token = self::get_access_token();
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
        $sid_callback = array(__class__,'metabox_spotify_id_content');
        
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

    static function can_spotify_search_entries($post_id){
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
    
    static function get_edit_spotify_id_input($post_id = null){
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
        
        if ( wpsstm()->get_options('spotify_auto_id') == "on" ){
            $is_ignore = ( get_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true ) );
            $input_auto_id_el = sprintf('<input type="checkbox" value="on" name="wpsstm-ignore-auto-spotify-id" %s/>',checked($is_ignore,true,false));
            $desc_el .= $input_auto_id_el . ' ' .__("Do not auto-identify",'wpsstm');
        }
        
        return $input_el . $desc_el;
    }

    public static function metabox_spotify_id_content($post){

        $spotify_id = wpsstm_get_post_spotify_id($post->ID);
        $can_search_entries = self::can_spotify_search_entries($post->ID);

        ?>
        <p>
            <?php echo self::get_edit_spotify_id_input($post->ID);?>
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

        $entries = self::search_spotify_entries_for_post($post->ID);

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
    private static function search_spotify_entries_for_post($post_id = null ){
        
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
        
        $data = self::get_spotify_api_entry($api_type,null,$api_query);
        if ( is_wp_error($data) ) return $data;
        
        $result_keys[] = 'items';
        return wpsstm_get_array_value($result_keys, $data);
        
    }
    
    static function get_spotify_api_entry($type,$spotify_id = null,$query = null,$offset = null){
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
            
            $spotify_request_args = self::get_spotify_request_args();
            $request = wp_remote_get($url,$spotify_request_args);
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
    
    public static function auto_spotify_id( $post_id ){

        $sid = null;
        $entries = array();

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return false;

        $entries = self::search_spotify_entries_for_post($post_id);
        if ( is_wp_error($entries) ) return $entries;
        if (!$entries) return;
        
        $first_entry = $entries[0];

        //get ID of first entry
        $sid = $first_entry['id'];

        wpsstm()->debug_log( json_encode(array('post_id'=>$post_id,'spotify_'=>$sid)),"Auto Spotify ID" ); 
        
        if ($sid){
            update_post_meta( $post_id, self::$spotify_id_meta_key, $sid );
            self::reload_spotify_datas($post_id);
            return $sid;
        }
        
    }
    
    /*
    Reload Spotify entry data for an MBID.
    */
    
    private static function reload_spotify_datas($post_id){

        //delete existing
        if ( delete_post_meta( $post_id, self::$spotify_data_meta_key ) ){
            delete_post_meta( $post_id, self::$spotify_data_time_metakey ); //delete timestamp
            wpsstm()->debug_log('WPSSTM_MusicBrainz::reload_spotify_datas() : deleted Spotify datas');
        }


        if ( !$id = wpsstm_get_post_spotify_id($post_id) ) return;

        //get API data
        $api_type = self::get_spotify_type_by_post_id($post_id,true);
        $data = self::get_spotify_api_entry($api_type,$id);
        if ( is_wp_error($data) ) return $data;

        if ( $success = update_post_meta( $post_id, self::$spotify_data_meta_key, $data ) ){

            //save timestamp
            $now = current_time('timestamp');
            update_post_meta( $post_id, self::$spotify_data_time_metakey, $now );
        }
        
        return $success;
        
    }
    
    public static function metabox_spotify_id_save( $post_id ) {

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
        }elseif ( isset($_POST['wpsstm-spotify-reload']) ){
            $action = 'reload';
        }elseif ( isset($_POST['wpsstm-spotify-fill']) ){
            $action = 'fill';
        }
        
        
        //update ID
        $old_id = wpsstm_get_post_mbid($post_id);
        $id = ( isset($_POST[ 'wpsstm_spotify_id' ]) ) ? $_POST[ 'wpsstm_spotify_id' ] : null;
        $is_id_update = ($old_id != $id);

        if (!$id){
            delete_post_meta( $post_id, self::$spotify_id_meta_key );
            delete_post_meta( $post_id, self::$spotify_data_meta_key ); //delete mdbatas
            delete_post_meta( $post_id, self::$spotify_data_time_metakey ); //delete mdbatas timestamp
        }else{
            update_post_meta( $post_id, self::$spotify_id_meta_key, $id );
            
            if ($is_id_update){
                self::reload_spotify_datas($post_id);
            }
        }
        
        //ignore auto MBID
        if ( wpsstm()->get_options('spotify_auto_id') == "on" ){
            $do_ignore = ( isset($_POST['wpsstm-ignore-auto-spotify-id']) ) ? true : false;
            if ($do_ignore){
                update_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true );
            }else{
                delete_post_meta( $post_id, self::$spotify_no_auto_id_metakey );
            }
        }

        switch ($action){
            case 'autoguess-id':
                $id = self::auto_spotify_id( $post_id );
                if ( is_wp_error($id) ) break;
            break;
        }

    }
    
    public static function metabox_spotify_data_save( $post_id ){

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
                self::reload_spotify_datas($post_id);
            break;
            /*
            TOUFIX    
            case 'fill':
                $field_slugs = isset($_POST['wpsstm-spotify-data-fill-fields']) ? $_POST['wpsstm-spotify-data-fill-fields'] : array();

                if ( !empty($field_slugs) ){
                    self::fill_with_mbdatas($post_id,$field_slugs,true);
                }

            break;
            */
        }
        
    }
    
    /*
    When saving an artist / track / album and that no Spotify ID exists, guess it - if option enabled
    */
    
    public static function auto_spotify_id_on_post_save( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $skip_status = in_array( get_post_status( $post_id ),array('auto-draft','trash') );
        $is_revision = wp_is_post_revision( $post_id );

        if ( $is_autosave || $skip_status || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;
        
        //ignore if global option disabled
        $auto_id = ( wpsstm()->get_options('spotify_auto_id') == "on" );
        if (!$auto_id) return false;

        //ignore if option disabled
        $is_ignore = ( get_post_meta( $post_id, self::$spotify_no_auto_id_metakey, true ) );
        if ($is_ignore) return false;
        
        $track = new WPSSTM_Track($post_id);
        
        //ignore if value exists
        if ($track->spotify_id) return;
        
        //get auto mbid
        $id = self::auto_spotify_id( $post_id );
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
    
}

class WPSSTM_Spotify_URL_Api_Preset{
    var $tracklist;
    private $playlist_id;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->playlist_id = $this->get_playlist_id();

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wppstm_live_tracklist_pagination',array($this,'get_remote_pagination') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
        add_filter( 'wpsstm_live_tracklist_author',array($this,'get_remote_author') );
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args') );
        
    }

    function can_handle_url(){
        if ( !$this->playlist_id ) return;        
        return true;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){

            $url = sprintf('	https://api.spotify.com/v1/playlists/%s/tracks',$this->playlist_id);
            $limit = $this->tracklist->request_pagination['page_items_limit'];

            /*
            TOUFIX
            $pagination_args = array(
                'limit'     => $limit,
                'offset'    => ($this->tracklist->request_pagination['current_page'] - 1) * $limit
            );
            */
            $pagination_args = array();

            $url = add_query_arg($pagination_args,$url);
        }

        return $url;
        

    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'           => array('path'=>'root > items'),
                'track_artist'     => array('path'=>'track > artists > name'),
                'track_album'      => array('path'=>'track > album > name'),
                'track_title'      => array('path'=>'track > name'),
            );
        }
        return $options;
    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);

        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_id(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/[^/]+/playlist/([\w\d]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_pagination($pagination){
        
        if ( $this->can_handle_url() ){
            
            $data = WPSSTM_Spotify::get_spotify_api_entry('playlists',$this->playlist_id);

            if ( is_wp_error($data) ){
                return $data;
            }

            //TO FIX not very clean ? Should we remove track_count and use pagination variable only ?
            $this->tracklist->track_count = wpsstm_get_array_value(array('tracks','total'), $data);

            //init pagination before request
            $pagination['page_items_limit'] = 100;
            

        }
        
        return $pagination;

    }
    
    function get_remote_title($title){
        
        if ( $this->can_handle_url() ){
            $data = WPSSTM_Spotify::get_spotify_api_entry('playlists',$this->playlist_id);
            if ( !is_wp_error($data) ){
                 $title = wpsstm_get_array_value('name', $data);
            }
           
        }
        return $title;

    }
    
    function get_remote_author($author){
        if ( $this->can_handle_url() ){
            $data = WPSSTM_Spotify::get_spotify_api_entry('playlists',$this->playlist_id);
            if ( !is_wp_error($data) ){
                 $author = wpsstm_get_array_value(array('owner','id'), $data);
            }
        }
        return $author;
    }

    function remote_request_args($args){
        
        if ( $this->can_handle_url() ){
            
            $spotify_args = WPSSTM_Spotify::get_spotify_request_args();
            if ( !is_wp_error($spotify_args) ){
                $args = array_merge($args,$spotify_args);
            }
            
        }

        return $args;
    }



}

//Spotify Playlists URIs
class WPSSTM_Spotify_URI_Api_Preset extends WPSSTM_Spotify_URL_Api_Preset{

    function get_user_slug(){
        $pattern = '~^spotify:user:([^:]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_id(){
        $pattern = '~^spotify:user:.*:playlist:([\w\d]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

function wpsstm_spotify_init(){
    new WPSSTM_Spotify();
}

add_action('wpsstm_init','wpsstm_spotify_init');