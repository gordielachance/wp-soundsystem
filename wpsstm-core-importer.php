<?php

class WPSSTM_Core_Importer{

    static $is_wizard_tracklist_metakey = '_wpsstm_is_wizard';

    function __construct(){

        //frontend
        add_action( 'wp', array($this,'handle_frontend_importer' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );
        add_filter( 'the_content', array($this,'frontend_importer_content'));

        //backend
        add_action( 'add_meta_boxes', array($this, 'metabox_importer_register'), 11 );
        add_action( 'save_post', array($this,'metabox_save_importer') );

        add_action( 'admin_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );

    }

    
    /*
    We're requesting the frontend wizard page, load the wizard template
    */
    
    function frontend_importer_content($content){
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return $content;
        
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
    
    function metabox_save_importer( $post_id ) {

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
        
        if ( !$data = wpsstm_get_array_value('wpsstm_wizard',$_POST) ) return;
        
        $tracklist = new WPSSTM_Post_Tracklist($post_id);

        $success = self::save_importer($tracklist,$data);

    }

    /*
    Create a tracklist from the frontend wizard search input and redirect to it.
    Set the community user as post author so we can detect it as a wizard tracklist.
    */

    function handle_frontend_importer(){
        
        global $wpsstm_tracklist;

        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return;
        if ( is_wp_error(wpsstm()->can_frontend_importer()) ) return;
        
        $url = wpsstm_get_array_value('wpsstm_frontend_wizard_url',$_POST);
        if (!$url) return;

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
        Check that this user already created a tracklist for that same search and redirect to it.
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
        Check that there is already a temporary wizard tracklist existing for that same search and redirect to it.
        */
        
        $community_duplicate_args = $duplicate_args;
        $community_duplicate_args['post_author'] = wpsstm()->get_options('community_user_id');

        $duplicate_query = new WP_Query( $community_duplicate_args );
        if ( $duplicate_query->have_posts() ){
            $existing_id = $duplicate_query->posts[0];
            $link = get_permalink($existing_id);
            wp_safe_redirect($link);
            exit;
        }

        /*
        Create a new tracklist and redirect to it
        */

        //store as wizard tracklist (author = community user / ->is_wizard_tracklist_metakey = true)

        $post_args = array(
            'post_type'     => wpsstm()->post_type_live_playlist,
            'post_status'   => 'publish',
            'post_author'   => wpsstm()->get_options('community_user_id'),
            'meta_input'   => array(
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
        $path = $wpsstm_tracklist->preset->get_selectors(array($selector,'path') );
        $path_default = wpsstm_get_array_value(array('selectors',$selector,'path'),$wpsstm_tracklist->preset->default_options);
        $disabled = ($path_default) ? disabled( $path, $path_default, false ) : null;
        $path = ( $path ? htmlentities($path) : null);

        //regex
        $regex = $wpsstm_tracklist->preset->get_selectors(array($selector,'regex') );
        $regex = ( $regex ? htmlentities($regex) : null);

        //attr
        $attr = $wpsstm_tracklist->preset->get_selectors(array($selector,'attr') );
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
                    case 'track_source_urls':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>audio source</code> '. sprintf( __('(set %s for attribute)','wpsstm'),'<code>src</code>') . ' ' . __('or an url','wpsstm')
                        );
                    break;
            }
            
            if ($selector!='tracks'){
                $tracks_prefix = $wpsstm_tracklist->preset->get_options(array('selectors','tracks','path'));

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
                'wpsstm_wizard',
                $selector,
                $path,
                $disabled
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
                                        'wpsstm_wizard',
                                        $selector,
                                        $attr,
                                        $disabled
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
                                        <span>~m</span>
                                        </p>',
                                        'wpsstm_wizard',
                                        $selector,
                                        $regex,
                                        $disabled
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

    static private function save_importer(WPSSTM_Post_Tracklist $tracklist,$wizard_data){
        $post_id = $tracklist->post_id;
        $post_type = get_post_type($post_id);

        if( !in_array($post_type,wpsstm()->tracklist_post_types ) ){
            return new WP_Error('wpsstm_invalid_tracklist',__('Invalid tracklist','wpsstm'));
        }
        
        //settings
        $db_settings = get_post_meta($post_id, WPSSTM_Post_Tracklist::$scraper_meta_name,true);
        $wizard_data = self::sanitize_importer_settings($wizard_data);

        //feed URL
        $feed_url = wpsstm_get_array_value('feed_url',$wizard_data);
        
        if ($feed_url){
            update_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name,$feed_url);
            unset($wizard_data['feed_url']);//we don't want to save it in the scraper settings
        }else{
            delete_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name);
        }

        //settings have been updated, clear tracklist cache
        if ($db_settings != $wizard_data){
            wpsstm()->debug_log('scraper settings have been updated, clear tracklist cache','Save wizard' );
            delete_post_meta($post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name);
        }

        if (!$wizard_data){
            $success = delete_post_meta($post_id, WPSSTM_Post_Tracklist::$scraper_meta_name);
        }else{
            $success = update_post_meta($post_id, WPSSTM_Post_Tracklist::$scraper_meta_name, $wizard_data);
        }
        
        //reload settings
        $tracklist->populate_tracklist_post();

        return $success;

    }

    /*
     * Sanitize wizard data
     */
    
    static function sanitize_importer_settings($input){

        $new_input = array();

        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //feed URL
        if ( isset($input['feed_url']) ){
            $new_input['feed_url'] = trim($input['feed_url']);
        }
        
        //cache
        if ( isset($input['remote_delay_min']) && ctype_digit($input['remote_delay_min']) ){
            $new_input['remote_delay_min'] = $input['remote_delay_min'];
        }

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

                $new_input['selectors'][$selector_slug] = array_filter($value);

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

    static function feedback_source_content_callback(){
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

}

function wpsstm_wizard_init(){
    new WPSSTM_Core_Importer();
}

add_action('wpsstm_init','wpsstm_wizard_init');