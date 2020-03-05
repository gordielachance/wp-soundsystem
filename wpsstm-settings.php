<?php

class WPSSTM_Settings {

    static $menu_slug = 'wpsstm';

    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 8 );
        add_action( 'admin_init', array( $this, 'settings_init' ), 5 );
        add_action( 'admin_init', array( $this, 'system_settings_init' ), 15 );
        add_action( 'current_screen', array( $this, 'settings_clear_premium_transients' ), 5 );
	}

    function create_admin_menu(){
        //http://wordpress.stackexchange.com/questions/236896/remove-or-move-admin-submenus-under-a-new-menu/236897#236897

        /////Create our custom menu

        $menu_page = add_menu_page(
            __( 'Music', 'wpsstm' ), //page title
            __( 'Music', 'wpsstm' ), //menu title
            'manage_options', //capability
            self::$menu_slug,
            array($this,'settings_page'), //this function will output the content of the 'Music' page.
            'dashicons-album', // an image would be 'plugins_url( 'myplugin/images/icon.png' )'; but for core icons, see https://developer.wordpress.org/resource/dashicons
            6
        );

        //create a submenu page that has the same slug so we don't have the menu title name for the first submenu page, see http://wordpress.stackexchange.com/questions/66498/add-menu-page-with-different-name-for-first-submenu-item

        add_submenu_page(
            self::$menu_slug,
            __( 'WP SoundSystem Settings', 'wpsstm' ),
            __( 'Settings' ),
            'manage_options',
            self::$menu_slug, // same slug than the menu page
            array($this,'settings_page') // same output function too
        );

        //custom hook to add submenu pages.
        do_action('wpsstm_register_submenus',self::$menu_slug);

    }

    static function is_settings_reset(){
        return wpsstm_get_array_value(array('wpsstm_options','reset_options'),$_POST);
    }

    function settings_sanitize( $input ){
        $new_input = array();

        /*
        Maintenance
        */

        //reset
        if ( self::is_settings_reset() ) return;

        //orphan tracks
        if ( wpsstm_get_array_value(array('wpsstm_options','batch_delete_orphan_tracks'),$_POST) ){
            WPSSTM_Core_Tracks::batch_delete_orphan_tracks();
        }

        //orphan links
        if ( wpsstm_get_array_value(array('wpsstm_options','batch_delete_orphan_links'),$_POST) ){
            WPSSTM_Core_Track_Links::batch_delete_orphan_links();
        }


        //excluded host links
        if ( wpsstm_get_array_value(array('wpsstm_options','batch_delete_excluded_hosts_links'),$_POST) ){
            WPSSTM_Core_Track_Links::batch_delete_excluded_hosts_links();
        }

        //unused terms
        if ( wpsstm_get_array_value(array('wpsstm_options','batch_delete_unused_music_terms'),$_POST) ){
            WP_SoundSystem::batch_delete_unused_music_terms();
        }

        /*
        Bot user
        */

        //user id
        if ( isset ($input['bot_user_id']) && ctype_digit($input['bot_user_id']) ){
            $new_input['bot_user_id'] = $input['bot_user_id'];
        }

        /*
        Player
        */

        $new_input['player_enabled'] = isset($input['player_enabled']);

        /*
        Tracklists
        */
        $new_input['playlists_manager'] = isset($input['playlists_manager']);


        /*
        Track Links
        */
        $new_input['autolink'] = isset($input['autolink']);

        if ( isset($input['excluded_track_link_hosts']) ){

            $domains = explode(',',$input['excluded_track_link_hosts']);
            $domains = array_filter(array_unique($domains));
            $new_input['excluded_track_link_hosts'] = $domains;

            //rebuild cache ?
            if ( $domains != wpsstm()->get_options('excluded_track_link_hosts') ){
                WPSSTM_Core_Track_Links::rebuild_excluded_hosts_cache();
            }
        }

        /*
        Importer
        */

        //post ID
        if ( isset ($input['importer_page_id']) && ctype_digit($input['importer_page_id']) ){
            if ( get_post_type($input['importer_page_id'] ) ){ //check page exists
                $new_input['importer_page_id'] = $input['importer_page_id'];
            }
        }

        /*
        Now Playing
        */

        //post ID
        if ( isset ($input['nowplaying_id']) && get_post_type($input['nowplaying_id']) ){
            if ( get_post_type($input['nowplaying_id'] ) ){ //check page exists
                $new_input['nowplaying_id'] = $input['nowplaying_id'];
            }
        }

        //delay
        if ( isset ($input['play_history_timeout']) && ctype_digit($input['play_history_timeout']) ){
            $new_input['play_history_timeout'] = $input['play_history_timeout'] * HOUR_IN_SECONDS;
        }

        /*
        Sitewide favorites
        */

        //post ID
        if ( isset ($input['sitewide_favorites_id']) && get_post_type($input['sitewide_favorites_id']) ){
            if ( get_post_type($input['sitewide_favorites_id'] ) ){ //check page exists
                $new_input['sitewide_favorites_id'] = $input['sitewide_favorites_id'];
            }
        }

        /*
        WPSSTM API
        */

        $old_token = wpsstm()->get_options('wpsstmapi_key');

        $new_input['wpsstmapi_key'] = trim( wpsstm_get_array_value('wpsstmapi_key',$input) );

        $new_input['details_engines'] = (array)$input['details_engines'];

        return $new_input;

    }

    function settings_clear_premium_transients(){
        //force API checks by deleting some transients
        if ( !WP_SoundSystem::is_settings_page() ) return;
        self::clear_premium_transients();
    }

    public static function clear_premium_transients(){
        WP_SoundSystem::debug_log('deleted premium transients...');
        delete_transient( WPSSTM_Core_Importer::$importers_transient_name );
        delete_transient( WPSSTM_Core_API::$token_transient_name );
        delete_transient( WPSSTM_Core_API::$premium_userdata_transient_name );
    }

    function settings_init(){

        register_setting(
            'wpsstm_option_group', // Option group
            wpsstm()->meta_name_options, // Option name
            array( $this, 'settings_sanitize' ) // Sanitize
         );

        /*
        WPSSTM API
        */
        add_settings_section(
            'wpsstmapi_settings', // ID
            'WP SoundSystem API', // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'wpsstmapi_key',
            __('API Key','wpsstm'),
            array( $this, 'wpsstmapi_apitoken_callback' ),
            'wpsstm-settings-page',
            'wpsstmapi_settings'
        );

        add_settings_field(
            'wpsstmapi_premium',
            __('Premium','wpsstm'),
            array( $this, 'wpsstmapi_apipremium_callback' ),
            'wpsstm-settings-page',
            'wpsstmapi_settings'
        );

        add_settings_field(
            'details_engines',
            __('Music Details','wpsstm'),
            array( $this, 'details_engines_callback' ),
            'wpsstm-settings-page',
            'wpsstmapi_settings'
        );

        /*
        Bot user
        */
        add_settings_section(
            'bot_user_settings', // ID
            __('Import bot user','wpsstm'), // Title
            array( $this, 'section_bot_user_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'bot_user_id',
            __('User ID','wpsstm'),
            array( $this, 'bot_user_id_callback' ),
            'wpsstm-settings-page',
            'bot_user_settings'
        );

        /*
        Importer page
        */

        add_settings_section(
            'tracklist_importer', // ID
            __('Import page','wpsstm'), // Title
            array( $this, 'section_importer_page_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'importer_page_id',
            __('Page ID','wpsstm'),
            array( $this, 'importer_page_callback' ),
            'wpsstm-settings-page',
            'tracklist_importer'
        );

        /*
        Now Playing
        */

        add_settings_section(
            'now_playing', // ID
            __("Now playing",'wpsstm'), // Title
            array( $this, 'now_playing_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'now_playing_id',
            __('Playlist ID','wpsstm'),
            array( $this, 'now_playing_callback' ),
            'wpsstm-settings-page',
            'now_playing'
        );

        add_settings_field(
            'now_playing_delay',
            __('Delay','wpsstm'),
            array( $this, 'now_playing_delay_callback' ),
            'wpsstm-settings-page',
            'now_playing'
        );

        /*
        Sitewide favorites
        */

        add_settings_section(
            'sitewide_favorites', // ID
            __("Sitewide favorites",'wpsstm'), // Title
            array( $this, 'sitewide_favorites_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'sitewide_favorites_id',
            __('Playlist ID','wpsstm'),
            array( $this, 'sitewide_favorites_callback' ),
            'wpsstm-settings-page',
            'sitewide_favorites'
        );

        /*
        Player
        */
        add_settings_section(
            'player_settings', // ID
            __('Player','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'player_enabled',
            __('Enabled','wpsstm'),
            array( $this, 'player_enabled_callback' ),
            'wpsstm-settings-page',
            'player_settings'
        );

        /*
        Radios
        */

        /*
        add_settings_section(
            'radio_settings', // ID
            __('Radios','wpsstm'), // Title
            array( $this, 'section_radios_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        */

        /*
        Tracks
        */

        add_settings_section(
            'track_settings', // ID
            __('Tracks','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'playlists_manager',
            __('Enable Playlists Manager','wpsstm'),
            array( $this, 'playlists_manager_callback' ),
            'wpsstm-settings-page',
            'track_settings'
        );

        /*
        Track links
        */
        add_settings_section(
            'track_link_settings', // ID
            __('Track Links','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'autolink',
            __('Autolink','wpsstm'),
            array( $this, 'autolink_callback' ),
            'wpsstm-settings-page',
            'track_link_settings'
        );

        add_settings_field(
            'excluded_track_link_hosts',
            __('Exclude hosts','wpsstm'),
            array( $this, 'exclude_hosts_callback' ),
            'wpsstm-settings-page',
            'track_link_settings'
        );

    }

    function system_settings_init(){

        add_settings_section(
            'settings_maintenance', // ID
            __('Maintenance','wpsstm'), // Title
            array( $this, 'section_maintenance_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'reset_options',
            __('Reset Options','wpsstm'),
            array( $this, 'reset_options_callback' ),
            'wpsstm-settings-page', // Page
            'settings_maintenance'//section
        );

        add_settings_field(
            'batch_delete_orphan_tracks',
            __('Delete orphan tracks','wpsstm'),
            array( $this, 'batch_delete_orphan_tracks_callback' ),
            'wpsstm-settings-page', // Page
            'settings_maintenance'//section
        );

        add_settings_field(
            'batch_delete_orphan_links',
            __('Delete orphan links','wpsstm'),
            array( $this, 'batch_delete_orphan_links_callback' ),
            'wpsstm-settings-page', // Page
            'settings_maintenance'//section
        );

        add_settings_field(
            'batch_delete_excluded_hosts_links',
            __('Delete excluded hosts links','wpsstm'),
            array( $this, 'batch_delete_excluded_hosts_links_callback' ),
            'wpsstm-settings-page', // Page
            'settings_maintenance'//section
        );

        add_settings_field(
            'batch_delete_unused_music_terms',
            __('Delete unused music terms','wpsstm'),
            array( $this, 'batch_delete_unused_music_terms_callback' ),
            'wpsstm-settings-page', // Page
            'settings_maintenance'//section
        );

    }

    public static function section_desc_empty(){

    }

    public static function section_maintenance_desc(){
        _e('Please make a backup of your database before doing maintenance.','wpsstm');
    }

    function player_enabled_callback(){
        $option = wpsstm()->get_options('player_enabled');
        $desc = '';

        printf(
            '<input type="checkbox" name="%s[player_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option,true, false ),
            $desc
        );
    }

    function autolink_callback(){

        if ( $enabled = wpsstm()->get_options('autolink') ){

            $can_autolink = WPSSTM_Core_Track_Links::can_autolink();

            if ( is_wp_error($can_autolink) ){
                add_settings_error('autolink',$can_autolink->get_error_code(),$can_autolink->get_error_message(),'inline');
            }

        }

        /*
        form
        */

        printf(
            '<input type="checkbox" name="%s[autolink]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled, true, false ),
            __("Try to get track links (stream URLs, ...) automatically if none have been set.","wpsstm")
        );

        //display errors
        settings_errors('autolink');
    }

    function playlists_manager_callback(){
        global $wp_roles;

        $enabled = wpsstm()->get_options('playlists_manager');

        $matching_roles = array();
        $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;

        $help = array();
        $help[]= __("If enabled, you have to give your users the capability to create new playlists.","wpsstm");


        foreach($wp_roles->roles as $role_arr){
            if ( wpsstm_get_array_value(array('capabilities',$required_cap),$role_arr) ){
                $matching_roles[] = $role_arr['name'];
            }
        }

        if ($matching_roles){
            $help[]= sprintf(__("Those roles have the required capability: %s.","wpsstm"),'<em>' . implode(',',$matching_roles) . '</em>');
        }else{
            $help[]= __("Currently, no roles have this capability.","wpsstm");
        }

        printf(
            '<input type="checkbox" name="%s[playlists_manager]" value="on" %s /><label>%s</label><br/><small>%s</small>',
            wpsstm()->meta_name_options,
            checked( $enabled, true, false ),
            __("Users can favorite tracks and queue them to custom playlists.","wpsstm"),
            implode('  ',$help)
        );

    }

    function exclude_hosts_callback(){
        $excluded_hosts = wpsstm()->get_options('excluded_track_link_hosts');

        printf(
            '<p><label>%s</label><input type="text" name="%s[excluded_track_link_hosts]" class="wpsstm-fullwidth" placeholder="%s" value="%s" /></p>',
            __("Ignore the track links belonging to those hosts (comma-separated):","wpsstm"),
            wpsstm()->meta_name_options,
            __('eg. yandex.ru,itunes.apple.com','wpsstm'),
            implode(',',$excluded_hosts)
        );

    }

    function section_bot_user_desc(){
        $desc = array();

        $desc[]= __("Importing data requires a bot user with specific capabitilies.","wpsstm");

        $faq_url = 'https://github.com/gordielachance/wp-soundsystem/wiki/Frequently-Asked-Questions';
        $plugin_link = sprintf('<a href="%s" target="_blank">%s</a>',$faq_url,__('FAQ','wpsstm'));
        $desc[] = sprintf( __("See the plugin's %s for more information.","wpsstm"), $plugin_link );

        echo implode("  ",$desc);

    }

    function details_engines_callback(){
        $enabled_services = wpsstm()->get_options('details_engines');
        $available_engines = wpsstm()->get_available_detail_engines();

        foreach((array)$available_engines as $engine){

            $is_checked = in_array($engine->slug,$enabled_services);

            printf(
                '<input type="checkbox" name="%s[details_engines][]" value="%s" %s /> <label>%s</label> ',
                wpsstm()->meta_name_options,
                $engine->slug,
                checked($is_checked,true, false ),
                $engine->name
            );
        }

        //register errors
        $valid_token = WPSSTM_Core_API::get_token();

    }

    function wpsstmapi_apitoken_callback(){
        //client secret
        $client_secret = wpsstm()->get_options('wpsstmapi_key');
        $valid_token = WPSSTM_Core_API::get_token();

        printf(
            '<p><input type="text" name="%s[wpsstmapi_key]" value="%s" class="wpsstm-fullwidth" /></p>',
            wpsstm()->meta_name_options,
            $client_secret
        );

        /*
        errors
        */

        //register errors
        if ( is_wp_error($valid_token) ){
            add_settings_error('api_token',$valid_token->get_error_code(),$valid_token->get_error_message(),'inline');
        }

        /*
        if ( !$valid_token || is_wp_error($valid_token) ){
            $link = sprintf('<a href="%s" target="_blank">%s</a>',WPSSTM_API_REGISTER_URL,__('here','wpsstm'));
            $desc = sprintf( __('WP Soundsystem uses an external API for several features. Get a free API key %s.','wpsstm'),$link);

            add_settings_error('api_token','api_get_token',$desc,'inline');
        }
        */

        //display errors
        settings_errors('api_token');

    }

    function wpsstmapi_apipremium_callback(){

        $response = null;
        $response = WPSSTM_Core_API::is_premium();

        //register errors
        if ( is_wp_error($response) ){
            add_settings_error('api_premium',$response->get_error_code(),$response->get_error_message(),'inline');
        }

        if ( !$response || is_wp_error($response) ){

            $link = sprintf('<a href="%s" target="_blank">%s</a>',WPSSTM_API_REGISTER_URL,__('Get premium','wpsstm'));
            $desc = sprintf(__('%s and unlock powerful features : Tracklists Importer, Tracks Autolink...  First and foremost, it is a nice way to support this  plugin, and to ensure its durability.  Thanks for your help!','wppstm'),$link);

            add_settings_error('api_premium','api_get_premium',$desc,'inline');

        }else{

            $datas = WPSSTM_Core_API::get_api_userdatas();
            if ( !is_wp_error($datas) ){
              if ( $expires_at = wpsstm_get_array_value('expires_at',$datas) ){
                $date = date( 'Y-m-d H:i:s', strtotime($expires_at) );
                echo get_date_from_gmt($date , get_option( 'date_format' ) );
              }else{
                  echo '—';
              }
            }

        }

        /*
        errors
        */

        //display errors
        settings_errors('api_premium');

    }

    function section_importer_page_desc(){
        _e('Page used as placeholder to import tracklists frontend.','wppstm');
    }

    function now_playing_desc(){
        _e('Playlist displaying the last tracks played.','wppstm');
    }

    function sitewide_favorites_desc(){
        _e('Playlist displaying the last favorited tracks among members.','wppstm');
    }

    function section_radios_desc(){
        $desc[] = __('Radios are how we call live playlists.  Those are automatically synced with remote datas, like a web page or a Spotify playlist.','wppstm');

        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );

        echo implode("\n",$desc);

    }

    function importer_page_callback(){
        $page_id = wpsstm()->get_options('importer_page_id');

        printf(
            '<input type="number" name="%s[importer_page_id]" size="4" min="0" value="%s"/>',
            wpsstm()->meta_name_options,
            $page_id
        );

        if ( get_post_type($page_id) ){
            $page_title = get_the_title( $page_id );
            $edit_url = get_edit_post_link($page_id);
            $link_txt = sprintf(__('Edit %s','wpsstm'),'<em>' . $page_title . '</em>');
            printf('  <a href="%s">%s</a>',$edit_url,$link_txt);
        }

    }

    function now_playing_callback(){
        $page_id = wpsstm()->get_options('nowplaying_id');

        printf(
            '<input type="number" name="%s[nowplaying_id]" value="%s"/>',
            wpsstm()->meta_name_options,
            $page_id
        );

        if ( get_post_type($page_id) ){
            $page_title = get_the_title( $page_id );
            $edit_url = get_edit_post_link($page_id);
            $link_txt = sprintf(__('Edit %s','wpsstm'),'<em>' . $page_title . '</em>');
            printf('  <a href="%s">%s</a>',$edit_url,$link_txt);
        }

    }

    function now_playing_delay_callback(){
        $delay = wpsstm()->get_options('play_history_timeout');

        printf(
            '<input type="number" name="%s[play_history_timeout]" value="%s"/>',
            wpsstm()->meta_name_options,
            $delay / HOUR_IN_SECONDS
        );
        _e('Hours a track remains in the playlist','wpsstm');

    }

    function sitewide_favorites_callback(){
        $page_id = wpsstm()->get_options('sitewide_favorites_id');

        printf(
            '<input type="number" name="%s[sitewide_favorites_id]" value="%s"/>',
            wpsstm()->meta_name_options,
            $page_id
        );

        if ( get_post_type($page_id) ){
            $page_title = get_the_title( $page_id );
            $edit_url = get_edit_post_link($page_id);
            $link_txt = sprintf(__('Edit %s','wpsstm'),'<em>' . $page_title . '</em>');
            printf('  <a href="%s">%s</a>',$edit_url,$link_txt);
        }

    }

    function bot_user_id_callback(){
        $bot_id = wpsstm()->get_options('bot_user_id');
        $bot_ready = wpsstm()->is_bot_ready();

        if ( is_wp_error($bot_ready) ){
            add_settings_error('bot_user',$bot_ready->get_error_code(),$bot_ready->get_error_message(),'inline');
        }

        /*
        form
        */
        printf(
            '<input type="number" name="%s[bot_user_id]" size="4" min="0" value="%s" />',
            wpsstm()->meta_name_options,
            $bot_id
        );

        if ( $bot_id = wpsstm()->get_options('bot_user_id') ){
            $userdata = get_userdata( $bot_id );
            $edit_url = get_edit_user_link($bot_id);
            $link_txt = sprintf(__('Edit %s','wpsstm'),'<em>' . $userdata->user_login . '</em>');
            printf('  <a href="%s">%s</a>',$edit_url,$link_txt);

        }

        /*
        errors
        */
        settings_errors('bot_user');
    }

    /*
    System
    */

    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%s[reset_options]" value="on"/><label>%s</label>',
            wpsstm()->meta_name_options,
            __("Reset options to their default values.","wpsstm")
        );
    }

    function batch_delete_orphan_tracks_callback(){
      printf(
          '<input type="checkbox" name="%s[batch_delete_orphan_tracks]" value="on"/><label>%s</label>',
          wpsstm()->meta_name_options,
          __("Batch delete orphan tracks.","wpsstm")
      );
    }

    function batch_delete_orphan_links_callback(){
      printf(
          '<input type="checkbox" name="%s[batch_delete_orphan_links]" value="on"/><label>%s</label>',
          wpsstm()->meta_name_options,
          __("Batch delete orphan links.","wpsstm")
      );
    }

    function batch_delete_excluded_hosts_links_callback(){
      printf(
          '<input type="checkbox" name="%s[batch_delete_excluded_hosts_links]" value="on"/><label>%s</label>',
          wpsstm()->meta_name_options,
          __("Batch delete excluded hosts tracks links.","wpsstm")
      );
    }
    function batch_delete_unused_music_terms_callback(){
      printf(
          '<input type="checkbox" name="%s[batch_delete_unused_music_terms]" value="on"/><label>%s</label>',
          wpsstm()->meta_name_options,
          __("Batch delete unused music terms.","wpsstm")
      );
    }

	function settings_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('WP SoundSystem Settings','wpsstm');?></h2>

            <?php

            settings_errors('wpsstm_option_group');

            ?>
            <form method="post" action="options.php">
                <?php

                // This prints out all hidden setting fields
                settings_fields( 'wpsstm_option_group' );
                do_settings_sections( 'wpsstm-settings-page' );
                submit_button();

                ?>
            </form>

        </div>
        <?php
	}

}

new WPSSTM_Settings;
