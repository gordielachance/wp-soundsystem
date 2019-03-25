<?php

class WPSSTM_Settings {
    
    static $menu_slug = 'wpsstm';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 8 );
        add_action( 'admin_init', array( $this, 'settings_init' ), 5 );
        add_action( 'admin_init', array( $this, 'system_settings_init' ), 15 );
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

        if ( self::is_settings_reset() ) return;
            
        if( isset( $input['trash-orphan-tracks'] ) ){
            WPSSTM_Core_Tracks::trash_orphan_tracks();
        }
        
        if( isset( $input['trash-temporary-tracklists'] ) ){
            WPSSTM_Core_Tracklists::trash_temporary_tracklists();
        }
        
        if( isset( $input['trash-unplayable-sources'] ) ){
            //TO FIX
        }

        /*
        Community user
        */

        //user id
        if ( isset ($input['community_user_id']) && ctype_digit($input['community_user_id']) ){
            if ( get_userdata( $input['community_user_id'] ) ){ //check user exists
                $new_input['community_user_id'] = $input['community_user_id'];
            }
        }

        /*
        Player
        */

        $new_input['player_enabled'] = isset($input['player_enabled']);
        $new_input['autosource'] = isset($input['autosource']);

        /*
        Importer
        */
        
        $new_input['importer_enabled'] = isset($input['importer_enabled']);
        $new_input['radios_enabled'] = isset($input['radios_enabled']);


        /*
        Live playlists
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
        $new_token = trim( wpsstm_get_array_value('wpsstmapi_token',$input) );
        
        if($old_token !== $new_token){
            delete_transient( WPSSTM_Core_API::$auth_transient_name );
            wpsstm()->debug_log('deleted wpsstmapi auth transient');
        }

        $new_input['wpsstmapi_token'] = $new_token;
        
        $new_input['details_engine'] = $input['details_engine'];


        return $new_input;
        
        
    }

    function settings_init(){

        register_setting(
            'wpsstm_option_group', // Option group
            wpsstm()->meta_name_options, // Option name
            array( $this, 'settings_sanitize' ) // Sanitize
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
            __('Enabled','wpsstm'), 
            array( $this, 'player_enabled_callback' ), 
            'wpsstm-settings-page', 
            'player_settings'
        );

        add_settings_field(
            'autosource', 
            __('Autosource','wpsstm'), 
            array( $this, 'autosource_callback' ), 
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
            'importer_enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'importer_enabled_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_importer'
        );
        
        add_settings_field(
            'radios_enabled', 
            __('Radios Post Type','wpsstm'), 
            array( $this, 'radios_enabled_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_importer'
        );

        add_settings_field(
            'frontend_scraper_page_id', 
            __('Frontend Tracklist Importer','wpsstm'), 
            array( $this, 'frontend_importer_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_importer'
        );

        /*
        Styling
        */
        add_settings_section(
            'settings_styling', // ID
            __('Styling','wpsstm'), // Title
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
        
        /*
        WPSSTM API
        */
        add_settings_section(
            'wpsstmapi_settings', // ID
            'WP SoundSystem API', // Title
            array( $this, 'section_wpsstmapi_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'wpsstmapi_token', 
            __('API','wpsstm'), 
            array( $this, 'wpsstmapi_apisecret_callback' ), 
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
            
            add_settings_field(
                'trash_unplayable_sources', 
                __('Trash unplayable sources','wpsstm'), 
                array( $this, 'trash_unplayable_sources_callback' ), 
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

    function autosource_callback(){
        
        $enabled = wpsstm()->get_options('autosource');

        /*
        form
        */
        
        printf(
            '<input type="checkbox" name="%s[autosource]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled, true, false ),
            __("If no source is set for a track, try to find an online source automatically.","wpsstm")
        );
        
        /*
        errors
        */
        
        //register errors
        if ( $enabled ){
        
            //autosource
            $can = WPSSTM_Core_Sources::can_autosource();

            if ( is_wp_error($can) ){
                add_settings_error('autosource',$can->get_error_code(),$can->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('autosource');

    }

    function section_community_user_desc(){
        $desc = array();
        $desc[]= __("The plugin requires a community user with specific capabitilies to enable some of the plugin's features; like autosource and tracklist importer.","wpsstm");

        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);

    }
    
    function section_wpsstmapi_desc(){
        
        $features = array(
            sprintf(__('Automatically search and save track sources online with the %s.','wpsstm'),sprintf('<strong>%s</strong>',__('autosource module','wpsstm'))),
            sprintf(__('Import tracklists from popular music services like Spotify (and almost any website where a tracklist is visible) with the %s.','wpsstm'),sprintf('<strong>%s</strong>',__('Tracklist Importer metabox','wpsstm'))),
            sprintf(__('Enable the %s - remote tracklists that are automatically refreshing every X minutes.','wpsstm'),sprintf('<strong>%s</strong>',__('Radios post type','wpsstm'))),
        );
        
        //wrap
        $features = array_map(
           function ($el) {
              return "<li>{$el}</li>";
           },
           $features
        );
        
        
        $desc[] = __('Get more out of this plugin by registering an API key!','wppsm');
        $desc[] = sprintf('<ul>%s</ul>',implode("\n",$features));
        $desc[] = __("That's also a nice way to support the work done - hundred of hours - for this free plugin, and to ensure its durability.  Thanks for your help !",'wppsm');
        
        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo sprintf('<div id="wpsstm-api-promo">%s</div>',implode("\n",$desc));
        
    }
    
    function details_engine_callback(){
        $option = wpsstm()->get_options('details_engine');
        $available_engines = wpsstm()->get_available_detail_engines();
        
        foreach((array)$available_engines as $key=>$engine){
            
            printf(
                '<input type="radio" name="%s[details_engine]" value="%s" %s /> <label>%s</label> ',
                wpsstm()->meta_name_options,
                $key,
                checked($option,$key, false ),
                $engine->name
            );
        }

    }
    
    function wpsstmapi_apisecret_callback(){
        //client secret
        $client_secret = wpsstm()->get_options('wpsstmapi_token');
        printf(
            '<p><label>%s</label> <input type="text" name="%s[wpsstmapi_token]" value="%s" /></p>',
            __('API Token:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );

        printf('<p><a href="%s" target="_blank">%s</a> !</p>',WPSSTM_API_REGISTER_URL,__('Get an API key now','wpsstm'));
    }

    function section_importer_desc(){
        $desc[] = __('This module adds a new metabox to import tracks from various services like Spotify, or any webpage where a tracklist is displayed.','wppsm');
        $desc[] = __('It also enables a new post type, "Radios", which are self-updating tracklists based on a remote URL.','wppsm');
        
        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);
        
    }
    
    function importer_enabled_callback(){
        $enabled = wpsstm()->get_options('importer_enabled');
        $desc = '';
        
        printf(
            '<input type="checkbox" name="%s[importer_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled,true, false ),
            $desc
        );
        
        /*
        errors
        */
        
        //register errors
        if ( $enabled ){
        
            //autosource
            $can = wpsstm()->can_importer();
            if ( is_wp_error($can) ){
                add_settings_error('importer',$can->get_error_code(),$can->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('importer');
        
    }
    
    function radios_enabled_callback(){
        $enabled = wpsstm()->get_options('radios_enabled');
        $desc = '';
        
        printf(
            '<input type="checkbox" name="%s[radios_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled,true, false ),
            $desc
        );
        
        /*
        errors
        */
        
        //register errors
        if ( $enabled ){
        
            //autosource
            $can = wpsstm()->can_radios();
            if ( is_wp_error($can) ){
                add_settings_error('radios',$can->get_error_code(),$can->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('radios');
        
    }
    
    function frontend_importer_callback(){
        $option = (int)wpsstm()->get_options('frontend_scraper_page_id');

        $help = array();
        $help[]= __("ID of the page used to display the frontend Tracklist Importer.","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));
        
        printf(
            '<input type="number" name="%s[frontend_scraper_page_id]" size="4" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
        );
        
        //register errors
        if ( $option ){

            //can wizard
            $can = wpsstm()->can_frontend_importer();
            if ( is_wp_error($can) ){
                add_settings_error('frontend_wizard',$can->get_error_code(),$can->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('frontend_wizard');
        
    }

    function registration_notice_callback(){
        $option = wpsstm()->get_options('registration_notice');

        printf(
            '<input type="checkbox" name="%s[registration_notice]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option,true, false ),
            __("Display a registration notice for non-logged users.","wpsstm")
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
            '<input type="checkbox" name="%s[reset_options]" value="on"/> %s',
            wpsstm()->meta_name_options,
            __("Reset options to their default values.","wpsstm")
        );
    }
    
    function trash_temporary_tracklists_callback(){
        $count = count(WPSSTM_Core_Tracklists::get_temporary_tracklists_ids());
        $desc = sprintf(__("Delete %d tracklists that were created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-temporary-tracklists]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            disabled($count,0,false),
            $desc
        );
    }
    
    function trash_orphan_tracks_callback(){
        $count = count(WPSSTM_Core_Tracks::get_orphan_track_ids());
        $desc = sprintf(__("Delete %d tracks that do not belong to any tracklists and have been created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-orphan-tracks]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
             disabled($count,0,false),
            $desc
        );
    }
    
    function trash_unplayable_sources_callback(){
        $count = 0;
        $desc = sprintf(__("Delete %d unplayable sources that have been created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-unplayable-sources]" value="on" %s disabled="disabled" /> %s',
            wpsstm()->meta_name_options,
             disabled($count,0,false),
            $desc
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