<?php

class WP_SoundSystem_Core_Wizard{

    var $is_advanced = false;

    var $wizard_sections  = array();
    var $wizard_fields = array();
    
    public $frontend_wizard_page_id = null;
    public $qvar_wizard_posts = 'wpsstm_wizard_posts';
    public $wizard_url;
    public $wizard_disabled_metakey = '_wpsstm_wizard_disabled';

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
        $this->wizard_url = isset($_REQUEST[ 'wpsstm_wizard' ]['feed_url']) ? trim($_REQUEST[ 'wpsstm_wizard' ]['feed_url']) : null;
    }

    function setup_actions(){
        
        add_filter( 'query_vars', array($this,'add_wizard_query_vars'));
        add_filter( 'page_rewrite_rules', array($this,'frontend_wizard_rewrite') );

        //frontend
        add_action( 'wp', array($this,'do_frontend_wizard_form' ) );
        add_action( 'wp',  array($this, 'frontend_wizard_add_tracklist'));
        
        add_filter( 'page_template', array($this,'wizard_page_template'));

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
        $vars[] = $this->qvar_wizard_posts;
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
        // CSS
        wp_register_style( 'wpsstm-scraper-wizard',  wpsstm()->plugin_url . '_inc/css/wpsstm-scraper-wizard.css',array('wpsstm-tracklists'),wpsstm()->version );
        
        // JS
        wp_register_script( 'wpsstm-scraper-wizard', wpsstm()->plugin_url . '_inc/js/wpsstm-scraper-wizard.js', array('jquery','jquery-ui-tabs','wpsstm-tracklists'),wpsstm()->version, true);
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
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }

    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper-wizard', 
            __('Remote Tracklist Manager','wpsstm'),
            array($this,'metabox_wizard_display'),
            wpsstm_tracklists()->tracklist_post_types, 
            'normal', //context
            'high' //priority
        );

    }

    function metabox_wizard_display(){
        global $wpsstm_tracklist;
        global $post;
        $this->populate_wizard_tracklist($post->ID);
        wpsstm_locate_template( 'wizard-form.php', true );
    }
    
    function wizard_page_template($template){
        if ( !is_page($this->frontend_wizard_page_id) ) return $template;
        if ( !$this->can_frontend_wizard() ) return $template;

        return wpsstm_locate_template( 'frontend-wizard.php' );
    }
    
    function populate_wizard_tracklist($post_id=null,$feed_url=null){
        global $wpsstm_tracklist;
        
        if ($post_id){
            $wpsstm_tracklist = wpsstm_get_post_live_tracklist($post_id);
        }else{
            $feed_url = wpsstm_wizard()->wizard_url;
            $wpsstm_tracklist = wpsstm_get_live_tracklist_preset($feed_url);
        }
        
        $wpsstm_tracklist->can_remote_request = !$wpsstm_tracklist->is_wizard_disabled();
        $wpsstm_tracklist->is_expired = true; //force tracklist refresh
        $wpsstm_tracklist->tracks_strict = false;
        
        if (wpsstm_is_backend() ){
            $wpsstm_tracklist->options['autoplay'] = false;
            $wpsstm_tracklist->options['can_play'] = false;
        }


    }

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
    
    function frontend_wizard_add_tracklist(){
        global $post;
        global $wpsstm_tracklist;
        if (!$post) return;
        
        $wizard_data = ( isset($_POST[ 'wpsstm_wizard' ]) ) ? $_POST[ 'wpsstm_wizard' ] : null;
        if ( !isset($wizard_data['save-wizard']) ) return;

        $tracklist = wpsstm_get_post_live_tracklist($post->ID);
        
        $saved = $tracklist->save_wizard($wizard_data);
        
        if ( is_wp_error($saved) ){
            //TO FIX debug log
        }elseif($saved){
            //redirect so we repopulate everything
            $wizard_permalink = $wpsstm_tracklist->get_wizard_permalink();
            wp_redirect($wizard_permalink);
            exit();
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
        }elseif ( isset($wizard_data['save-playlist']['type']['static']) ){
            $success = $wpsstm_tracklist->convert_to_static_playlist();
        }elseif ( isset($wizard_data['import-tracks']) ){
            $wpsstm_tracklist->append_wizard_tracks();
        }elseif( isset($wizard_data['toggle-wizard']) ){
            $enable = ( isset($wizard_data['toggle-wizard']['enable']) );
            $wpsstm_tracklist->toggle_enable_wizard($enable);
        }

    }

    function do_frontend_wizard_form(){

        if ( !is_page($this->frontend_wizard_page_id) ) return;
        if ( !$this->can_frontend_wizard() ) return;
        
        $wizard_data = isset($_POST[ 'wpsstm_wizard' ]) ? $_POST[ 'wpsstm_wizard' ] : array();
        
        $save_live_playlist = ( isset($wizard_data['save-playlist']['type']['live']) );
        $save_static_playlist = ( isset($wizard_data['save-playlist']['type']['static']) );
        
        if ( !$save_live_playlist && !$save_static_playlist ) return;

        $is_static = ($save_static_playlist);
        $saved = $this->insert_frontend_wizard_tracklist($is_static);

    }
    
    function insert_frontend_wizard_tracklist($static=false){
        
        if ( !wpsstm_wizard()->can_frontend_wizard() ) return;

        if ( !$user_id = get_current_user_id() ){
            $user_id = wpsstm()->get_options('community_user_id');
        }
        
        if (!$user_id) return;

        if (filter_var($this->wizard_url, FILTER_VALIDATE_URL) === false){
            $link = get_permalink($this->frontend_wizard_page_id);
            $link = add_query_arg(array('wizard_error'=>'invalid_url'),$link);
            wp_redirect($link);
            exit();
        }

        //create tracklist
        //TO FIX check for duplicate using author + scraper url ?
        
        $post_args = array(
            'post_type'     => wpsstm()->post_type_live_playlist,
            'post_status'   => 'publish',
            'post_author'   => $user_id,
            'meta_input'   => array(
                wpsstm_live_playlists()->feed_url_meta_name => $this->wizard_url,
            )
        );

        $new_post_id = wp_insert_post( $post_args );
        if ( is_wp_error($new_post_id) ) return $new_post_id;
        
        $tracklist = wpsstm_get_post_live_tracklist($new_post_id);  
        $tracklist->update_from_remote();
        
        if($static){
            $converted = $tracklist->convert_to_static_playlist();

            if ( is_wp_error($converted) ){
                $link = get_permalink($this->frontend_wizard_page_id);
                $link = add_query_arg(array('wizard_error'=>$converted->get_error_code()),$link);
                wp_redirect($link);
                exit();
            }
            
        }

        $link = get_permalink($new_post_id);
        wp_redirect($link);
        exit();
    }

    function wizard_settings_init(){
        global $post;
        global $wpsstm_tracklist;
        
        wpsstm_wizard()->is_advanced = ( $wpsstm_tracklist->feed_url && !$wpsstm_tracklist->track_count && wpsstm_is_backend() );

        /*
        Source
        */

        $this->add_wizard_section(
             'wizard_section_source', //id
             __('Source','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );

        $this->add_wizard_field(
            'feed_url', //id
            __('URL','wpsstm'), //title
            array( $this, 'feed_url_callback' ), //callback
            'wpsstm-wizard-step-source', //page
            'wizard_section_source', //section
            null //args
        );
        
        /*
        Source feedback
        */

        $this->add_wizard_section(
             'wizard_section_source_feedback', //id
             __('Feedback','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );

        if ( $this->is_advanced ){
            
            /*
            Regex matches
            */

            if ( $wpsstm_tracklist->variables ){
                $this->add_wizard_field(
                    'regex_matches', 
                    __('Regex matches','wpsstm'), 
                    array( $this, 'feedback_regex_matches_callback' ), 
                    'wpsstm-wizard-step-source', 
                    'wizard_section_source_feedback'
                );
            }

            /*
            Tracks
            */

            $this->add_wizard_section(
                'wizard_section_tracks', //id
                __('Tracks','wpsstm'), //title
                array( $this, 'section_tracks_desc' ), //callback
                'wpsstm-wizard-step-tracks' //page
            );
            
            $this->add_wizard_field(
                'feedback_data_type', 
                __('Input type','wpsstm'), 
                array( $this, 'feedback_data_type_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );
            
            $this->add_wizard_field(
                'feedback_source_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_source_content_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            $this->add_wizard_field(
                'tracks_selector', 
                __('Tracks Selector','wpsstm'), 
                array( $this, 'selector_tracks_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            $this->add_wizard_field(
                'tracks_order', 
                __('Tracks Order','wpsstm'), 
                array( $this, 'tracks_order_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            /*
            Tracks feedback
            */

            $this->add_wizard_section(
                 'wizard_section_tracks_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-tracks' //page
            );

            /*
            Single track
            */

            $this->add_wizard_section(
                'wizard-section-single-track', //id
                __('Track details','wpsstm'),
                array( $this, 'section_single_track_desc' ),
                'wpsstm-wizard-step-single-track' //page
            );
            
            $this->add_wizard_field(
                'feedback_tracklist_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_tracks_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_artist_selector', 
                __('Artist Selector','wpsstm').'* '.$this->regex_link(),
                array( $this, 'track_artist_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_title_selector', 
                __('Title Selector','wpsstm').'* '.$this->regex_link(), 
                array( $this, 'track_title_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_album_selector', 
                __('Album Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_album_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );
            
            $this->add_wizard_field(
                'track_image_selector', 
                __('Image Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_image_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_source_urls', 
                __('Source URL','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_sources_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            /*
            Single track feedback
            */

            $this->add_wizard_section(
                 'wizard_section_single_track_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-single-track' //page
            );

            /*
            Options
            */

            $this->add_wizard_section(
                'wizard-section-options', //id
                __('Options','wpsstm'),
                array( $this, 'section_desc_empty' ),
                'wpsstm-wizard-step-options' //page
            );

            $this->add_wizard_field(
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
            $this->add_wizard_field(
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

        printf(
            '<input type="text" name="%s[feed_url]" value="%s" class="fullwidth" placeholder="%s" />',
            'wpsstm_wizard',
            $option,
            __('URL of the tracklist you would like to get','wpsstm')
        );
        
        //presets
        $presets_list = array();
        $presets_list_str = null;
        foreach ((array)wpsstm_live_playlists()->get_available_presets() as $preset){
            if ( !$preset->wizard_suggest ) continue;
            $preset_str = $preset->preset_name;
            if ($preset->preset_url){
                $preset_str = sprintf('<a href="%s" title="%s" target="_blank">%s</a>',$preset->preset_url,$preset->preset_desc,$preset_str);
            }
            $presets_list[] = $preset_str;
        }

        if ( !empty($presets_list) ){
            $presets_list_str = implode(', ',$presets_list);
            printf('<p id="wpsstm-available-presets"><small><strong>%s</strong> %s</small></p>',__('Available presets:','wpsstm'),$presets_list_str);
        }

    }
    
    function feedback_tracklist_callback(){
        global $wpsstm_tracklist;
        echo $wpsstm_tracklist->get_tracklist_table();
    }

    function feedback_data_type_callback(){
        global $wpsstm_tracklist;

        $output = "—";

        if ( $wpsstm_tracklist->response_type ){
            $output = $wpsstm_tracklist->response_type;
        }
        
        echo $output;

    }
    
    function feedback_regex_matches_callback(){
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
        
        $wpsstm_tracklist->output_notices('wizard-step-tracks');
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
        
        $wpsstm_tracklist->output_notices('wizard-step-single-track');
        
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

        printf(
            '<input type="number" name="%1$s[datas_cache_min]" size="4" min="0" value="%2$s" /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            $option,
            __('Time the remote tracks should be cached (in minutes).','spiff')
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
            'title'  => __('Track details','spiff'),
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

    /*
    Inspired by WP function add_settings_section()
    */
    
    function add_wizard_section($id, $title, $callback, $page) {
        $this->wizard_sections[$page][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback);
    }
    
    /*
    Inspired by WP function add_settings_field()
    */
    
    function add_wizard_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
        $this->wizard_fields[$page][$section][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args);
    }
    
    /*
    Inspired by WP function do_settings_sections()
    */
    
    function do_wizard_sections( $page ) {

        if ( ! isset( $this->wizard_sections[$page] ) )
            return;

        foreach ( (array) $this->wizard_sections[$page] as $section ) {
            if ( $section['title'] )
                echo "<h2>{$section['title']}</h2>\n";

            if ( $section['callback'] )
                call_user_func( $section['callback'], $section );

            if ( ! isset( $this->wizard_fields ) || !isset( $this->wizard_fields[$page] ) || !isset( $this->wizard_fields[$page][$section['id']] ) )
                continue;
            echo '<table class="form-table wizard-section-table">';
            $this->do_wizard_fields( $page, $section['id'] );
            echo '</table>';
        }
    }
    
    /*
    Inspired by WP function do_settings_fields()
    */
    
    function do_wizard_fields($page, $section) {

        if ( ! isset( $this->wizard_fields[$page][$section] ) )
            return;

        foreach ( (array) $this->wizard_fields[$page][$section] as $field ) {
            $class = '';

            if ( ! empty( $field['args']['class'] ) ) {
                $class = ' class="' . esc_attr( $field['args']['class'] ) . '"';
            }

            echo "<tr{$class}>";

            if ( ! empty( $field['args']['label_for'] ) ) {
                echo '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label></th>';
            } else {
                echo '<th scope="row">' . $field['title'] . '</th>';
            }

            echo '<td>';
            call_user_func($field['callback'], $field['args']);
            echo '</td>';
            echo '</tr>';
        }
    }
    

    /*
    Inspired by WP function submit_button(); which is not available frontend
    */
    
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
        echo $this->get_submit_button( $text, $type, $name, $wrap, $other_attributes );
    }
    
    /*
    Inspired by WP function get_submit_button(); which is not available frontend
    */
    
    function get_submit_button( $text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '' ) {
        if ( ! is_array( $type ) )
            $type = explode( ' ', $type );

        $button_shorthand = array( 'primary', 'small', 'large' );
        $classes = array( 'button' );
        foreach ( $type as $t ) {
            if ( 'secondary' === $t || 'button-secondary' === $t )
                continue;
            $classes[] = in_array( $t, $button_shorthand ) ? 'button-' . $t : $t;
        }
        // Remove empty items, remove duplicate items, and finally build a string.
        $class = implode( ' ', array_unique( array_filter( $classes ) ) );

        $text = $text ? $text : __( 'Save Changes' );

        // Default the id attribute to $name unless an id was specifically provided in $other_attributes
        $id = $name;
        if ( is_array( $other_attributes ) && isset( $other_attributes['id'] ) ) {
            $id = $other_attributes['id'];
            unset( $other_attributes['id'] );
        }

        $attributes = '';
        if ( is_array( $other_attributes ) ) {
            foreach ( $other_attributes as $attribute => $value ) {
                $attributes .= $attribute . '="' . esc_attr( $value ) . '" '; // Trailing space is important
            }
        } elseif ( ! empty( $other_attributes ) ) { // Attributes provided as a string
            $attributes = $other_attributes;
        }

        // Don't output empty name and id attributes.
        $name_attr = $name ? ' name="' . esc_attr( $name ) . '"' : '';
        $id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';

        $button = '<input type="submit"' . $name_attr . $id_attr . ' class="' . esc_attr( $class );
        $button .= '" value="' . esc_attr( $text ) . '" ' . $attributes . ' />';

        if ( $wrap ) {
            $button = '<p class="submit">' . $button . '</p>';
        }

        return $button;
    }
    
    function can_frontend_wizard(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;
        return user_can($community_user_id,$required_cap);
    }

}

function wpsstm_wizard() {
	return WP_SoundSystem_Core_Wizard::instance();
}

wpsstm_wizard();
