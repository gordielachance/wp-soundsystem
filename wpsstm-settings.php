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
        Tracklist
        */

        $new_input['player_enabled'] = isset($input['player_enabled']);
        $new_input['autoplay'] = isset($input['autoplay']);
        $new_input['autosource'] = isset($input['autosource']);

        //shorten tracklist
        if ( isset ($input['toggle_tracklist']) && ctype_digit($input['toggle_tracklist']) ){
            $new_input['toggle_tracklist'] = $input['toggle_tracklist'];
        }

        /*
        Sources
        */


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

        //recent wizard entries
        if ( isset ($input['recent_wizard_entries']) && ctype_digit($input['recent_wizard_entries']) ){
                $new_input['recent_wizard_entries'] = $input['recent_wizard_entries'];
        }

        //$new_input = array_filter($new_input); //disabled here because this will remove '0' values

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
        Tracklists
        */
        add_settings_section(
            'tracklist_settings', // ID
            __('Tracklists','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'player_enabled', 
            __('Audio Player','wpsstm'), 
            array( $this, 'player_enabled_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_settings'
        );
        
        add_settings_field(
            'autoplay', 
            __('Autoplay','wpsstm'), 
            array( $this, 'autoplay_callback' ), 
            'wpsstm-settings-page', 
            'tracklist_settings'
        );

        /*
        Sources
        */
        
        add_settings_section(
            'sources', // ID
            __('Sources','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'autosource', 
            __('Autosource','wpsstm'), 
            array( $this, 'autosource_callback' ), 
            'wpsstm-settings-page', 
            'sources'
        );

        /*
        Frontend Wizard
        */
        
        add_settings_section(
            'frontend_wizard_settings', // ID
            __('Frontend Wizard','wpsstm'), // Title
            array( $this, 'section_frontend_wizard_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        

        add_settings_field(
            'frontend_scraper_page_id', 
            __('Frontend wizard page ID','wpsstm'), 
            array( $this, 'wizard_page_id_callback' ), 
            'wpsstm-settings-page', 
            'frontend_wizard_settings'
        );
        
        add_settings_field(
            'visitors_wizard', 
            __('Enable for visitors','wpsstm'), 
            array( $this, 'visitors_wizard_callback' ), 
            'wpsstm-settings-page', 
            'frontend_wizard_settings'
        );
        
        add_settings_field(
            'recent_wizard_entries', 
            __('Show recent entries','wpsstm'), 
            array( $this, 'recent_wizard_entries_callback' ), 
            'wpsstm-settings-page', 
            'frontend_wizard_settings'
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
            'toggle_tracklist', 
            __('Shorten tracklist','wpsstm'), 
            array( $this, 'toggle_tracklist_callback' ), 
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
    
    function autoplay_callback(){
        $option = wpsstm()->get_options('autoplay');

        printf(
            '<input type="checkbox" name="%s[autoplay]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, true, false ),
            __("Autoplay the first track displayed.","wpsstm")
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
            $can_autosource = WPSSTM_Core_Sources::can_autosource();
            if ( is_wp_error($can_autosource) ){
                add_settings_error('autosource', 'cannot_autosource', $can_autosource->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('autosource');

    }

    function toggle_tracklist_callback(){
        $option = (int)wpsstm()->get_options('toggle_tracklist');

        $desc = __("When the tracklist loads, only show a limited amout of tracks and display a button to expand it.","wpsstm");
        $help = __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",$help);

        printf(
            '<input type="number" name="%s[toggle_tracklist]" size="4" min="0" value="%s" />%s %s',
            wpsstm()->meta_name_options,
            $option,
            $desc,
            $help
        );
    }

    function section_community_user_desc(){
        $desc = array();
        $desc[]= __("The plugin requires a community user with specific capabitilies to enable some of the plugin's features; like autosource, live playlists or frontend wizard.","wpsstm");

        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);

    }

    function section_frontend_wizard_desc(){
        _e('Setup a frontend page from which users will be able to load a remote tracklist.','wppsm');
        printf('  <small>%s</small>',__("This requires the community user ID to be set.","wpsstm") );
    }
    
    function wizard_page_id_callback(){
        $option = (int)wpsstm()->get_options('frontend_scraper_page_id');

        $help = array();
        $help[]= __("ID of the page used to display the frontend Tracklist Wizard.","wpsstm");
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
             $can_frontend_wizard = WPSSTM_Core_Live_Playlists::is_community_user_ready();
            if ( is_wp_error($can_frontend_wizard) ){
                add_settings_error('frontend_wizard', 'cannot_frontend_wizard',$can_frontend_wizard->get_error_message(),'inline');
            }
            
        }
        
        //display errors
        settings_errors('frontend_wizard');
        
    }
    
    function visitors_wizard_callback(){
        $option = wpsstm()->get_options('visitors_wizard');

        printf(
            '<input type="checkbox" name="%s[visitors_wizard]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option,true, false ),
            __("Enable frontend wizard for non-logged users.","wpsstm")
        );
    }
    
    function recent_wizard_entries_callback(){
        $option = (int)wpsstm()->get_options('recent_wizard_entries');
        
        $help = array();
        $help[]= __("Number of recent entries to display on the wizard page.","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));

        printf(
            '<input type="number" name="%s[recent_wizard_entries]" size="2" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
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
        $desc = sprintf(__("Delete %d tracks that do not belong to any playlists and have been created with the community user.","wpsstm"),$count);
        printf(
            '<input type="checkbox" name="%s[trash-orphan-tracks]" value="on" %s /> %s',
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