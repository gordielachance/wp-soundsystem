<?php

class WPSSTM_Settings {
    
    static $menu_slug = 'wpsstm';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 8 );
        add_action( 'admin_init', array( $this, 'settings_init' ), 5 );
        add_action( 'admin_init', array( $this, 'system_settings_init' ), 15 );
        add_action( 'current_screen', array( $this, 'clear_settings_transients' ) );
	}

    function create_admin_menu(){
        //http://wordpress.stackexchange.com/questions/236896/remove-or-move-admin-submenus-under-a-new-menu/236897#236897

        /////Create our custom menu

        $menu_page = add_menu_page( 
            __( 'WP SoundSystem', 'wpsstm' ), //page title
            __( 'WP SoundSystem', 'wpsstm' ), //menu title
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
        
        //reset
        if ( self::is_settings_reset() ) return;
            
        if( isset( $input['trash-orphan-tracks'] ) ){
            WPSSTM_Core_Tracks::trash_orphan_tracks();
        }
        
        if( isset( $input['trash-orphan-links'] ) ){
            WPSSTM_Core_Track_Links::trash_orphan_links();
        }
        
        if( isset( $input['trash-duplicate-links'] ) ){
            WPSSTM_Core_Track_Links::trash_duplicate_links();
        }
        
        if( isset( $input['trash-temporary-tracklists'] ) ){
            WPSSTM_Core_Tracklists::trash_temporary_tracklists();
        }

        /*
        Community user
        */

        //user id
        if ( isset ($input['community_user_id']) && ctype_digit($input['community_user_id']) ){
            
            $user_id = $input['community_user_id'];
            $userdata = get_userdata( $user_id );
            
            if ( $userdata ){ //check user exists
                $new_input['community_user_id'] = $user_id;
            }
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
        }

        //delete links from excluded domains
        if( isset( $input['delete_excluded_track_link_hosts'] ) ){
            WPSSTM_Core_Track_Links::trash_excluded_hosts();
        }

        /*
        Importer
        */

        //scraper wizard page ID
        if ( isset ($input['frontend_scraper_page_id']) && ctype_digit($input['frontend_scraper_page_id']) ){
            if ( is_string( get_post_status( $input['frontend_scraper_page_id'] ) ) ){ //check page exists
                $new_input['frontend_scraper_page_id'] = $input['frontend_scraper_page_id'];
                flush_rewrite_rules(); //because of function frontend_wizard_rewrite()
            }

        }

        /*
        Styling
        */
        
        //recent wizard entries
        $new_input['registration_notice'] = isset($input['registration_notice']);
        
        /*
        WPSSTM API
        */

        $old_token = wpsstm()->get_options('wpsstmapi_token');

        $new_input['wpsstmapi_token'] = trim( wpsstm_get_array_value('wpsstmapi_token',$input) );
        
        $new_input['details_engine'] = (array)$input['details_engine'];

        return $new_input;

    }
    
    function clear_settings_transients(){
        //force API checks by deleting some transients
        if ( !WP_SoundSystem::is_settings_page() ) return;
        WP_SoundSystem::debug_log('deleted settings transients...');
        delete_transient( WPSSTM_Core_Importer::$importer_links_transient_name );
        delete_transient( WPSSTM_Core_API::$valid_token_transient_name );
        delete_transient( WPSSTM_Core_API::$premium_expiry_transient_name );
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
            'wpsstmapi_token', 
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
            'details_engine', 
            __('Music Details','wpsstm'), 
            array( $this, 'details_engine_callback' ), 
            'wpsstm-settings-page', 
            'wpsstmapi_settings'
        );

        /*
        Community user
        */
        add_settings_section(
            'community_user_settings', // ID
            __('Community user','wpsstm'), // Title
            array( $this, 'section_community_user_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'community_user_id', 
            __('Community user ID','wpsstm'), 
            array( $this, 'community_user_id_callback' ), 
            'wpsstm-settings-page', 
            'community_user_settings'
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
            __('Audio Player','wpsstm'), 
            array( $this, 'player_enabled_callback' ), 
            'wpsstm-settings-page', 
            'player_settings'
        );

        /*
        Tracklist Importer
        */

        add_settings_section(
            'tracklist_importer', // ID
            __('Tracklist Importer','wpsstm'), // Title
            array( $this, 'section_importer_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'frontend_scraper_page_id', 
            __('Frontend Tracklist Importer','wpsstm'), 
            array( $this, 'frontend_importer_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_importer'
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

        /*
        Styling
        */
        add_settings_section(
            'settings_styling', // ID
            __('Display','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'registration_notice', 
            __('Registration notice','wpsstm'), 
            array( $this, 'registration_notice_callback' ), 
            'wpsstm-settings-page', 
            'settings_styling'
        );

    }

    function system_settings_init(){

        add_settings_section(
            'settings_system', // ID
            __('System','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'reset_options', 
            __('Reset Options','wpsstm'), 
            array( $this, 'reset_options_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_system'//section
        );
        
        if ( $community_id = wpsstm()->get_options('community_user_id') ){
            
            add_settings_field(
                'trash_temporary_tracklists', 
                __('Trash temporary tracklists','wpsstm'), 
                array( $this, 'trash_temporary_tracklists_callback' ), 
                'wpsstm-settings-page', // Page
                'settings_system'//section
            );

            add_settings_field(
                'trash_orphan_tracks', 
                __('Trash orphan tracks','wpsstm'), 
                array( $this, 'trash_orphan_tracks_callback' ), 
                'wpsstm-settings-page', // Page
                'settings_system'//section
            );

        }
        
        add_settings_field(
            'trash_orphan_links', 
            __('Trash orphan links','wpsstm'), 
            array( $this, 'trash_orphan_links_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_system'//section
        );
        
        add_settings_field(
            'trash_duplicate_links', 
            __('Trash duplicate links','wpsstm'), 
            array( $this, 'trash_duplicate_links_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_system'//section
        );
        
        if ( wpsstm()->get_options('excluded_track_link_hosts') ){
            add_settings_field(
                'trash_excluded_track_link_hosts', 
                __('Trash excluded hosts links','wpsstm'), 
                array( $this, 'trash_excluded_track_link_hosts_callback' ), 
                'wpsstm-settings-page', // Page
                'settings_system'//section
            );
        }
        
    }
    
    public static function section_desc_empty(){
        
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
        
        $enabled = wpsstm()->get_options('autolink');

        /*
        form
        */
        
        printf(
            '<input type="checkbox" name="%s[autolink]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled, true, false ),
            __("Try to get track links (stream URLs, ...) automatically if none have been set.","wpsstm")
        );
        printf('<p style="color:red">%s</p>',__('Requires the Spotify API settings, and WP SoundSystem API premium.','wpsstm'));

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

    function section_community_user_desc(){
        $desc = array();
        $desc[]= __("The plugin requires a community user with specific capabitilies to enable some of the plugin's features; like autolink and tracklist importer.","wpsstm");

        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);

    }

    function details_engine_callback(){
        $enabled_services = wpsstm()->get_options('details_engine');
        $available_engines = wpsstm()->get_available_detail_engines();

        foreach((array)$available_engines as $engine){
            
            $is_checked = in_array($engine->slug,$enabled_services);
            
            printf(
                '<input type="radio" name="%s[details_engine]" value="%s" %s /> <label>%s</label> ',
                wpsstm()->meta_name_options,
                $engine->slug,
                checked($is_checked,true, false ),
                $engine->name
            );
        }
        
        //register errors
        $valid_token = WPSSTM_Core_API::has_valid_api_token();

    }
    
    function wpsstmapi_apitoken_callback(){
        //client secret
        $client_secret = wpsstm()->get_options('wpsstmapi_token');
        $valid_token = WPSSTM_Core_API::has_valid_api_token();

        printf(
            '<p><input type="text" name="%s[wpsstmapi_token]" value="%s" class="wpsstm-fullwidth" /></p>',
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
            
            $datas = WPSSTM_Core_API::get_premium_datas();
   
            if ( $expiry = wpsstm_get_array_value('expiry',$datas) ){
                echo get_date_from_gmt( date( 'Y-m-d H:i:s', $expiry ), get_option( 'date_format' ) );   
            }else{
                echo '—';
            }
            
        }

        /*
        errors
        */

        //display errors
        settings_errors('api_premium');

    }

    function section_importer_desc(){
        $desc[] = __('This module adds a new metabox to import tracks from various services like Spotify, or any webpage where a tracklist is displayed.','wppstm');
        
        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);
        
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

    function frontend_importer_callback(){
        $option = wpsstm()->get_options('frontend_scraper_page_id');

        $help = array();
        $help[]= __("ID of the page used to display the frontend Tracklist Importer.","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));
        
        printf(
            '<input type="number" name="%s[frontend_scraper_page_id]" size="4" min="0" value="%s"/><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
        );
    }

    function registration_notice_callback(){
        $option = wpsstm()->get_options('registration_notice');
        $readonly = !get_option( 'users_can_register' );

        printf(
            '<input type="checkbox" name="%s[registration_notice]" value="on" %s %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option,true, false ),
            wpsstm_readonly( $readonly,true, false ),
            __("Invite non-logged users to login or register by displaying a frontend notice.","wpsstm")
        );
    }
    
    function community_user_id_callback(){
        $option = wpsstm()->get_options('community_user_id');

        /*
        form
        */
        printf(
            '<input type="number" name="%s[community_user_id]" size="4" min="0" value="%s" />',
            wpsstm()->meta_name_options,
            $option
        );
        
        /*
        errors
        */
        settings_errors('community_user_id');
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
    
    function trash_temporary_tracklists_callback(){
        $count = count(WPSSTM_Core_Tracklists::get_temporary_tracklists_ids());
        $desc = sprintf(__("Trash %d tracklists that were created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-temporary-tracklists]" value="on" %s /><label>%s</label>',
            wpsstm()->meta_name_options,
            disabled($count,0,false),
            $desc
        );
    }
    
    function trash_orphan_tracks_callback(){
        $count = count(WPSSTM_Core_Tracks::get_orphan_track_ids());
        $desc = sprintf(__("Trash %d tracks that do not belong to any tracklists and have been created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-orphan-tracks]" value="on" %s /><label>%s</label>',
            wpsstm()->meta_name_options,
            disabled($count,0,false),
            $desc
        );
    }
    
    function trash_orphan_links_callback(){
        $count = count(WPSSTM_Core_Track_Links::get_orphan_link_ids());
        $desc = sprintf(__("Trash %d links that are not attached to a track post.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-orphan-links]" value="on" %s /><label>%s</label>',
            wpsstm()->meta_name_options,
            disabled($count,0,false),
            $desc
        );
    }
    
    function trash_duplicate_links_callback(){
        $count = count(WPSSTM_Core_Track_Links::get_duplicate_link_ids());
        $desc = sprintf(__("Trash %d duplicate links (same URL & parent post).","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-duplicate-links]" value="on" %s /><label>%s</label>',
            wpsstm()->meta_name_options,
            disabled($count,0,false),
            $desc
        );
    }
    
    function trash_excluded_track_link_hosts_callback(){
            $count = count( WPSSTM_Core_Track_Links::get_excluded_host_link_ids() );

            printf(
                '<p><input type="checkbox" name="%s[delete_excluded_track_link_hosts]" value="on" %s /><label>%s</label></p>',
                wpsstm()->meta_name_options,
                disabled($count,0,false),
                sprintf(__("Delete the %s track links matching the excluded hosts list.","wpsstm"),$count)
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