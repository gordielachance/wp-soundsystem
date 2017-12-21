<?php

class WP_SoundSystem_Settings {
    
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
                wpsstm_tracks()->flush_community_tracks();
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
            
            
            if( isset($input['autosource_filter_ban_words']) ){
                $ban_words = explode(',',$input['autosource_filter_ban_words']);
                foreach($ban_words as $key=>$word){
                    $ban_words[$key] = trim($word);
                }
                $new_input['autosource_filter_ban_words'] = $ban_words;
            }

            
            $new_input['autosource_filter_requires_artist'] = ( isset($input['autosource_filter_requires_artist']) ) ? 'on' : 'off';

            /*
            Live playlists
            */
            
            $new_input['live_playlists_enabled'] = ( isset($input['live_playlists_enabled']) ) ? 'on' : 'off';
            
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
            Musicbrainz 
            */
            
            $new_input['musicbrainz_enabled'] = ( isset($input['musicbrainz_enabled']) ) ? 'on' : 'off';
            $new_input['mb_auto_id'] = ( isset($input['mb_auto_id']) ) ? 'on' : 'off';
            $new_input['mb_suggest_bookmarks'] = ( isset($input['mb_suggest_bookmarks']) ) ? 'on' : 'off';
            
            /* 
            Last.fm 
            */
            $new_input['lastfm_client_id'] = ( isset($input['lastfm_client_id']) ) ? trim($input['lastfm_client_id']) : null;
            $new_input['lastfm_client_secret'] = ( isset($input['lastfm_client_secret']) ) ? trim($input['lastfm_client_secret']) : null;
            $new_input['lastfm_scrobbling'] = ( isset($input['lastfm_scrobbling']) ) ? 'on' : 'off';
            $new_input['lastfm_favorites'] = ( isset($input['lastfm_favorites']) ) ? 'on' : 'off';

            /*
            Other APIs
            */
            
            //spotify
            $new_input['spotify_client_id'] = ( isset($input['spotify_client_id']) ) ? trim($input['spotify_client_id']) : null;
            $new_input['spotify_client_secret'] = ( isset($input['spotify_client_secret']) ) ? trim($input['spotify_client_secret']) : null;
            
            //soundcloud
            $new_input['soundcloud_client_id'] = ( isset($input['soundcloud_client_id']) ) ? trim($input['soundcloud_client_id']) : null;
            $new_input['soundcloud_client_secret'] = ( isset($input['soundcloud_client_secret']) ) ? trim($input['soundcloud_client_secret']) : null;
            
            /*
            Styling
            */
            $new_input['minimal_css'] = ( isset($input['minimal_css']) ) ? 'on' : 'off';
            $new_input['playable_opacity_class'] = ( isset($input['playable_opacity_class']) ) ? 'on' : 'off';
            
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
            array( $this, 'section_tracklists_desc' ), // Callback
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
        
        add_settings_field(
            'autosource', 
            __('Autosource','wpsstm'), 
            array( $this, 'autosource_callback' ), 
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
            'autosource_filter_ban_words', 
            __('Ban words filter','wpsstm'), 
            array( $this, 'autosource_filter_ban_words_callback' ), 
            'wpsstm-settings-page', 
            'sources'
        );
        
        add_settings_field(
            'autosource_filter_requires_artist', 
            __('Artist filter','wpsstm'),
            array( $this, 'autosource_filter_requires_artist_callback' ), 
            'wpsstm-settings-page', 
            'sources'
        );

        /*
        Live Playlists
        */
        
        add_settings_section(
            'live_playlists_settings', // ID
            __('Live Playlists','wpsstm'), // Title
            array( $this, 'section_live_playlists_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'live_playlists_enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'live_playlists_enabled_callback' ), 
            'wpsstm-settings-page', 
            'live_playlists_settings'
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
        MusicBrainz
        */

        add_settings_section(
            'settings-musicbrainz', // ID
            __('MusicBrainz','wpsstm'), // Title
            array( $this, 'section_musicbrainz_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'musicbrainz_enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'musicbrainz_enabled_callback' ), 
            'wpsstm-settings-page', // Page
            'settings-musicbrainz'//section
        );

        add_settings_field(
            'mb_auto_id', 
            __('MusicBrainz auto ID','wpsstm'), 
            array( $this, 'mb_auto_id_callback' ), 
            'wpsstm-settings-page', // Page
            'settings-musicbrainz'//section
        );

        add_settings_field(
            'mb_suggest_bookmarks', 
            __('Suggest links','wpsstm'), 
            array( $this, 'mb_suggest_bookmarks_callback' ), 
            'wpsstm-settings-page', // Page
            'settings-musicbrainz'//section
        );
        
        /*
        Last.fm
        */
        
        add_settings_section(
            'lastfm_settings', // ID
            'Last.fm', // Title
            array( $this, 'section_lastfm_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'lastfm_client_id', 
            __('API','wpsstm'), 
            array( $this, 'lastfm_client_callback' ), 
            'wpsstm-settings-page', 
            'lastfm_settings'
        );
        
        add_settings_field(
            'lastfm_scrobbling', 
            __('Scrobbling','wpsstm'), 
            array( $this, 'lastfm_scrobbling_callback' ), 
            'wpsstm-settings-page', 
            'lastfm_settings'
        );
        
        add_settings_field(
            'lastfm_love', 
            __('Mark tracks as favorites','wpsstm'), 
            array( $this, 'lastfm_favorites_callback' ), 
            'wpsstm-settings-page', 
            'lastfm_settings'
        );
        
        add_settings_field(
            'lastfm_community_scrobble', 
            __('Scrobble along','wpsstm'), 
            array( $this, 'lastfm_community_scrobble_callback' ), 
            'wpsstm-settings-page', 
            'lastfm_settings'
        );
        
        /*
        APIs
        */
        
        add_settings_section(
            'settings_apis', // ID
            __('Other APIs','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
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
            'minimal_css', 
            __('Minimal CSS','wpsstm'), 
            array( $this, 'minimal_css_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_styling'//section
        );
        
        add_settings_field(
            'playable_opacity_class', 
            __('.playable-opacity','wpsstm'), 
            array( $this, 'playable_opacity_class_callback' ), 
            'wpsstm-settings-page', 
            'settings_styling'
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
    
    function section_musicbrainz_desc(){
        $mb_link = '<a href="https://musicbrainz.org/" target="_blank">Musicbrainz</a>';
        printf(__('%s is an open data music database.  By enabling it, the plugin will fetch various informations about the tracks, artists and albums you post with this plugin, and will for example try to get the unique MusicBrainz ID of each item.','wpsstm'),$mb_link);
    }
    
    function musicbrainz_enabled_callback(){
        $option = wpsstm()->get_options('musicbrainz_enabled');
        
        printf(
            '<input type="checkbox" name="%s[musicbrainz_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Enable MusicBrainz","wpsstm")
        );
    }
    
    function mb_suggest_bookmarks_callback(){
        $option = ( wpsstm()->get_options('mb_suggest_bookmarks') == "on" );
        $disabled = !class_exists( 'Post_Bookmarks' );
        
        printf(
            '<input type="checkbox" name="%s[mb_suggest_bookmarks]" value="on" %s %s /> %s %s',
            wpsstm()->meta_name_options,
            checked( $option, true, false ),
            disabled( $disabled, true, false ),
            __("Suggest links from MusicBrainz","wpsstm"),
            '— <small>'.sprintf(__("You'll need the %s plugin to enable this feature",'wpsstm'),'<a href="https://wordpress.org/plugins/post-bookmarks/" target="_blank">Custom Post Links</a>').'</small>'
        );
    }
    
    function mb_auto_id_callback(){
        $option = wpsstm()->get_options('mb_auto_id');
        
        printf(
            '<input type="checkbox" name="%s[mb_auto_id]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Try to guess MusicBrainz ID if for items if user left the MusicBrainz ID field empty.","wpsstm")
        );
        echo '  <small> ' . sprintf(__('Can be ignored by setting %s for input value.','wpsstm'),'<code>-</code>') . '</small>';
    }
    
    function section_tracklists_desc(){
        
        /*NOTICES*/
        
        //autosource
        $autosource_enabled = ( wpsstm()->get_options('autosource') == 'on' );
        
        if ( $autosource_enabled ){
        
            if ( !$community_user_id = wpsstm()->get_options('community_user_id') ){

                add_settings_error( 'wpsstm-settings-tracklists', 'community-user-id-required', __("A Community user ID is required if you want to enable the tracks autosource feature.",'wpsstm') );

            }else{

                //cap missing
                if ( !$can_autosource = wpsstm_sources()->can_autosource() ){
                    
                    $sources_post_type_obj = get_post_type_object(wpsstm()->post_type_source);
                    $autosource_cap = $sources_post_type_obj->cap->edit_posts;
                    
                    add_settings_error( 'wpsstm-settings-tracklists', 'community-user-cap-missing', sprintf(__("Autosource requires the community user to have the %s capability granted.",'wpsstm'),'<em>'.$autosource_cap.'</em>') );
                }

            }
            
        }
        
        //display settings errors
        settings_errors('wpsstm-settings-tracklists');
        
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
    
    function autosource_filter_ban_words_callback(){

        $desc = array();
        $desc[]= sprintf(
            '<strong>'.__("Experimental","wpsstm").'</strong> '.__("Ignore an autosource when one of those words is contained in its title and isn't in the track title.","wpsstm")
        );
        
        $desc[]= sprintf('<small>%s</small>',__('List of comma-separated words'));
        
        $ban_words = wpsstm()->get_options('autosource_filter_ban_words');
        $ban_words_str = implode(',',$ban_words);
        $desc[]= sprintf('<input type="text" name="%s[autosource_filter_ban_words]" value="%s" />',wpsstm()->meta_name_options,$ban_words_str);
        
        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        echo implode("\n",$desc);
        
    }
    
    function autosource_callback(){
        $option = wpsstm()->get_options('autosource');

        printf(
            '<input type="checkbox" name="%s[autosource]" value="on" %s /> %s <small>%s</small>',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("If no source is set for a track, try to find an online source automatically.","wpsstm"),
            __("This requires the community user ID to be set.","wpsstm")
        );
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
    
    function autosource_filter_requires_artist_callback(){
        $option = wpsstm()->get_options('autosource_filter_requires_artist');

        printf(
            '<input type="checkbox" name="%s[autosource_filter_requires_artist]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            '<strong>'.__("Experimental","wpsstm").'</strong> '.__("Ignore an autosource when the track artist is not contained in its title and isn't in the track title.","wpsstm")
        );
    }
    
    function section_lastfm_desc(){
        $api_link = sprintf('<a href="%s" target="_blank">%s</a>','https://www.last.fm/api/account/create',__('here','wpsstm') );
        printf(__('Required for the Last.fm preset and Last.fm features.  Get an API account %s.','wpsstm'),$api_link );
            
        /*
        SCROBBLE ALONG
        */
        
        $scrobble_along_enabled = ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' );
        
        if ( $scrobble_along_enabled ){

            if ( !$community_user_id = wpsstm()->get_options('community_user_id') ){

                add_settings_error( 'wpsstm-settings-lastfm', 'community-user-id-required', __("A Community user ID is required if you want to enable the scrobble along feature.",'wpsstm') );

            }else{

                //cap missing
                if ( !$can_community_scrobble = wpsstm_lastfm()->can_community_scrobble() ){

                    add_settings_error( 'wpsstm-settings-lastfm', 'community-user-cap-missing', __("Last.fm scrobble along requires the community user to be authentificated to Last.fm.",'wpsstm') );
                }

            }
            
        }
        
        //display settings errors
        settings_errors('wpsstm-settings-lastfm');
            
    }
    
    function lastfm_scrobbling_callback(){
        $option = wpsstm()->get_options('lastfm_scrobbling');

        printf(
            '<input type="checkbox" name="%s[lastfm_scrobbling]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Allow users to scrobble songs to their Last.fm account.","wpsstm")
        );
    }
    
    function lastfm_favorites_callback(){
        $option = wpsstm()->get_options('lastfm_favorites');
        
        printf(
            '<input type="checkbox" name="%s[lastfm_favorites]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Allow users to mark tracks as favorites and sync them with their Last.fm account.","wpsstm")
        );
    }
    
    function lastfm_community_scrobble_callback(){
        $option = wpsstm()->get_options('lastfm_community_scrobble');
        
        $help = array();
        $help[]= __("Each time a user scrobbles a song to Last.fm, do scrobble along with the community user.","wpsstm");
        $help[]= sprintf( "<small>%s</small>",__("0 = Disabled.","wpsstm") );
        $help[]= sprintf( "<small>%s</small>",__("(You need to have authorized the community user to Last.fm)","wpsstm") );
        
        printf(
            '<input type="checkbox" name="%s[lastfm_community_scrobble]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            implode('  ',$help)
        );
    }
    
    function section_live_playlists_desc(){
        
        _e('Live Playlists lets you grab a tracklist from a remote URL (eg. a radio station page); and will stay synchronized with its source : it will be updated each time someone access the Live Playlist post.','wppsm');
        
        $live_playlists_enabled = ( wpsstm()->get_options('live_playlists_enabled') == 'on' );
        
        if ( $live_playlists_enabled ){

            $can_live_playlists = wpsstm_live_playlists()->can_live_playlists();
            $live_playlist_post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);

            if ( !$community_user_id = wpsstm()->get_options('community_user_id') ){

                add_settings_error( 'wpsstm-settings-live-playlists', 'community-user-id-required', __("A Community user ID is required if you want to enable the live playlists feature.",'wpsstm') );

            }else{

                //cap missing
                if(!$can_live_playlists){
                    $live_playlists_cap = $live_playlist_post_type_obj->cap->edit_posts;
                    add_settings_error( 'wpsstm-settings-live-playlists', 'community-user-cap-missing', sprintf(__("Live Playlists requires the community user to have the %s capability granted",'wpsstm'),'<em>'.$live_playlists_cap.'</em>') );
                }

            }
            
        }
        
        //display settings errors
        settings_errors('wpsstm-settings-live-playlists');
        
    }

    function live_playlists_enabled_callback(){
        $option = wpsstm()->get_options('live_playlists_enabled');
        
        printf(
            '<input type="checkbox" name="%s[live_playlists_enabled]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Enable Live Playlists","wpsstm")
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
        $option = (int)wpsstm()->get_options('community_user_id');

        printf(
            '<input type="number" name="%s[community_user_id]" size="4" min="0" value="%s" />',
            wpsstm()->meta_name_options,
            $option
        );
    }

    //APIs
    
    function lastfm_client_callback(){
        $client_id = wpsstm()->get_options('lastfm_client_id');
        $client_secret = wpsstm()->get_options('lastfm_client_secret');
        $new_app_link = 'https://www.last.fm/api/account/create';

        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[lastfm_client_id]" value="%s" /></p>',
            __('Api key:','wpsstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[lastfm_client_secret]" value="%s" /></p>',
            __('Shared secret:','wpsstm'),
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
    
    /*Styling*/
    
    function minimal_css_callback(){
        $option = wpsstm()->get_options('minimal_css');
        
        printf(
            '<input type="checkbox" name="%s[minimal_css]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Do not include default styling.","wpsstm")
        );
    }

    function playable_opacity_class_callback(){
        $option = wpsstm()->get_options('playable_opacity_class');
        $help = sprintf(__('not playable:%s, playable:%s ,has played/hover:%s, active:%s','wpsstm'),'.25','.5','.75','1');
        
        printf(
            '<input type="checkbox" name="%s[playable_opacity_class]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Toggle tracks opacity according to the playable state.","wpsstm") . sprintf(' <small>%s</small>',$help)
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

new WP_SoundSystem_Settings;