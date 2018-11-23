<?php

class WPSSTM_Settings {
    
    static $menu_slug = 'wpsstm';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 8 );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
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
    
    function settings_sanitize( $input ){
        $new_input = array();

        if( isset( $input['reset_options'] ) ){
            
            $new_input = wpsstm()->options_default;
            
        }else{ //sanitize values
            
            if( isset( $input['flush_community_tracks'] ) ){
                WPSSTM_Core_Tracks::flush_community_tracks();
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
            
            //scrobble along
            $new_input['lastfm_community_scrobble'] = ( isset($input['lastfm_community_scrobble']) ) ? 'on' : 'off';
            
            /*
            Tracklist
            */
            
            $new_input['player_enabled'] = ( isset($input['player_enabled']) ) ? 'on' : 'off';
            $new_input['autoplay'] = ( isset($input['autoplay']) ) ? 'on' : 'off';
            $new_input['autosource'] = ( isset($input['autosource']) ) ? 'on' : 'off';
            
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

            /*
            Services
            */
            
            //MusicBrainz
            $new_input['musicbrainz_enabled'] = ( isset($input['musicbrainz_enabled']) ) ? 'on' : 'off';
            $new_input['mb_auto_id'] = ( isset($input['mb_auto_id']) ) ? 'on' : 'off';

            //Last.fm 
            $new_input['lastfm_client_id'] = ( isset($input['lastfm_client_id']) ) ? trim($input['lastfm_client_id']) : null;
            $new_input['lastfm_client_secret'] = ( isset($input['lastfm_client_secret']) ) ? trim($input['lastfm_client_secret']) : null;
            $new_input['lastfm_scrobbling'] = ( isset($input['lastfm_scrobbling']) ) ? 'on' : 'off';
            $new_input['lastfm_favorites'] = ( isset($input['lastfm_favorites']) ) ? 'on' : 'off';
            
            //tuneefy
            $new_input['tuneefy_client_id'] = ( isset($input['tuneefy_client_id']) ) ? trim($input['tuneefy_client_id']) : null;
            $new_input['tuneefy_client_secret'] = ( isset($input['tuneefy_client_secret']) ) ? trim($input['tuneefy_client_secret']) : null;

            //spotify
            $new_input['spotify_client_id'] = ( isset($input['spotify_client_id']) ) ? trim($input['spotify_client_id']) : null;
            $new_input['spotify_client_secret'] = ( isset($input['spotify_client_secret']) ) ? trim($input['spotify_client_secret']) : null;
            
            //soundcloud
            $new_input['soundcloud_client_id'] = ( isset($input['soundcloud_client_id']) ) ? trim($input['soundcloud_client_id']) : null;
            $new_input['soundcloud_client_secret'] = ( isset($input['soundcloud_client_secret']) ) ? trim($input['soundcloud_client_secret']) : null;

            /*
            System
            */

        }
        
        //remove default values
        foreach((array)$input as $slug => $value){
            $default = wpsstm()->get_default_option($slug);
            if ($value == $default) unset ($input[$slug]);
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
        Services
        */
        
        add_settings_section(
            'settings_apis', // ID
            __('Services','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'musicbrainz_client', 
            __('Musicbrainz','wpsstm'), 
            array( $this, 'musicbrainz_service_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_apis'//section
        );
        
        add_settings_field(
            'lastfm_client', 
            __('Last.fm','wpsstm'), 
            array( $this, 'lastfm_service_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_apis'//section
        );

        /*
        add_settings_field(
            'tuneefy_client', 
            __('Tuneefy'), 
            array( $this, 'tuneefy_client_callback' ), 
            'wpsstm-settings-page', 
            'settings_apis'
        );
        */

        add_settings_field(
            'spotify_client', 
            __('Spotify'), 
            array( $this, 'spotify_client_callback' ), 
            'wpsstm-settings-page', 
            'settings_apis'
        );
        
        add_settings_field(
            'soundcloud_client', 
            __('Soundcloud'), 
            array( $this, 'soundcloud_client_id_callback' ), 
            'wpsstm-settings-page', 
            'settings_apis'
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

        /*
        System
        */

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
        
        add_settings_field(
            'flush_tracks', 
            __('Flush Community Tracks','wpsstm'), 
            array( $this, 'flush_tracks_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_system'//section
        );

    }
    
    function section_desc_empty(){
        
    }
    
    function musicbrainz_service_callback(){
        $this->musicbrainz_enabled_callback();
        $this->mb_auto_id_callback();
    }

    function musicbrainz_enabled_callback(){
        $option = wpsstm()->get_options('musicbrainz_enabled');
        $mb_link = '<a href="https://musicbrainz.org/" target="_blank">MusicBrainz</a>';
        $desc = sprintf(__('%s is an open data music database.  By enabling it, the plugin will fetch various informations about the tracks, artists and albums you post.','wpsstm'),$mb_link);
        printf('<p><small>%s</small></p>',$desc);
        
        $el = sprintf(
            '<input type="checkbox" name="%s[musicbrainz_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Enable MusicBrainz.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }

    function mb_auto_id_callback(){
        $option = wpsstm()->get_options('mb_auto_id');
        
        $el = sprintf(
            '<input type="checkbox" name="%s[mb_auto_id]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Auto-lookup MusicBrainz IDs.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }
    
    function player_enabled_callback(){
        $option = wpsstm()->get_options('player_enabled');
        $desc = '';
        
        printf(
            '<input type="checkbox" name="%s[player_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            $desc
        );
    }
    
    function autoplay_callback(){
        $option = wpsstm()->get_options('autoplay');

        printf(
            '<input type="checkbox" name="%s[autoplay]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Autoplay the first track displayed.","wpsstm")
        );
    }

    function autosource_callback(){
        
        $enabled = ( wpsstm()->get_options('autosource') == 'on' );

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
            checked( $option, 'on', false ),
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

    //APIs
    
    function lastfm_service_callback(){
        $this->lastfm_auth_callback();
        $this->lastfm_scrobbling_callback();
        $this->lastfm_favorites_callback();
        $this->lastfm_community_scrobble_callback();
    }
    
    function lastfm_auth_callback(){
        $client_id = wpsstm()->get_options('lastfm_client_id');
        $client_secret = wpsstm()->get_options('lastfm_client_secret');
        $new_app_url = 'https://www.last.fm/api/account/create';
        
        $api_link = sprintf('<a href="%s" target="_blank">%s</a>',$new_app_url,__('here','wpsstm') );
        $desc = sprintf(__('Required for the Last.fm preset and Last.fm features.  Get an API account %s.','wpsstm'),$api_link );
        printf('<p><small>%s</small></p>',$desc);

        //client ID
        $client_el = sprintf(
            '<p><label>%s</label> <input type="text" name="%s[lastfm_client_id]" value="%s" /></p>',
            __('Api key:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        $secret_el = sprintf(
            '<p><label>%s</label> <input type="text" name="%s[lastfm_client_secret]" value="%s" /></p>',
            __('Shared secret:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );
        printf('<div>%s%s</div>',$client_el,$secret_el);
    }
    
    function lastfm_scrobbling_callback(){
        $option = wpsstm()->get_options('lastfm_scrobbling');

        $el = sprintf(
            '<input type="checkbox" name="%s[lastfm_scrobbling]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Allow users to scrobble songs to their Last.fm account.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }
    
    function lastfm_favorites_callback(){
        $option = wpsstm()->get_options('lastfm_favorites');
        
        $el = sprintf(
            '<input type="checkbox" name="%s[lastfm_favorites]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("When a track is favorited/unfavorited, love/unlove it on Last.fm.","wpsstm")
        );
        
        printf('<p>%s</p>',$el);
    }
    
    function lastfm_community_scrobble_callback(){
        
        $enabled = ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' );
        
        /*
        form
        */

        $help = array();
        $help[]= __("Each time a user scrobbles a song to Last.fm, do scrobble it along with the community user.","wpsstm");
        
        $el = sprintf(
            '<input type="checkbox" name="%s[lastfm_community_scrobble]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $enabled,true, false ),
            implode('  ',$help)
        );
        
        /*
        errors
        */

        if ( $enabled ){
            
            $can_scrobble_along = WPSSTM_LastFM::can_scrobble_along();
            if ( is_wp_error($can_scrobble_along) ){
                $error = sprintf( __( 'Cannot scrobble along: %s','wpsstm'),$can_scrobble_along->get_error_message() );
                add_settings_error('lastfm_scrobble_along', 'cannot_scrobble_along',$error,'inline');
            }
            
        }
        
        printf('<p>%s</p>',$el);
        
        //display settings errors
        settings_errors('lastfm_scrobble_along');
    }
    
    function tuneefy_client_callback(){
        $client_id = wpsstm()->get_options('tuneefy_client_id');
        $client_secret = wpsstm()->get_options('tuneefy_client_secret');
        $new_app_link = 'https://data.tuneefy.com/#header-oauth';
        
        $desc = sprintf(__('Required for autosourcing. Request your Tuneefy credentials %s.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
        printf('<p><small>%s</small></p>',$desc);
        
        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[tuneefy_client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[tuneefy_client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );
    }

    function spotify_client_callback(){
        $client_id = wpsstm()->get_options('spotify_client_id');
        $client_secret = wpsstm()->get_options('spotify_client_secret');
        $new_app_link = 'https://developer.spotify.com/my-applications/#!/applications/create';
        
        $desc = sprintf(__('Required for the Live Playlists Spotify preset.  Create a Spotify application %s to get the required informations.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
        printf('<p><small>%s</small></p>',$desc);
        
        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[spotify_client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[spotify_client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );
    }
    
    function soundcloud_client_id_callback(){
        $client_id = wpsstm()->get_options('soundcloud_client_id');
        $client_secret = wpsstm()->get_options('soundcloud_client_secret');
        
        $new_app_link = 'http://soundcloud.com/you/apps/new';
        
        $desc = sprintf(__('Required for the Live Playlists Soundcloud preset.  Create a Soundcloud application %s to get the required informations.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
        printf('<p><small>%s</small></p>',$desc);

        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[soundcloud_client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[soundcloud_client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );
        
    }

    /*System*/
    
    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%s[reset_options]" value="on"/> %s',
            wpsstm()->meta_name_options,
            __("Reset options to their default values.","wpsstm")
        );
    }
    
    function flush_tracks_callback(){
        $desc = __("Delete community tracks.","wpsstm");
        $desc.= sprintf( ' <small>%s</small>',__("(Only tracks that do not belong to any playlist or user likes)","wpsstm") );
        printf(
            '<input type="checkbox" name="%s[flush_community_tracks]" value="on"/> %s',
            wpsstm()->meta_name_options,
            $desc
        );
    }

    
	function  settings_page() {
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