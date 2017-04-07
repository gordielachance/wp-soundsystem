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
    
    function register_admin_submenus(){ //TO FIX - this function should be under wpsstm-item.php instead of each class , but then $post_type_slug is not found.
        
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

            foreach((array)$transients as $transient_name){
                delete_transient( $transient_name );
            }
            
        }

        if( isset( $input['reset_options'] ) ){
            
            $new_input = wpsstm()->options_default;
            
        }else{ //sanitize values

            //musicbrainz
            $new_input['musicbrainz_enabled'] = ( isset($input['musicbrainz_enabled']) ) ? 'on' : 'off';
            
            //auto guess
            $new_input['mb_auto_id'] = ( isset($input['mb_auto_id']) ) ? 'on' : 'off';
            
            //playlists
            $new_input['hide_subtracks'] = ( isset($input['hide_subtracks']) ) ? 'on' : 'off';
            
            /*
            live playlists
            */
            $new_input['live_playlists_enabled'] = ( isset($input['live_playlists_enabled']) ) ? 'on' : 'off';

            //cache duration
            
            if ( isset ($input['live_playlists_cache_min']) && ctype_digit($input['live_playlists_cache_min']) ){
                $new_input['live_playlists_cache_min'] = $input['live_playlists_cache_min'];
            }

            //post bookmarks
            $new_input['mb_suggest_bookmarks'] = ( isset($input['mb_suggest_bookmarks']) ) ? 'on' : 'off';
    
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
        
        add_settings_section(
            'settings_general', // ID
            __('General','wpsstm'), // Title
            array( $this, 'wpsstm_settings_general_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_section(
            'settings-musicbrainz', // ID
            __('MusicBrainz','wpsstm'), // Title
            array( $this, 'musicbrainz_section_desc' ), // Callback
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
        
        //playlists
        add_settings_section(
            'tracks_settings', // ID
            __('Tracks','wpsstm'), // Title
            array( $this, 'tracks_section_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'hide_subtracks', 
            __('Hide tracklist tracks','wpsstm'), 
            array( $this, 'hide_subtracks_callback' ), 
            'wpsstm-settings-page', 
            'tracks_settings'
        );
        
        //live playlists
        add_settings_section(
            'live_playlists_settings', // ID
            __('Live Playlists','wpsstm'), // Title
            array( $this, 'live_tracks_section_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'live_playlists_enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'live_playlists_enabled_callback' ), 
            'wpsstm-settings-page', 
            'live_playlists_settings'
        );

        add_settings_field(
            'cache_tracks_intval', 
            __('Playlist cache duration','wpsstm'), 
            array( $this, 'live_playlists_cache_callback' ), 
            'wpsstm-settings-page', 
            'live_playlists_settings'
        );
        


        add_settings_section(
            'settings_system', // ID
            __('System','wpsstm'), // Title
            array( $this, 'wpsstm_settings_system_desc' ), // Callback
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
    
    function wpsstm_settings_general_desc(){
        
    }
    
    function musicbrainz_enabled_callback(){
        $option = wpsstm()->get_options('musicbrainz_enabled');
        
        printf(
            '<input type="checkbox" name="%s[musicbrainz_enabled]" value="on" %s /> %s %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Enable MusicBrainz","wpsstm"),
            '— <small>'.sprintf(__('MusicBrainz is an open data music database.  By enabling it, the plugin will fetch various informations about the tracks, artists and albums you post with this plugin, and will for example try to get the unique MusicBrainz ID of each item.','wpsstm')).'</small>'
        );
    }
    
    function musicbrainz_section_desc(){
        
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
    
    function tracks_section_desc(){
        
    }
    
    function hide_subtracks_callback(){
        $option = wpsstm()->get_options('hide_subtracks');
        
        printf(
            '<input type="checkbox" name="%s[hide_subtracks]" value="on" %s /> %s',
            wpsstm()->meta_name_options,
            checked( $option, 'on', false ),
            __("Hide tracks that belongs to an album or a playlist from the tracks listing","wpsstm")
        );
    }
    
    function live_tracks_section_desc(){
        
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
    
    function live_playlists_cache_callback(){
        $option = (int)wpsstm()->get_options('live_playlists_cache_min');

        $help = '<small>'.__('Number of minutes a playlist is cached before requesting the remote page again. 0 = Disabled.','wpsstm').'</small>';
        
        printf(
            '<input type="number" name="%s[live_playlists_cache_min]" size="4" min="0" value="%s" /><br/>%s',
            wpsstm()->meta_name_options,
            $option,
            $help
        );
        
    }

    
    function wpsstm_settings_system_desc(){
        
    }
    
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