<?php

class WP_SoundSystem_Core_Wizard{

    var $is_advanced = false;

    var $wizard_sections  = array();
    var $wizard_fields = array();
    
    public $frontend_wizard_page_id = null;
    public $qvar_tracklist_wizard = 'wztr';
    public $wizard_disabled_metakey = '_wpsstm_wizard_disabled';
    public $is_wizard_tracklist_metakey = '_wpsstm_is_wizard';
    
    
    public $tracklist;

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Wizard;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        $this->frontend_wizard_page_id = (int)wpsstm()->get_options('frontend_scraper_page_id');
        $this->tracklist = new WP_SoundSystem_Remote_Tracklist();
    }

    function setup_actions(){
        
        add_filter( 'query_vars', array($this,'add_wizard_query_vars'));
        add_filter( 'page_rewrite_rules', array($this,'frontend_wizard_rewrite') );

        //frontend
        add_action( 'wp', array($this,'populate_frontend_wizard_tracklist'));
        add_action( 'wp', array($this,'frontend_wizard_create_from_search' ) );
        add_action( 'template_redirect', array($this,'community_tracklist_redirect'));
        add_filter( 'template_include', array($this,'frontend_wizard_template'));

        add_action( 'wp_enqueue_scripts', array( $this, 'wizard_register_scripts_style_shared' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'wizard_scripts_styles_frontend' ) );

        //backend
        add_action( 'admin_head', array($this, 'init_backend_wizard') );
        add_action( 'save_post', array($this, 'backend_wizard_save'));
        add_action( 'add_meta_boxes', array($this, 'metabox_scraper_wizard_register'), 11 );
        add_action( 'admin_enqueue_scripts', array( $this, 'wizard_register_scripts_style_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'wizard_scripts_styles_backend' ) );
        
    }
    
    /**
    *   Add the query variables for the Wizard
    */
    function add_wizard_query_vars($vars){
        $vars[] = $this->qvar_tracklist_wizard;
        return $vars;
    }
    
    /*
    Handle the XSPF endpoint for the frontend wizard page
    */
    
    function frontend_wizard_rewrite($rules){
        global $wp_rewrite;
        if ( !$this->frontend_wizard_page_id ) return $rules;
        
        $page_slug = get_post_field( 'post_name', $this->frontend_wizard_page_id );

        $wizard_rule = array(
            $page_slug . '/xspf/?' => sprintf('index.php?pagename=%s&%s=true',$page_slug,wpsstm_tracklists()->qvar_xspf)
        );

        return array_merge($wizard_rule, $rules);
    }

    function wizard_register_scripts_style_shared(){

        wp_register_style( 'wpsstm-wizard',  wpsstm()->plugin_url . '_inc/css/wpsstm-wizard.css',array('wpsstm-tracklists'),wpsstm()->version );

        // JS
        wp_register_script( 'wpsstm-wizard', wpsstm()->plugin_url . '_inc/js/wpsstm-wizard.js', array('jquery','jquery-ui-tabs','wpsstm-tracklists'),wpsstm()->version, true);
    }
    
    function wizard_scripts_styles_frontend(){
        
        $tracklist_admin_action = wpsstm_tracklists()->get_tracklist_action();
        if ( !is_page($this->frontend_wizard_page_id ) ) return;
        
        $this->wizard_enqueue_script_styles();
    }
    
    function wizard_scripts_styles_backend(){
        //TO FIX
        $screen = get_current_screen();
        $this->wizard_enqueue_script_styles();
    }
    
    function wizard_enqueue_script_styles(){
        wp_enqueue_style('wpsstm-wizard');
        wp_enqueue_script('wpsstm-wizard');
    }

    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper', 
            __('Remote Tracklist Manager','wpsstm'),
            array($this,'metabox_wizard_display'),
            wpsstm_tracklists()->tracklist_post_types, 
            'normal', //context
            'high' //priority
        );

    }

    function metabox_wizard_display(){
        global $post;

        wpsstm_wizard()->wizard_settings_init();
        wpsstm_locate_template( 'wizard-backend.php', true );
    }
    
    /*
    For wizard tracklists (created with the community user); 
    Redirect to wizard and pass tracklist ID as parameter
    
    If user try to hacks this (by passing a tracklist ID that is not a community post); redirect to regular tracklist.
    */
    
    function populate_frontend_wizard_tracklist(){
        global $post;
        global $wpsstm_tracklist;
        
        if ( !$this->can_frontend_wizard() ) return;
        if ( !is_page($this->frontend_wizard_page_id) ) return;
        
        //wizard search
        $wizard_data = ( isset($_POST['wpsstm_wizard']) ) ? $_POST['wpsstm_wizard'] : null;
        $input = isset($wizard_data['search']) ? trim($wizard_data['search']) : null;
        $this->tracklist = $this->get_wizard_tracklist(null,$input);

        if ($input && !$this->tracklist->feed_url){
            $link = get_permalink($this->frontend_wizard_page_id);
            $link = add_query_arg(array('wizard_error'=>'no-matching-preset'),$link);
            wp_redirect($link);
            exit();
        }
        
        $wpsstm_tracklist = $this->tracklist;

    }
    
    function community_tracklist_redirect(){
        global $post;
        global $wpsstm_tracklist;
        if ( is_page($this->frontend_wizard_page_id) ){
            //wizard called on a tracklist that is not a community one.  Redirect to regular tracklist.
            if ( ( $wztr = get_query_var($this->qvar_tracklist_wizard,null) ) && ( $wpsstm_tracklist = $this->get_wizard_tracklist($wztr) ) ){
                //this is not a community tracklist, abord wizard
                if (!$wpsstm_tracklist->is_community){
                    $link = get_permalink($wpsstm_tracklist->post_id);
                    wp_redirect($link);
                    exit();
                }
            }
        }

        //live playlist page but this is a community tracklist ! Redirect to wizard.
        if( is_singular( wpsstm()->post_type_live_playlist )  && ( $wpsstm_tracklist = $this->get_wizard_tracklist($post->ID) ) ){
            
            if ($wpsstm_tracklist && $wpsstm_tracklist->is_community){
                $link = get_permalink($this->frontend_wizard_page_id);
                $link = add_query_arg(array($this->qvar_tracklist_wizard=>$wpsstm_tracklist->post_id),$link);
                wp_redirect($link);
                exit();
            }
        }
        
        
    }

    /*
    We're requesting the frontend wizard page:
    load the wizard template
    eventually populate wizard tracklist
    */
    
    function frontend_wizard_template($template){
        global $post;
        global $wpsstm_tracklist;
        
        if ( !is_page($this->frontend_wizard_page_id) ) return $template;

        return wpsstm_locate_template( 'frontend-wizard.php' );
    }

    function get_wizard_tracklist($post_id=null,$input=null){

        if ($post_id){
            $this->tracklist = wpsstm_get_post_live_tracklist($post_id);
            
        }elseif($input){
            if ( $preset = wpsstm_get_live_tracklist_preset($input) ){
                $this->tracklist = $preset;
            }
            $this->tracklist->options['datas_cache_min'] = 0; //no cache by default for wizard (do NOT create subtracks until post is saved and cache enabled)
        }

        $this->tracklist->tracks_strict = false;

        if (wpsstm_is_backend() ){
            $this->tracklist->options['autoplay'] = false;
            $this->tracklist->options['can_play'] = false;
        }

        return $this->tracklist;
    }
    
    /*
    Register the global $wpsstm_tracklist obj backend + enqueue scripts & styles
    */

    function init_backend_wizard(){
        global $post;
        global $wpsstm_tracklist;

        if ( wpsstm_is_backend() ){ //backend
            
            $screen = get_current_screen();

            if ($screen->base != 'post') return;
            if( !in_array($screen->post_type,wpsstm_tracklists()->tracklist_post_types ) ) return;
            
            $this->wizard_enqueue_script_styles();
            
        }

    }

    function backend_wizard_save($post_id){
        global $wpsstm_tracklist;
        
        if( !is_admin() ) return;
        
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,wpsstm_tracklists()->tracklist_post_types) ) return;

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );

        $is_valid_nonce = ( isset($_POST[ 'wpsstm_scraper_wizard_nonce' ]) && wp_verify_nonce( $_POST['wpsstm_scraper_wizard_nonce'], 'wpsstm_save_scraper_wizard'));

        if ($is_autosave || $is_autodraft || $is_revision || !$is_valid_nonce) return;
        
        $_POST[ 'wpsstm_scraper_wizard_nonce' ] = null; //so it breaks infinite loop
        
        $wpsstm_tracklist = wpsstm_get_post_live_tracklist($post_id);
        
        wpsstm()->debug_log($wpsstm_tracklist->post_id, "WP_SoundSystem_Core_Wizard::backend_wizard_save()");
        
        $wizard_data = ( isset($_POST['wpsstm_wizard']) ) ? $_POST['wpsstm_wizard'] : null;

        if ( isset($wizard_data['save-wizard']) ){
            $success = $wpsstm_tracklist->save_wizard($wizard_data);
        }elseif ( isset($wizard_data['import-tracks']) ){
            $wpsstm_tracklist->append_wizard_tracks();
        }elseif( isset($wizard_data['toggle-wizard']) ){
            $enable = ( isset($wizard_data['toggle-wizard']['enable']) );
            $wpsstm_tracklist->toggle_enable_wizard($enable);
        }elseif( isset($wizard_data['restore-scraper']) ){

            $check_keys = array('selectors', 'tracks_order');
            foreach($check_keys as $key){
                if ( array_key_exists($key,$wizard_data) ){
                    unset($wizard_data[$key]);
                }
            }

            $success = $wpsstm_tracklist->save_wizard($wizard_data);
        }

    }
    
    /*
    Create a tracklist from the frontend wizard search input and redirect to it.
    Set the community user as post author so we can detect it as a wizard tracklist.
    */

    function frontend_wizard_create_from_search(){
        
        global $wpsstm_tracklist;

        if ( is_admin() ) return;
        if ( !is_page($this->frontend_wizard_page_id) ) return;
        if ( !$this->can_frontend_wizard() ) return;

        //wizard action
        $wizard_data = isset($_REQUEST[ 'wpsstm_wizard' ]) ? $_REQUEST[ 'wpsstm_wizard' ] : array();
        $is_load_url = isset($wizard_data['action']['load-url']);
        if ( !$is_load_url ) return;


        /*
        Check that there is already a temporary wizard tracklist existing for that same search and redirect to it.
        */

        $duplicate_args = array(
            'post_type'         => wpsstm()->post_type_live_playlist,
            'fields'            => 'ids',
            'post_author'       => wpsstm()->get_options('community_user_id'), //temporary wizard tracklist
            'meta_query' => array(
                array(
                    'key' => wpsstm_live_playlists()->feed_url_meta_name,
                    'value' => $wpsstm_tracklist->feed_url
                )
            )
        );

        $duplicate_query = new WP_Query( $duplicate_args );
        if ( $duplicate_query->have_posts() ){
            $existing_id = $duplicate_query->posts[0];
            $link = get_permalink($existing_id);
            wp_redirect($link);
            exit();
        }

        /*
        Create a new live tracklist for this search and redirect to it
        */

        //store as wizard tracklist (author = community user / ->is_wizard_tracklist_metakey = true)

        $post_args = array(
            'post_title'    => $wpsstm_tracklist->title,
            'post_type'     => wpsstm()->post_type_live_playlist,
            'post_status'   => 'publish',
            'post_author'   => wpsstm()->get_options('community_user_id'),
            'meta_input'   => array(
                wpsstm_live_playlists()->feed_url_meta_name => $wpsstm_tracklist->feed_url,
                $this->is_wizard_tracklist_metakey  => true,
            )
        );

        $new_tracklist_id = wp_insert_post( $post_args );

        if ( is_wp_error($new_tracklist_id) ){
            $link = get_permalink($this->frontend_wizard_page_id);
            $link = add_query_arg(array('wizard_error'=>$new_tracklist_id->get_error_code()),$link);
            wp_redirect($link);
            exit();
        }else{
            $link = get_permalink($new_tracklist_id);
            wp_redirect($link);
            exit();
        }
    }

    function wizard_settings_init(){
        global $post;
        global $wpsstm_tracklist;
        
        //populate backend tracklist
        $wpsstm_tracklist = $this->get_wizard_tracklist($post->ID); 

        wpsstm_wizard()->is_advanced = ( wpsstm_is_backend() && $wpsstm_tracklist->feed_url );
        if ( wpsstm_wizard()->is_advanced ){
            $wpsstm_tracklist->ajax_refresh = false;
        }
        
        if ( ( $wpsstm_tracklist->preset_slug != 'default') && ( $edited = $wpsstm_tracklist->get_user_edited_scraper_options() ) ){
            $restore_link = sprintf('<a href="%s">%s</a>','#',__('here','wpsstm'));
            $restore_link = get_submit_button(__('Restore','wpsstm'),'primary','wpsstm_wizard[restore-scraper]',false);
            $notice = sprintf(__("The Tracks / Track Details settings do not match the %s preset.",'wpsstm'),'<em>' . $wpsstm_tracklist->preset_name . '</em>' ) . '  ' . $restore_link;
            $wpsstm_tracklist->add_notice( 'wizard-header', 'not_preset_defaults', $notice );
        }

        /*
        Source
        */

        add_settings_section(
             'wizard_section_source', //id
             __('Source','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );

        add_settings_field(
            'wpsstm_wizard', //id
            __('URL','wpsstm'), //title
            array( $this, 'feed_url_callback' ), //callback
            'wpsstm-wizard-step-source', //page
            'wizard_section_source', //section
            null //args
        );
        
        /*
        Source feedback
        */

        add_settings_section(
             'wizard_section_source_feedback', //id
             __('Feedback','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );

        if ( $this->is_advanced ){
            
            /*
            Variables
            */

            if ( $wpsstm_tracklist->variables ){
                add_settings_field(
                    'variables', 
                    __('Variables','wpsstm'), 
                    array( $this, 'feedback_variables_callback' ), 
                    'wpsstm-wizard-step-source', 
                    'wizard_section_source_feedback'
                );
            }

            /*
            Tracks
            */

            add_settings_section(
                'wizard_section_tracks', //id
                __('Tracks','wpsstm'), //title
                array( $this, 'section_tracks_desc' ), //callback
                'wpsstm-wizard-step-tracks' //page
            );
            
            add_settings_field(
                'feedback_data_type', 
                __('Input type','wpsstm'), 
                array( $this, 'feedback_data_type_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );
            
            add_settings_field(
                'feedback_source_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_source_content_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            add_settings_field(
                'tracks_selector', 
                __('Tracks Selector','wpsstm'), 
                array( $this, 'selector_tracks_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            add_settings_field(
                'tracks_order', 
                __('Tracks Order','wpsstm'), 
                array( $this, 'tracks_order_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            /*
            Tracks feedback
            */

            add_settings_section(
                 'wizard_section_tracks_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-tracks' //page
            );

            /*
            Single track
            */

            add_settings_section(
                'wizard-section-single-track', //id
                __('Track Details','wpsstm'),
                array( $this, 'section_single_track_desc' ),
                'wpsstm-wizard-step-single-track' //page
            );
            
            add_settings_field(
                'feedback_tracklist_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_tracks_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            add_settings_field(
                'track_artist_selector', 
                __('Artist Selector','wpsstm').'* '.$this->regex_link(),
                array( $this, 'track_artist_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            add_settings_field(
                'track_title_selector', 
                __('Title Selector','wpsstm').'* '.$this->regex_link(), 
                array( $this, 'track_title_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            add_settings_field(
                'track_album_selector', 
                __('Album Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_album_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );
            
            add_settings_field(
                'track_image_selector', 
                __('Image Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_image_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            add_settings_field(
                'track_source_urls', 
                __('Source URL','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_sources_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            /*
            Single track feedback
            */

            add_settings_section(
                 'wizard_section_single_track_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-single-track' //page
            );

            /*
            Options
            */

            add_settings_section(
                'wizard-section-options', //id
                __('Options','wpsstm'),
                array( $this, 'section_desc_empty' ),
                'wpsstm-wizard-step-options' //page
            );

            add_settings_field(
                'datas_cache_min', 
                __('Cache duration','wpsstm'), 
                array( $this, 'cache_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );
            
        }
        
        /*
        display tracklist if available.  
        Not shown this in a separate metabox since we'll already have the Tracklist metabox for playlists and albums.
        */
        if ( wpsstm_is_backend() && $wpsstm_tracklist->feed_url ){
            add_settings_field(
                'feedback_tracklist_content', 
                __('Tracklist','wpsstm'), 
                array( $this, 'feedback_tracklist_callback' ), 
                'wpsstm-wizard-step-source', 
                'wizard_section_source_feedback'
            );
        }

    }

    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="wpsstm-wizard-selector-toggle-advanced"><i class="fa fa-cog" aria-hidden="true"></i></a>',
            __('Use Regular Expression','wpsstm')
        );
    }
    
    function css_selector_block($selector){
        global $wpsstm_tracklist;
        ?>
        <div class="wpsstm-wizard-selector">
            <?php

            //path
            $path = $wpsstm_tracklist->get_options( array('selectors',$selector,'path') );
            $path = ( $path ? htmlentities($path) : null);

            //regex
            $regex = $wpsstm_tracklist->get_options( array('selectors',$selector,'regex') );
            $regex = ( $regex ? htmlentities($regex) : null);
        
            //attr
            $attr_disabled = ( $wpsstm_tracklist->response_type != 'text/html');
            $attr = $wpsstm_tracklist->get_options( array('selectors',$selector,'attr') );
            $attr = ( $attr ? htmlentities($attr) : null);
            

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
                echo $this->get_track_detail_selector_prefix();
            }
            
            printf(
                '<input type="text" class="wpsstm-wizard-selector-jquery" name="%1$s[selectors][%2$s][path]" value="%3$s" />',
                'wpsstm_wizard',
                $selector,
                $path
            );

            //regex
            //uses a table so the style matches with the global form (WP-core styling)
            ?>
            <div class="wpsstm-wizard-selector-advanced">
                <?php
                if ($info){
                    printf('<p class="wpsstm-wizard-track-selector-desc">%s</p>',$info);
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
                                        '<span class="wpsstm-wizard-selector-attr"><input class="regex" name="%s[selectors][%s][attr]" type="text" value="%s" %s/></span>',
                                        'wpsstm_wizard',
                                        $selector,
                                        $attr,
                                        disabled( $attr_disabled, true, false )
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
                                        '<span class="wpsstm-wizard-selector-regex"><input class="regex" name="%1$s[selectors][%2$s][regex]" type="text" value="%3$s"/></span>',
                                        'wpsstm_wizard',
                                        $selector,
                                        $regex
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
    
    function section_desc_empty(){
    }


    function feed_url_callback(){
        global $wpsstm_tracklist;
        
        $option = $wpsstm_tracklist->feed_url;

        $text_input = sprintf(
            '<input type="text" name="%s[search]" value="%s" class="fullwidth" placeholder="%s" />',
            'wpsstm_wizard',
            $option,
            __('Enter a tracklist URL','wpsstm')
        );
        
        $submit_input = null;
        if ( !wpsstm_is_backend() ){
            $submit_input = sprintf('<input type="submit" name="wpsstm_wizard[action][load-url]" id="wpsstm_wizard[action][load-url]" class="button button-primary" value="">');
        }

        
        printf('<p id="wpsstm-wizard-search">%s%s</p>',$text_input,$submit_input);

        //wizard helpers
        if ( $helpers = wpsstm_wizard()->get_available_helpers() ){
            echo $helpers;
        }

    }
    
    function feedback_tracklist_callback(){
        global $wpsstm_tracklist;
        echo $wpsstm_tracklist->get_tracklist_html();
    }

    function feedback_data_type_callback(){
        global $wpsstm_tracklist;

        $output = "—";

        if ( $wpsstm_tracklist->response_type ){
            $output = $wpsstm_tracklist->response_type;
        }
        
        echo $output;

    }
    
    function feedback_variables_callback(){
        global $wpsstm_tracklist;
        
        foreach($wpsstm_tracklist->variables as $variable_slug => $variable){
            $value_str = ( $variable ) ? sprintf('<code>%s</code>',$variable) : '—';
            printf('<p><strong>%s :</strong> %s',$variable_slug,$value_str);
        }
    }   

    function feedback_source_content_callback(){
        global $wpsstm_tracklist;

        $output = "—";
        
        if ( $body_node = $wpsstm_tracklist->body_node ){
            
            $content = $body_node->html();

            //force UTF8
            $content = iconv("ISO-8859-1", "UTF-8", $content); //ISO-8859-1 is from QueryPath

            $content = esc_html($content);
            $output = '<pre class="spiff-raw"><code class="language-markup">'.$content.'</code></pre>';

        }
        
        echo $output;
        

    }
    
    function section_tracks_desc(){
        global $wpsstm_tracklist;

        printf(
            __('Enter a <a href="%s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %s.','wpsstm'),
            'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
            '<code>#content #tracklist .track</code>'
        );
        
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function feedback_tracks_callback(){
        global $wpsstm_tracklist;

        $output = "—"; //none
        $tracks_output = array();
        
        if ( $track_nodes = $wpsstm_tracklist->track_nodes ){

            foreach ($track_nodes as $single_track_node){
                
                $single_track_html = $single_track_node->innerHTML();

                //force UTF8
                $single_track_html = iconv("ISO-8859-1", "UTF-8", $single_track_html); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($single_track_html) );

            }
            if ($tracks_output){
                
                //reverse
                if ( $wpsstm_tracklist->get_options('tracks_order') == 'asc' ){
                    $tracks_output = array_reverse($tracks_output);
                }
                
                $output = sprintf('<div id="spiff-station-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
            }

            
        }


        echo $output;

    }

    function section_single_track_desc(){
        global $wpsstm_tracklist;
        
        $jquery_selectors_link = sprintf('<a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">%s</a>',__('jQuery selectors','wpsstm'));
        $regexes_link = sprintf('<a href="http://regex101.com" target="_blank">%s</a>',__('regular expressions','wpsstm'));

        printf(__('Enter a %s to extract the data for each track.','wpsstm'),$jquery_selectors_link);
        echo"<br/>";
        printf(__('It is also possible to target the attribute of an element or to filter the data with a %s by using %s advanced settings for each item.','wpsstm'),$regexes_link,'<i class="fa fa-cog" aria-hidden="true"></i>');

    }
    
    function get_track_detail_selector_prefix(){
        global $wpsstm_tracklist;
        
        $selector = $wpsstm_tracklist->get_options(array('selectors','tracks','path'));

        if (!$selector) return;
        return sprintf(
            '<span class="tracks-selector-prefix">%1$s</span>',
            $selector
        );
    }

    function track_artist_selector_callback(){
        $this->css_selector_block('track_artist');
    }

    function track_title_selector_callback(){
        $this->css_selector_block('track_title');
    }

    function track_album_selector_callback(){
        $this->css_selector_block('track_album');
    }
    
    function track_image_selector_callback(){
        $this->css_selector_block('track_image');
    }
    
    function track_sources_selector_callback(){
        $this->css_selector_block('track_source_urls');
    }

    function cache_callback(){
        global $wpsstm_tracklist;
        
        $option = $wpsstm_tracklist->get_options('datas_cache_min');

        $desc[] = __('If set, posts will be created for each track when the remote playlist is retrieved.','wpsstm');
        $desc[] = __("They will be flushed after the cache time has expired; if the track does not belong to another playlist or user's likes.",'wpsstm');
        $desc[] = __("This can be useful if you have a lot of traffic - there will be less remote requests ans track sources will be searched only once.",'wpsstm');
        $desc = implode("<br/>",$desc);

        printf(
            '<input type="number" name="%s[datas_cache_min]" size="4" min="0" value="%s" /> %s<br/><small>%s</small>',
            'wpsstm_wizard',
            $option,
            __('minutes','spiff'),
            $desc
        );

        
    }

    function tracks_order_callback(){
        global $wpsstm_tracklist;
        
        $option = $wpsstm_tracklist->get_options('tracks_order');
        
        $desc_text = sprintf(
            '<input type="radio" name="%1$s[tracks_order]" value="desc" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked($option, 'desc', false),
            __('Descending','spiff')
        );
        
        $asc_text = sprintf(
            '<input type="radio" name="%1$s[tracks_order]" value="asc" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked($option, 'asc', false),
            __('Ascending','spiff')
        );
        
        echo $desc_text." ".$asc_text;

        
    }

    function wizard_tabs( $active_tab = '' ) {
        global $wpsstm_tracklist;

        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $source_tab = $tracks_selector_tab = $track_details_tab = $options_tab = $tracklist_tab = array();
        
        $status_icons = array(
            '<i class="fa fa-times-circle" aria-hidden="true"></i>',
            '<i class="fa fa-check-circle" aria-hidden="true"></i>'
        );

        $icon_source_tab = $status_icons[0];
        if ( $wpsstm_tracklist->body_node ){
            $icon_source_tab = $status_icons[1];
        }

        $source_tab = array(
            'icon'    => $icon_source_tab,
            'title'     => __('Source','spiff'),
            'href'      => '#wpsstm-wizard-step-source-content'
        );

        $icon_tracks_tab = $status_icons[0];
        if ( $wpsstm_tracklist->track_nodes ){
            $icon_tracks_tab = $status_icons[1];
        }

        $tracks_selector_tab = array(
            'icon'    => $icon_tracks_tab,
            'title'  => __('Tracks','spiff'),
            'href'  => '#wpsstm-wizard-step-tracks-content'
        );

        $icon_track_details_tab = $status_icons[0];

        if ( $wpsstm_tracklist->tracks ){
            $icon_track_details_tab = $status_icons[1];
        }

        $track_details_tab = array(
            'icon'    => $icon_track_details_tab,
            'title'  => __('Track Details','spiff'),
            'href'  => '#wpsstm-wizard-step-single-track-content'
        );

        $options_tab = array(
            'title'  => __('Options','spiff'),
            'href'  => '#wpsstm-wizard-step-options'
        );

        $tabs = array(
            $source_tab,
            $tracks_selector_tab,
            $track_details_tab,
            $options_tab
        );
        
        $tabs = array_filter($tabs);

        // Loop through tabs and build navigation
        foreach ( array_values( $tabs ) as $key=>$tab_data ) {

                $is_current = (bool) ( $key == $active_tab );
                $tab_class  = $is_current ? $active_class : $idle_class;
                $tab_icon =  ( isset($tab_data['icon']) ) ? $tab_data['icon'] : null;
            
                $tabs_html .= sprintf('<li><a href="%s" class="%s">%s %s</a></li>',
                    $tab_data['href'],
                    esc_attr( $tab_class ),
                    $tab_icon,
                    esc_html( $tab_data['title'] )
                );
        }

        echo $tabs_html;
    }

    function can_frontend_wizard(){

        if ( !$user_id = get_current_user_id() ){
            $can_wizard_unlogged = ( wpsstm()->get_options('visitors_wizard') == 'on' );
            if (!$can_wizard_unlogged) return false;
        }

        $community_user_id = wpsstm()->get_options('community_user_id');
        
        $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;
        return user_can($community_user_id,$required_cap);
    }
    
    function get_available_helpers(){
        $class_names = array();
        $helpers = array();
        $helpers_output = array();
        
        $presets_path = trailingslashit( wpsstm()->plugin_dir . 'classes/wizard-helpers' );
        require_once($presets_path . 'default.php'); //default class
        
        //get all files in /presets directory
        $preset_files = glob( $presets_path . '*.php' ); 

        foreach ($preset_files as $file) {
            require_once($file);
        }
        $class_names = apply_filters('wpsstm_get_wizard_helpers',$class_names);

        //check and run
        foreach((array)$class_names as $class_name){
            if ( !class_exists($class_name) ) continue;
            $can_show_helper = $class_name::can_show_helper();
            if ( $can_show_helper !== true ) continue;
            $helpers[] = new $class_name();
            
        }
        
        foreach((array)$helpers as $helper){
            $helper_title = ($helper->name) ? sprintf('<h3>%s</h3>',$helper->name) : null;
            $helper_desc = ($helper->desc) ? sprintf('<p>%s</p>',$helper->desc) : null;
            $helper_content = ($content = $helper->get_output()) ? sprintf('<div>%s</div>',$content) : null;
            
            $helpers_output[] = sprintf('<li class="wpsstm-wizard-helper" id="wpsstm-wizard-helper-%s">%s%s%s</li>',$helper->slug,$helper_title,$helper_desc,$helper_content);
        }

        if ($helpers_output) return sprintf('<ul id="wpsstm-wizard-helpers">%s</ul>',implode("\n",$helpers_output));

    }

}

function wpsstm_wizard() {
	return WP_SoundSystem_Core_Wizard::instance();
}

wpsstm_wizard();
