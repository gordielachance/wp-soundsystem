<?php

class WPSSTM_Core_Importer{

    static $is_wizard_tracklist_metakey = '_wpsstm_is_wizard';
    static $importer_links_transient_name = 'wpsstmapi_importers_links';

    function __construct(){

        //frontend
        add_action( 'wp', array($this,'handle_frontend_importer' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );
        add_filter( 'the_content', array($this,'frontend_importer_content'));
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_ignore_community_tracklists') );

        //backend
        add_action( 'add_meta_boxes', array($this, 'metabox_importer_register'), 11 );
        add_action( 'save_post', array($this,'metabox_save_importer_settings') );

        add_action( 'admin_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );

    }

    /*
    Usually, we don't want community playlists; it's only used by the importer.
    So ignore those playlists frontend.
    */
    
    function pre_get_posts_ignore_community_tracklists( $query ){

        //main query check
        if ( !$query->is_main_query() ) return $query;

        //archive check
        if ( $query->is_singular() ) return $query;
        
        //post type check
        $post_type = $query->get('post_type');
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return $query;
        
        //we HAVE an author query
        if ( $query->get('author') || $query->get('author_name') || $query->get('author__in') ) return $query;

        if ( !$community_id = wpsstm()->get_options('community_user_id') ) return $query;

        //ignore community posts
        $author_not_in = $query->get('author__not_in');
        $author_not_in[] = $community_id;
        $query->set('author__not_in',$author_not_in);

        return $query;
    }
    
    /*
    We're requesting the frontend wizard page, load the wizard template
    */
    
    function frontend_importer_content($content){
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return $content;
        
        //check community user
        $can_community = wpsstm()->is_community_user_ready();
        if ( is_wp_error($can_community) ){
            WP_SoundSystem::debug_log('Community user not ready','Frontend import template' );
            return $content;
        }
        
        ob_start();
        wpsstm_locate_template( 'frontend-importer.php', true, false );
        $wizard = ob_get_clean();
        return $content . $wizard;
    }

    function importer_register_scripts_styles(){
        
        $wp_scripts = wp_scripts();
        
        // JS
        wp_register_script( 'wpsstm-importer', wpsstm()->plugin_url . '_inc/js/wpsstm-importer.js',array('jquery','jquery-ui-tabs'),wpsstm()->version);

        //CSS
        wp_register_style( 'wpsstm-importer', wpsstm()->plugin_url . '_inc/css/wpsstm-importer.css',null,wpsstm()->version );

        ///
        if ( is_admin() ){
            wp_enqueue_script('wpsstm-importer');
        }
        wp_enqueue_style('wpsstm-importer');
    }

    function metabox_importer_register(){

        add_meta_box( 
            'wpsstm-metabox-importer', 
            __('Tracklist Importer','wpsstm'),
            array($this,'metabox_importer_display'),
            wpsstm()->tracklist_post_types, 
            'normal', //context
            'high' //priority
        );

    }

    function metabox_importer_display(){
        global $wpsstm_tracklist;
        wpsstm_locate_template( 'tracklist-importer.php', true );
    }
    
    function metabox_save_importer_settings( $post_id ) {
        global $wpsstm_tracklist;

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_tracklist_importer_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,wpsstm()->tracklist_post_types ) ){
            return new WP_Error('wpsstm_invalid_tracklist',__('Invalid tracklist','wpsstm'));
        }

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_tracklist_importer_meta_box_nonce'], 'wpsstm_tracklist_importer_meta_box' ) );
        if ( !$is_valid_nonce ) return;

        /////
        /////
        
        if ( !$data = wpsstm_get_array_value('wpsstm_importer',$_POST) ) return;

        $tracklist = new WPSSTM_Post_Tracklist($post_id);
        
        //feed URL
        $feed_url = wpsstm_get_array_value('feed_url',$data);

        if ($feed_url){
            update_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name,$feed_url);
        }else{
            delete_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name);
        }

        //website URL
        $website_url = wpsstm_get_array_value('website_url',$data);
        
        if ($website_url){
            update_post_meta( $post_id, WPSSTM_Post_Tracklist::$website_url_meta_name,$website_url);
        }else{
            delete_post_meta( $post_id, WPSSTM_Post_Tracklist::$website_url_meta_name);
        }
        
        /*
        importer
        */

        $importer_options = get_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name,true);
        $importer_data = self::sanitize_importer_settings($data);

        //settings have been updated, clear tracklist cache
        if ($importer_options != $importer_data){
            //TOUFIX OR if cache time has been updated ?
            WP_SoundSystem::debug_log('scraper settings have been updated, clear tracklist cache','Save wizard' );
            $tracklist->remove_cache_timestamp();
        }

        if (!$importer_data){
            $success = delete_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name);
        }else{
            $success = update_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name, $importer_data);
        }

        //reload settings
        $tracklist->populate_tracklist_post();

        return $success;

    }

    /*
    Create a tracklist from the frontend wizard search input and redirect to it.
    Set the community user as post author so we can detect it as a wizard tracklist.
    */

    function handle_frontend_importer(){
        
        global $wpsstm_tracklist;

        if ( !wpsstm()->get_options('frontend_scraper_page_id') ) return;
        
        $url = wpsstm_get_array_value('wpsstm_frontend_wizard_url',$_POST);
        if (!$url) return;

        //check community user
        $can_community = wpsstm()->is_community_user_ready();
        if ( is_wp_error($can_community) ){
            WP_SoundSystem::debug_log('Community user not ready','Frontend import URL' );
            return;
        }
        $community_id = wpsstm()->get_options('community_user_id');

        
        $duplicate_args = array(
            'post_type'         => wpsstm()->post_type_live_playlist,
            'fields'            => 'ids',
            'meta_query' => array(
                array(
                    'key' => WPSSTM_Post_Tracklist::$feed_url_meta_name,
                    'value' => $url
                )
            )
        );
        
        /*
        Check for radio duplicates, by user ID
        */
        if ( $user_id = get_current_user_id() ){
            
            $author_duplicate_args = $duplicate_args;
            $author_duplicate_args['post_author'] = $user_id;

            $duplicate_query = new WP_Query( $author_duplicate_args );
            if ( $duplicate_query->have_posts() ){
                $existing_id = $duplicate_query->posts[0];
                $link = get_permalink($existing_id);
                wp_safe_redirect($link);
                exit;
            }
        }


        /*
        Check for radio duplicates, by community user ID
        */

        $community_duplicate_args = $duplicate_args;
        $community_duplicate_args['post_author'] = $community_id;

        $duplicate_query = new WP_Query( $community_duplicate_args );
        if ( $duplicate_query->have_posts() ){
            $existing_id = $duplicate_query->posts[0];
            $link = get_permalink($existing_id);
            wp_safe_redirect($link);
            exit;
        }

        /*
        Create a new temporary radio and redirect to it
        */

        //store as wizard tracklist (author = community user / ->is_wizard_tracklist_metakey = true)

        $post_args = array(
            'post_type' =>      wpsstm()->post_type_live_playlist,
            'post_status' =>    'publish',
            'post_author' =>    $community_id,
            'meta_input' =>     array(
                WPSSTM_Post_Tracklist::$feed_url_meta_name => $url,
                self::$is_wizard_tracklist_metakey  => true,
            )
        );

        $success = wp_insert_post( $post_args, true );

        if ( is_wp_error($success) ){
            $link = get_permalink(wpsstm()->get_options('frontend_scraper_page_id'));
            $link = add_query_arg(array('wizard_error'=>$success->get_error_code()),$link);
            wp_safe_redirect($link);
            exit;
        }else{
            $post_id = $success;
            $link = get_permalink($post_id);
            wp_safe_redirect($link);
            exit;
        }
    }

    static function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="wpsstm-importer-selector-toggle-advanced"><i class="fa fa-cog" aria-hidden="true"></i></a>',
            __('Use Regular Expression','wpsstm')
        );
    }
    
    static function css_selector_block($selector){
        global $wpsstm_tracklist;
        
        //path
        $path = $wpsstm_tracklist->get_importer_options( array('selectors',$selector,'path') );
        $path_forced = null;//TOUFIX$wpsstm_tracklist->preset->get_preset_options(array('selectors',$selector,'path'));
        $path_disabled = disabled( (bool)$path_forced, true, false );
        $path = ( $path ? htmlentities($path) : null);


        //regex
        $regex = $wpsstm_tracklist->get_importer_options( array('selectors',$selector,'regex') );
        $regex_forced = null;//TOUFIX$wpsstm_tracklist->preset->get_preset_options(array('selectors',$selector,'regex'));
        $regex_disabled = disabled( (bool)$regex_forced, true, false );
        $regex = ( $regex ? htmlentities($regex) : null);

        //attr
        $attr = $wpsstm_tracklist->get_importer_options( array('selectors',$selector,'attr') );
        $attr_forced = null;//TOUFIX$wpsstm_tracklist->preset->get_preset_options(array('selectors',$selector,'attr'));
        $attr_disabled = disabled( (bool)$attr_forced, true, false );
        $attr = ( $attr ? htmlentities($attr) : null);

        ?>
        <div class="wpsstm-importer-selector">
            <?php

            //build info
        
            $info = null;

            switch($selector){
                    case 'track_artist':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>h4 .artist strong</code>'
                        );
                    break;
                    case 'track_title':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>span.track</code>'
                        );
                    break;
                    case 'track_album':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>span.album</code>'
                        );
                    break;
                    case 'track_image':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>a.album-art img</code> '. sprintf( __('(set %s for attribute)','wpsstm'),'<code>src</code>') . ' ' . __('or an url','wpsstm')
                        );
                    break;
                    case 'track_link_urls':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>audio link</code> '. sprintf( __('(set %s for attribute)','wpsstm'),'<code>src</code>') . ' ' . __('or an url','wpsstm')
                        );
                    break;
            }
            
            if ($selector!='tracks'){
                $tracks_prefix = $wpsstm_tracklist->get_importer_options(array('selectors','tracks','path'));

                if ($tracks_prefix){
                    printf(
                        '<span class="tracks-selector-prefix">%1$s</span>',
                        $tracks_prefix
                    );
                }

            }
        
            // if this is a preset default, set as readonly
        
            
            
            printf(
                '<input type="text" class="wpsstm-importer-selector-jquery" name="%s[selectors][%s][path]" value="%s" %s />',
                'wpsstm_importer',
                $selector,
                $path,
                $path_disabled
            );

            //regex
            //uses a table so the style matches with the global form (WP-core styling)
            ?>
            <div class="wpsstm-importer-selector-advanced">
                <?php
                if ($info){
                    printf('<p class="wpsstm-importer-track-selector-desc">%s</p>',$info);
                }
                ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Tag attribute','wpsstm');?></th>
                            <td>        
                                <div>
                                    <?php

                                    printf(
                                        '<span class="wpsstm-importer-selector-attr"><input name="%s[selectors][%s][attr]" type="text" value="%s" %s/></span>',
                                        'wpsstm_importer',
                                        $selector,
                                        $attr,
                                        $attr_disabled
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Regex pattern','wpsstm');?></th>
                            <td>        
                                <div>
                                    <?php

                                    printf(
                                        '<p class="wpsstm-importer-selector-regex">
                                        <span>~</span>
                                        <input class="regex" name="%s[selectors][%s][regex]" type="text" value="%s" %s />
                                        <span>~mi</span>
                                        </p>',
                                        'wpsstm_importer',
                                        $selector,
                                        $regex,
                                        $regex_disabled
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        
    }

    
    static function sanitize_importer_settings($input){

        $new_input = array();

        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //selectors 
        if ( isset($input['selectors']) && !empty($input['selectors']) ){
            
            foreach ($input['selectors'] as $selector_slug=>$value){

                //path
                if ( isset($value['path']) ) {
                    $value['path'] = trim($value['path']);
                }

                //attr
                if ( isset($value['attr']) ) {
                    $value['attr'] = trim($value['attr']);
                }

                //regex
                if ( isset($value['regex']) ) {
                    $value['regex'] = trim($value['regex']);
                }
                
                if ( $value = array_filter($value) ){
                    $new_input['selectors'][$selector_slug] = array_filter($value);
                }

            }
        }

        //order
        if ( isset($input['tracks_order']) ){
            $new_input['tracks_order'] = $input['tracks_order'];
        }

        return $new_input;
    }
    
    /*
    Feedback
    */
    
    static function feedback_preset(){
        global $wpsstm_tracklist;
        echo $wpsstm_tracklist->preset->get_preset_name();
    }
    
    
    static function feedback_data_type_callback(){
        global $wpsstm_tracklist;

        $output = "—";

        if ( $wpsstm_tracklist->preset->response_type ){
            $output = $wpsstm_tracklist->preset->response_type;
        }
        
        echo $output;

    }

    static function feedback_link_content_callback(){
        global $wpsstm_tracklist;

        $output = "—";

        if ( $body_node = $wpsstm_tracklist->preset->body_node ){
            
            $content = $body_node->html();

            //force UTF8
            $content = iconv("ISO-8859-1", "UTF-8", $content); //ISO-8859-1 is from QueryPath

            $content = esc_html($content);
            $output = '<pre class="wpsstm-raw"><code class="language-markup">'.$content.'</code></pre>';

        }
        
        echo $output;
        

    }

    static function feedback_tracks_callback(){
        global $wpsstm_tracklist;

        $output = "—"; //none
        $tracks_output = array();

        if ( $track_nodes = $wpsstm_tracklist->preset->track_nodes ){

            foreach ($track_nodes as $single_track_node){
                
                $single_track_html = $single_track_node->innerHTML();

                //force UTF8
                $single_track_html = iconv("ISO-8859-1", "UTF-8", $single_track_html); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="wpsstm-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($single_track_html) );

            }
            if ($tracks_output){

                $output = sprintf('<div id="wpsstm-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
            }

            
        }


        echo $output;

    }
    
    static function get_import_services(){
        
        $services = get_transient( self::$importer_links_transient_name );

        if (false === $services){
            $services = WPSSTM_Core_API::api_request('import/services/get');

            if ( is_wp_error($services) ) return false;

            set_transient( self::$importer_links_transient_name, $services, 1 * DAY_IN_SECONDS );
        }
        
        return $services;
    }

}

function wpsstm_wizard_init(){
    new WPSSTM_Core_Importer();
}

add_action('wpsstm_init','wpsstm_wizard_init');