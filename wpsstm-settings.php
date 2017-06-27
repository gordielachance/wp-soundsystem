<?php

class WP_SoundSytem_Settings {
    
    static $menu_slug = 'wpsstm';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 9 );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_submenus' ), 9 );
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

    }
    
    function register_admin_submenus(){ //TO FIX - this function should be under each type of item ?
        
        $menu_slug = WP_SoundSytem_Settings::$menu_slug;
        
        $allowed_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );

        foreach ($allowed_post_types as $post_type_slug){

            $post_type = get_post_type_object($post_type_slug);
            if (!$post_type) continue;

             add_submenu_page(
                    $menu_slug,
                    $post_type->labels->name, //page title - I never understood why this parameter is needed for.  Put what you like ?
                    $post_type->labels->name, //submenu title
                    'edit_posts',
                    sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug

             );
            /*
             add_submenu_page(
                    $menu_slug,
                    $post_type->labels->add_new_item,
                    $post_type->labels->add_new_item,
                    'edit_posts',
                    sprintf('post-new.php?post_type=%s',$post_type_slug) //url or slug

             );
             */
            
        }
    }
    
    function settings_sanitize( $input ){
        $new_input = array();
        
        //delete transients
        if( isset( $input['delete_transients'] ) ){
            $transients = wpsstm_get_transients_by_prefix( 'wpsstm' );

            //TO FIX use a mysql command for this ?  Crashes when there is too much transient.
            foreach((array)$transients as $transient_name){
                delete_transient( $transient_name );
            }
            
        }

        if( isset( $input['reset_options'] ) ){
            
            $new_input = wpsstm()->options_default;
            
        }else{ //sanitize values
            
            /*
            Player
            */
            
            $new_input['player_enabled'] = ( isset($input['player_enabled']) ) ? 'on' : 'off';
            $new_input['autoplay'] = ( isset($input['autoplay']) ) ? 'on' : 'off';

            /*
            Sources
            */
            $new_input['autosource'] = ( isset($input['autosource']) ) ? 'on' : 'off';
            
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
            
            //scraper page ID
            if ( isset ($input['frontend_scraper_page_id']) && ctype_digit($input['frontend_scraper_page_id']) ){
                if ( is_string( get_post_status( $input['frontend_scraper_page_id'] ) ) ){ //check page exists
                    $new_input['frontend_scraper_page_id'] = $input['frontend_scraper_page_id'];
                    flush_rewrite_rules(); //because of function frontend_wizard_rewrite()
                }
                
            }
            //guest user ID
            if ( isset ($input['guest_user_id']) && ctype_digit($input['guest_user_id']) ){
                if ( get_userdata( $input['guest_user_id'] ) ){ //check user exists
                    $new_input['guest_user_id'] = $input['guest_user_id'];
                }
            }

            //cache duration
            if ( isset ($input['live_playlists_cache_min']) && ctype_digit($input['live_playlists_cache_min']) ){
                $new_input['live_playlists_cache_min'] = $input['live_playlists_cache_min'];
            }
            
            /* 
            Musicbrainz 
            */
            
            $new_input['musicbrainz_enabled'] = ( isset($input['musicbrainz_enabled']) ) ? 'on' : 'off';
            $new_input['mb_auto_id'] = ( isset($input['mb_auto_id']) ) ? 'on' : 'off';
            $new_input['mb_suggest_bookmarks'] = ( isset($input['mb_suggest_bookmarks']) ) ? 'on' : 'off';
            
            /* 
            Last.FM 
            */
            $new_input['lastfm_client_id'] = ( isset($input['lastfm_client_id']) ) ? trim($input['lastfm_client_id']) : null;
            $new_input['lastfm_client_secret'] = ( isset($input['lastfm_client_secret']) ) ? trim($input['lastfm_client_secret']) : null;
            $new_input['lastfm_scrobbling'] = ( isset($input['lastfm_scrobbling']) ) ? 'on' : 'off';
            $new_input['lastfm_favorites'] = ( isset($input['lastfm_favorites']) ) ? 'on' : 'off';
            
            //Lastfm bot
            if ( isset ($input['lastfm_bot_user_id']) && ctype_digit($input['lastfm_bot_user_id']) ){
                if ( get_userdata( $input['lastfm_bot_user_id'] ) ){ //check user exists
                    $new_input['lastfm_bot_user_id'] = $input['lastfm_bot_user_id'];
                }
            }

            /*
            Other APIs
            */
            
            //spotify
            $new_input['spotify_client_id'] = ( isset($input['spotify_client_id']) ) ? trim($input['spotify_client_id']) : null;
            $new_input['spotify_client_secret'] = ( isset($input['spotify_client_secret']) ) ? trim($input['spotify_client_secret']) : null;
            
            //soundcloud
            $new_input['soundcloud_client_id'] = ( isset($input['soundcloud_client_id']) ) ? trim($input['soundcloud_client_id']) : null;
            $new_input['soundcloud_client_secret'] = ( isset($input['soundcloud_client_secret']) ) ? trim($input['soundcloud_client_secret']) : null;
    
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
        Tracklists
        
        add_settings_section(
            'tracklist_settings', // ID
            __('Tracklists','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        */
        
        /*
        Player
        */
        add_settings_section(
            'player_settings', // ID
            __('Audio Player','wpsstm'), // Title
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
            'autoplay', 
            __('Auto-play','wpsstm'), 
            array( $this, 'autoplay_callback' ), 
            'wpsstm-settings-page', 
            'player_settings'
        );
        
        
        /*
        Sources
        */
        
        add_settings_field(
            'autosource', 
            __('Auto-source','wpsstm'), 
            array( $this, 'autosource_callback' ), 
            'wpsstm-settings-page', 
            'sources'
        );
        
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
            'guest_user_id', 
            __('Guest ID','wpsstm'), 
            array( $this, 'guest_user_id_callback' ), 
            'wpsstm-settings-page', 
            'frontend_wizard_settings'
        );

        add_settings_field(
            'cache_tracks_intval', 
            __('Playlist cache duration','wpsstm'), 
            array( $this, 'live_playlists_cache_callback' ), 
            'wpsstm-settings-page', 
            'live_playlists_settings'
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
        Last.FM
        */
        
        add_settings_section(
            'lastfm_settings', // ID
            'Last.FM', // Title
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
            'lastfm_bot_user_id', 
            __('Last.fm bot ID','wpsstm'), 
            array( $this, 'lastfm_bot_user_id_callback' ), 
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
        System
        */

        add_settings_section(
            'settings_system', // ID
            __('System','wpsstm'), // Title
            array( $this, 'section_desc_empty' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'delete_transients', 
            __('Delete Transients','wpsstm'), 
            array( $this, 'delete_transients_callback' ), 
            'wpsstm-settings-page', // Page
            'settings_system'//section
        );
        
        //
        add_settings_field(
            'reset_options', 
            __('Reset Options','wpsstm'), 
            array( $this, 'reset_options_callback' ), 
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
    
    function player_enabled_callback(){
        $option = wpsstm()->get_options('player_enabled');
        
        $buglink = sprintf('<a target="_blank" href="%s">%s</a>','https://core.trac.wordpress.org/ticket/39686',__('this bug','wpsstm'));
        $desc = sprintf( __('Requires Wordpress 4.8 - see %s.','wppsm'),$buglink);
        $desc = sprintf('— <small>%s</small>',$desc);
        
        printf(
            '<input type="checkbox" name="%s[player_enabled]" value="on" %s /> %s %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Enable Audio Player","wpsstm"),
            $desc
        );
    }
    
    function autoplay_callback(){
        $option = wpsstm()->get_options('autoplay');

        printf(
            '<input type="checkbox" name="%s[autoplay]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Auto-play the first track displayed.","wpsstm")
        );
    }
    
    function autosource_filter_ban_words_callback(){

        $desc[]= sprintf(
            '<strong>'.__("Experimental","wpsstm").'</strong> '.__("Ignore an auto-source when one of those words is contained in its title","wpsstm")
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
    function autosource_filter_requires_artist_callback(){
        $option = wpsstm()->get_options('autosource_filter_requires_artist');

        printf(
            '<input type="checkbox" name="%s[autosource_filter_requires_artist]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            '<strong>'.__("Experimental","wpsstm").'</strong> '.__("Ignore an auto-source when the track artist is not contained in its title.","wpsstm")
        );
    }

    function autosource_callback(){
        $option = wpsstm()->get_options('autosource');

        printf(
            '<input type="checkbox" name="%s[autosource]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("If no source is set for the track, try to find an online source automatically.","wpsstm")
        );
    }
    
    function section_lastfm_desc(){
        $api_link = sprintf('<a href="%s" target="_blank">%s</a>','https://www.last.fm/api/account/create',__('here','wpsstm') );
        printf(__('Required for the Last.FM preset and Last.FM features.  Get an API account %s.','wpsstm'),$api_link );
    }
    
    function lastfm_scrobbling_callback(){
        $option = wpsstm()->get_options('lastfm_scrobbling');

        printf(
            '<input type="checkbox" name="%s[lastfm_scrobbling]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Allow users to scrobble songs to their Last.FM account.","wpsstm")
        );
    }
    
    function lastfm_favorites_callback(){
        $option = wpsstm()->get_options('lastfm_favorites');
        
        printf(
            '<input type="checkbox" name="%s[lastfm_favorites]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Allow users to mark tracks as favorites and sync them with their Last.FM account.","wpsstm")
        );
    }
    
    function lastfm_bot_user_id_callback(){
        $option = (int)wpsstm()->get_options('lastfm_bot_user_id');
        
        $help = array();
        $help[]= __("ID of Wordpress user to use for as Last.fm bot: each time a user scrobbles a song, do scrobble it with this account too.","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help[]= __("(You need to have authorized this account to Last.fm)","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));
        
        printf(
            '<input type="number" name="%s[lastfm_bot_user_id]" size="4" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
        );
    }
    
    function section_live_playlists_desc(){
        _e('Live Playlists lets you grab a tracklist from a remote URL (eg. a radio station page); and will stay synchronized with its source : it will be updated each time someone access the Live Playlist post.','wppsm');
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
    
    function section_frontend_wizard_desc(){
        _e('Setup a frontend page from which users will be able to load a remote tracklist.','wppsm');
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
    
    function guest_user_id_callback(){
        $option = (int)wpsstm()->get_options('guest_user_id');
        
        $help = array();
        $help[]= __("If the visitor is not logged, use this Wordpress user to create the loaded playlist.","wpsstm");
        $help[]= __("The capability 'edit_post' must be enabled for this user (Contributor role).","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));
        
        printf(
            '<input type="number" name="%s[guest_user_id]" size="4" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
        );
    }
    
    function live_playlists_cache_callback(){
        $option = (int)wpsstm()->get_options('live_playlists_cache_min');
        
        $help = array();
        $help[]= __("Number of minutes a playlist is cached before requesting the remote page again.","wpsstm");
        $help[]= __("0 = Disabled.","wpsstm");
        $help = sprintf("<small>%s</small>",implode('  ',$help));

        printf(
            '<input type="number" name="%s[live_playlists_cache_min]" size="4" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
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
            __('Api key:','wppstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[lastfm_client_secret]" value="%s" /></p>',
            __('Shared secret:','wppstm'),
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
            __('Client ID:','wppstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[spotify_client_secret]" value="%s" /></p>',
            __('Client Secret:','wppstm'),
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
            __('Client ID:','wppstm'),
            wpsstm()->meta_name_options,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[soundcloud_client_secret]" value="%s" /></p>',
            __('Client Secret:','wppstm'),
            wpsstm()->meta_name_options,
            $client_secret
        );
        
    }
    
    //System
    
    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%1$s[reset_options]" value="on"/> %2$s',
            wpsstm()->meta_name_options,
            __("Reset options to their default values.","wpsstm")
        );
    }

    function delete_transients_callback(){
        
        $transients = wpsstm_get_transients_by_prefix( 'wpsstm' );
        $transient_count = count($transients);
        $text_count = sprintf( _n( '%s transient currently stored', '%s transients currently stored', $transient_count, 'wpsstm' ), $transient_count );
        
        printf(
            '<input type="checkbox" name="%1$s[delete_transients]" value="on"/> %2$s',
            wpsstm()->meta_name_options,
            __('Clear the temporary data','wpsstm').' <small>('.$text_count.')</small>'
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

new WP_SoundSytem_Settings;