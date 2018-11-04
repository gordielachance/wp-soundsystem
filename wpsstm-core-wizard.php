<?php

class WPSSTM_Core_Wizard{

    var $wizard_sections  = array();
    var $wizard_fields = array();

    static $qvar_tracklist_wizard = 'wztr';
    static $is_wizard_tracklist_metakey = '_wpsstm_is_wizard';

    
    function __construct(){
        
        add_filter( 'query_vars', array($this,'add_wizard_query_vars'));

        //frontend
        add_action( 'wp', array($this,'frontend_wizard_create_from_search' ) );
        add_action( 'template_redirect', array($this,'community_tracklist_redirect'));
        add_action( 'the_post', array($this,'populate_wizard_tracklist_input'), 11, 2); //after 'the_tracklist' priority
        add_action( 'the_post', array($this,'populate_wizard_tracklist_id'), 12, 2); //after 'the_tracklist' priority
        add_filter( 'the_content', array($this,'frontend_wizard_content'));

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
        $vars[] = self::$qvar_tracklist_wizard;
        return $vars;
    }

    function wizard_register_scripts_style_shared(){
        // JS
        wp_register_script( 'wpsstm-wizard', wpsstm()->plugin_url . '_inc/js/wpsstm-wizard.js', array('jquery','jquery-ui-tabs','wpsstm-tracklists'),wpsstm()->version, true);
    }
    
    function wizard_scripts_styles_frontend(){
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return;
        $this->wizard_enqueue_script_styles();
    }
    
    function wizard_scripts_styles_backend(){
        //TO FIX
        $screen = get_current_screen();
        $this->wizard_enqueue_script_styles();
    }
    
    function wizard_enqueue_script_styles(){
        wp_enqueue_script('wpsstm-wizard');
    }

    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper', 
            __('Remote Tracklist Manager','wpsstm'),
            array($this,'metabox_wizard_display'),
            wpsstm()->tracklist_post_types, 
            'normal', //context
            'high' //priority
        );

    }

    function metabox_wizard_display(){
        $this->wizard_settings_init();
        wpsstm_locate_template( 'wizard-backend.php', true );
    }

    /*
    For wizard tracklists (created with the community user); 
    Redirect to wizard and pass tracklist ID as parameter
    
    If user try to hacks this (by passing a tracklist ID that is not a community post); redirect to regular tracklist.
    */
    
    function community_tracklist_redirect(){
        global $post;
        global $wpsstm_tracklist;
        if ( is_page(wpsstm()->get_options('frontend_scraper_page_id')) ){
            //wizard called on a tracklist that is not a community one.  Redirect to regular tracklist.
            if ( $wztr_id = get_query_var(self::$qvar_tracklist_wizard) ){
                
                //this is not a community tracklist, abord wizard
                if ( !wpsstm_is_community_post($wztr_id) ){
                    $link = get_permalink($wztr_id);
                    wp_safe_redirect($link);
                    exit();
                }
            }
        }

        //live playlist page but this is a community tracklist ! Redirect to wizard.
        if( is_singular( wpsstm()->post_type_live_playlist ) ){
            $wpsstm_tracklist = wpsstm_get_tracklist($post->ID);
            if ($wpsstm_tracklist->post_id){
                $tracklist_action = get_query_var( WPSSTM_Core_Tracklists::$qvar_tracklist_action );

                if ( !$tracklist_action && $wpsstm_tracklist && wpsstm_is_community_post($wpsstm_tracklist->post_id) ){
                    $link = get_permalink(wpsstm()->get_options('frontend_scraper_page_id'));
                    $link = add_query_arg(array(self::$qvar_tracklist_wizard=>$wpsstm_tracklist->post_id),$link);
                    wp_safe_redirect($link);
                    exit();
                }
            }
        }
        
        
    }
    
    /*
    populate wizard tracklist when ?wztr=... is set 
    */
    function populate_wizard_tracklist_id($post,$query){
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return;
        if ( !$wztr = get_query_var(self::$qvar_tracklist_wizard,null) ) return;
        $this->populate_wizard_tracklist($wztr);
    }
    
    /*
    populate wizard tracklist when input search is defined
    */
    function populate_wizard_tracklist_input($post,$query){
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return;
        //get tracklist from wizard input
        $input = isset($_POST['wpsstm_wizard']['search']) ? trim($_POST['wpsstm_wizard']['search']) : null;
        if (!$input) return;
        
        $this->populate_wizard_tracklist(null,$input);
    }

    /*
    We're requesting the frontend wizard page:
    load the wizard template
    eventually populate wizard tracklist
    */
    
    function frontend_wizard_content($content){

        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return $content;
        
        ob_start();
        wpsstm_locate_template( 'wizard-frontend.php', true, false );
        $wizard = ob_get_clean();
        return $content . $wizard;
    }

    private function populate_wizard_tracklist($post_id=null,$feed_url=null){
        global $wpsstm_tracklist;
        
        //set global $wpsstm_tracklist
        $wpsstm_tracklist = wpsstm_get_tracklist($post_id);
        
        //wizard specific options
        $wpsstm_tracklist->options['tracks_strict'] = false;

        if (wpsstm_is_backend() ){
            $wpsstm_tracklist->options['autoplay'] = false;
        }

        if ( !$post_id && $feed_url ){ //is wizard input
            $feed_url = trim($feed_url);
            $feed_url = apply_filters('wpsstm_wizard_input',$feed_url);

            if( !$feed_url ){
                $wpsstm_tracklist->add_notice( 'wizard-header','wpsstm_wizard_missing_input', __('Missing wizard input.','wpsstm') );
                return false;
            }

            $url_parsed = parse_url($feed_url); //check this is an URL
            if( empty($url_parsed['scheme']) ){
                $wpsstm_tracklist->add_notice( 'wizard-header','wpsstm_wizard_missing_url_input', __('Missing wizard URL input.','wpsstm') );
                return false;
            }
            
            $wpsstm_tracklist->feed_url = $feed_url;
            
        }

        return true;
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
            if( !in_array($screen->post_type,wpsstm()->tracklist_post_types ) ) return;
            
            $this->wizard_enqueue_script_styles();
            
        }

    }

    function backend_wizard_save($post_id){
        global $wpsstm_tracklist;
        
        if( !is_admin() ) return;
        
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return;

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );

        $is_valid_nonce = ( isset($_POST[ 'wpsstm_scraper_wizard_nonce' ]) && wp_verify_nonce( $_POST['wpsstm_scraper_wizard_nonce'], 'wpsstm_save_scraper_wizard'));

        if ($is_autosave || $is_autodraft || $is_revision || !$is_valid_nonce) return;
        
        $_POST[ 'wpsstm_scraper_wizard_nonce' ] = null; //so it breaks infinite loop
        
        //set global $wpsstm_tracklist
        $wpsstm_tracklist = wpsstm_get_tracklist($post_id);
        
        $wpsstm_tracklist->tracklist_log($wpsstm_tracklist->post_id, "WPSSTM_Core_Wizard::backend_wizard_save()");

        $wizard_data = ( isset($_POST['wpsstm_wizard']) ) ? $_POST['wpsstm_wizard'] : null;

        if ( isset($wizard_data['save-wizard']) ){
            //save feed URL
            $input = isset($wizard_data['search']) ? $wizard_data['search'] : null;
            $wpsstm_tracklist->feed_url = trim($input);
            $success = $wpsstm_tracklist->save_feed_url(); //TO FIX input not filtered. Should we rather use populate_wizard_tracklist(null,$input) here ?
            //save wizard settings
            $success = $wpsstm_tracklist->save_wizard($wizard_data);
        }elseif ( isset($wizard_data['import-tracks']) ){
            $wpsstm_tracklist->append_wizard_tracks();
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
        if ( !is_page(wpsstm()->get_options('frontend_scraper_page_id')) ) return;
        if ( !self::can_frontend_wizard() ) return;

        //wizard action
        $is_load_url = isset($_REQUEST[ 'wpsstm_wizard' ]['action']['load-url']);
        if ( !$is_load_url ) return;
        
        //populate tracklist
        $input = isset($_POST['wpsstm_wizard']['search']) ? trim($_POST['wpsstm_wizard']['search']) : null;
        if ($input){
            $this->populate_wizard_tracklist(null,$input);
        }
        
        if ( !$wpsstm_tracklist->feed_url ) return;

        $duplicate_args = array(
            'post_type'         => wpsstm()->post_type_live_playlist,
            'fields'            => 'ids',
            'meta_query' => array(
                array(
                    'key' => WPSSTM_Core_Live_Playlists::$feed_url_meta_name,
                    'value' => $wpsstm_tracklist->feed_url
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
                exit();
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
                WPSSTM_Core_Live_Playlists::$feed_url_meta_name => $wpsstm_tracklist->feed_url,
                self::$is_wizard_tracklist_metakey  => true,
            )
        );

        $success = wp_insert_post( $post_args, true );

        if ( is_wp_error($success) ){
            $link = get_permalink(wpsstm()->get_options('frontend_scraper_page_id'));
            $link = add_query_arg(array('wizard_error'=>$success->get_error_code()),$link);
            wp_safe_redirect($link);
            exit();
        }else{
            $post_id = $success;
            $link = get_permalink($post_id);
            wp_safe_redirect($link);
            exit();
        }
    }

    function wizard_settings_init(){
        global $post;
        global $wpsstm_tracklist;
        
        //populate backend tracklist
        $this->populate_wizard_tracklist($post->ID);

        /*
        TOFIXGGG TO CHECK is this useful ? should we re-enable it ?
        if ( ( $wpsstm_tracklist->preset_slug != 'default') && ( $edited = $wpsstm_tracklist->get_user_edited_scraper_options() ) ){
            $restore_link = sprintf('<a href="%s">%s</a>','#',__('here','wpsstm'));
            $restore_link = get_submit_button(__('Restore','wpsstm'),'primary','wpsstm_wizard[restore-scraper]',false);
            $notice = sprintf(__("The Tracks / Track Details settings do not match the %s preset.",'wpsstm'),'<em>' . $wpsstm_tracklist->preset_name . '</em>' ) . '  ' . $restore_link;
            $wpsstm_tracklist->add_notice( 'wizard-header', 'not_preset_defaults', $notice );
        }
        */
        
        /*
        Input
        */
        
        add_settings_section(
             'wizard_section_input', //id
             __('Input','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-input' //page
        );

        add_settings_field(
            'wpsstm_wizard', //id
            __('Remote URL','wpsstm'), //title
            array( $this, 'feed_url_callback' ), //callback
            'wpsstm-wizard-step-input', //page
            'wizard_section_input', //section
            null //args
        );

        /*
        Profile
        */

        /*
        Tracks
        */

        add_settings_section(
            'wizard_section_tracks', //id
            __('Tracks','wpsstm'), //title
            array( $this, 'section_tracks_desc' ), //callback
            'wpsstm-wizard-step-profile' //page
        );

        add_settings_field(
            'tracks_selector', 
            __('Tracks Selector','wpsstm'), 
            array( $this, 'selector_tracks_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard_section_tracks'
        );

        add_settings_field(
            'tracks_order', 
            __('Tracks Order','wpsstm'), 
            array( $this, 'tracks_order_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard_section_tracks'
        );



        /*
        Track Details
        */

        add_settings_section(
            'wizard-section-single-track', //id
            __('Track Details','wpsstm'),
            array( $this, 'section_single_track_desc' ),
            'wpsstm-wizard-step-profile' //page
        );

        add_settings_field(
            'track_artist_selector', 
            __('Artist Selector','wpsstm').' '.$this->regex_link(),
            array( $this, 'track_artist_selector_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard-section-single-track'
        );

        add_settings_field(
            'track_title_selector', 
            __('Title Selector','wpsstm').' '.$this->regex_link(), 
            array( $this, 'track_title_selector_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard-section-single-track'
        );

        add_settings_field(
            'track_album_selector', 
            __('Album Selector','wpsstm').' '.$this->regex_link(), 
            array( $this, 'track_album_selector_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard-section-single-track'
        );

        add_settings_field(
            'track_image_selector', 
            __('Image Selector','wpsstm').' '.$this->regex_link(), 
            array( $this, 'track_image_selector_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard-section-single-track'
        );

        add_settings_field(
            'track_source_urls', 
            __('Source URL','wpsstm').' '.$this->regex_link(), 
            array( $this, 'track_sources_selector_callback' ), 
            'wpsstm-wizard-step-profile', 
            'wizard-section-single-track'
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
        

        /*
        Results
        */

        add_settings_section(
             'wizard_section_results', //id
             __('Results','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-results' //page
        );

        /*
        display tracklist if available.  
        Do not show this in a separate metabox since we'll already have the Tracklist metabox for playlists and albums.
        */
        if ( $this->is_advanced_wizard() ){
            add_settings_field(
                'feedback_tracklist_content', 
                __('Tracklist','wpsstm'), 
                array( $this, 'feedback_tracklist_callback' ), 
                'wpsstm-wizard-step-results', 
                'wizard_section_results'
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
            $path = $wpsstm_tracklist->get_selectors( array($selector,'path') );
            $path = ( $path ? htmlentities($path) : null);

            //regex
            $regex = $wpsstm_tracklist->get_selectors( array($selector,'regex') );
            $regex = ( $regex ? htmlentities($regex) : null);
        
            //attr
            $attr_disabled = ( $wpsstm_tracklist->response_type != 'text/html');
            $attr = $wpsstm_tracklist->get_selectors( array($selector,'attr') );
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


    public static function feed_url_callback(){
        global $wpsstm_tracklist;
        
        $option = ($wpsstm_tracklist->tracklist_type == 'live') ? $wpsstm_tracklist->feed_url_no_filters : null;

        $text_input = sprintf(
            '<input type="text" name="%s[search]" value="%s" class="wpsstm-fullwidth" placeholder="%s" />',
            'wpsstm_wizard',
            $option,
            __('Type something or enter a tracklist URL','wpsstm')
        );
        
        $submit_input = null;
        if ( !wpsstm_is_backend() ){
            $submit_input = '<button type="submit" name="wpsstm_wizard[action][load-url]" id="wpsstm_wizard[action][load-url]" class="button button-primary wpsstm-icon-button"><i class="fa fa-search" aria-hidden="true"></i></button>';
        }

        
        printf('<p class="wpsstm-icon-input" id="wpsstm-wizard-search">%s%s</p>',$text_input,$submit_input);

        //wizard widgets
        if ( $widgets = self::get_available_widgets() ){
            echo $widgets;
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
                if ( $wpsstm_tracklist->get_scraper_options('tracks_order') == 'asc' ){
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
        
        $selector = $wpsstm_tracklist->get_selectors( array('tracks','path'));

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
        
        $option = $wpsstm_tracklist->get_scraper_options('datas_cache_min');

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
        
        $option = $wpsstm_tracklist->get_scraper_options('tracks_order');
        
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

    public static function wizard_tabs( $active_tab = '' ) {
        global $wpsstm_tracklist;

        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $input_tab = $profile_tab = $options_tab = $results_tab = array();

        $input_tab = array(
            'title'     => __('Input','spiff'),
            'href'      => '#wpsstm-wizard-step-input-content'
        );

        $profile_tab = array(
            'title'     => __('Profile','spiff'),
            'href'      => '#wpsstm-wizard-step-profile-content'
        );

        $options_tab = array(
            'title'  => __('Options','spiff'),
            'href'  => '#wpsstm-wizard-step-options-content'
        );

        $results_title = __('Results','spiff');
          
        if ( $wpsstm_tracklist->did_query_tracks ){
            $tracks_count = count($wpsstm_tracklist->tracks);
            $results_title .= ' '.$tracks_count;
            //TOFIFIX $results_title .= sprintf(' <small>%s</small>',_n( '%s track', '%s tracks', $tracks_count, 'wpsstm' ) );
        }
        
        $results_tab = array(
            'title'  => $results_title,
            'href'  => '#wpsstm-wizard-step-results-content'
        );

        $tabs = array(
            $input_tab,
            $profile_tab,
            $options_tab,
            $results_tab,
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

    public static function can_frontend_wizard(){

        if ( !$user_id = get_current_user_id() ){
            $can_wizard_unlogged = ( wpsstm()->get_options('visitors_wizard') == 'on' );
            if (!$can_wizard_unlogged) return false;
        }

        $community_user_id = wpsstm()->get_options('community_user_id');
        
        $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;
        return user_can($community_user_id,$required_cap);
    }
    
    private static function get_available_widgets(){
        $class_names = array();
        $widgets = array();
        $widgets_output = array();
        
        $presets_path = trailingslashit( wpsstm()->plugin_dir . 'classes/wizard-widgets' );
        require_once($presets_path . 'default.php'); //default class
        
        //get all files in /presets directory
        $preset_files = glob( $presets_path . '*.php' ); 

        foreach ($preset_files as $file) {
            require_once($file);
        }
        $class_names = apply_filters('wpsstm_get_wizard_widgets',$class_names);

        //check and run
        foreach((array)$class_names as $class_name){
            if ( !class_exists($class_name) ) continue;
            $widgets[] = new $class_name();
            
        }
        
        foreach((array)$widgets as $widget){
            $widget_title = ($widget->name) ? sprintf('<h3>%s</h3>',$widget->name) : null;
            $widget_desc = ($widget->desc) ? sprintf('<p>%s</p>',$widget->desc) : null;
            
            if ( $content = $widget->get_output() ){
                $widget_content = ($content = $widget->get_output()) ? sprintf('<div class="wpsstm-wizard-widget-content">%s</div>',$content) : null;

                $widgets_output[] = sprintf('<li class="wpsstm-wizard-widget" id="wpsstm-wizard-widget-%s">%s%s%s</li>',$widget->slug,$widget_title,$widget_desc,$widget_content);
            }

        }

        if ($widgets_output) return sprintf('<ul id="wpsstm-wizard-widgets">%s</ul>',implode("\n",$widgets_output));

    }
    
    public static function is_advanced_wizard(){
        global $wpsstm_tracklist;
        return ( wpsstm_is_backend() && ($wpsstm_tracklist->tracklist_type == 'live') );
    }

}
