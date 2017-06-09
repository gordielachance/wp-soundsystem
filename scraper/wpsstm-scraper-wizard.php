<?php
//TO FIX rather extend WP_SoundSytem_Remote_Tracklist ?
class WP_SoundSytem_Scraper_Wizard{

    var $tracklist;
    
    var $is_advanced = false; //is advanced wizard ?

    var $wizard_sections  = array();
    var $wizard_fields = array();
    
    function __construct($post_id_or_feed_url = null){

        $this->tracklist = wpsstm_live_playlists()->get_preset_tracklist($post_id_or_feed_url);

        $this->tracklist->ignore_cache = ( wpsstm_is_backend() && isset($_REQUEST['advanced_wizard']) );
        $this->tracklist->tracks_strict = false;
        $this->tracklist->load_remote_tracks(true);
        
        $this->is_advanced = ( wpsstm_is_backend() && ( $this->tracklist->ignore_cache || ( $this->tracklist->feed_url && !$this->tracklist->tracks ) ) );

        //metabox
        add_action( 'add_meta_boxes', array($this, 'metabox_scraper_wizard_register') );
        
        //populate settings
        $this->wizard_settings_init();
        
        //scripts & styles
        $this->wizard_register_scripts_styles();  //so we can enqueue them both frontend and backend
        add_action( 'admin_enqueue_scripts', array( $this, 'wizard_scripts_styles_backend' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'wizard_scripts_styles_frontend' ) );
    }

    function wizard_register_scripts_styles(){
        // CSS
        wp_register_style( 'wpsstm-scraper-wizard',  wpsstm()->plugin_url . 'scraper/_inc/css/wpsstm-scraper-wizard.css',null,wpsstm()->version );
        
        // JS
        wp_register_script( 'wpsstm-scraper-wizard', wpsstm()->plugin_url . 'scraper/_inc/js/wpsstm-scraper-wizard.js', array('jquery','jquery-ui-tabs'),wpsstm()->version);
    }
    
    function wizard_scripts_styles_backend(){
        wpsstm_tracklists()->tracklists_script_styles();
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    function wizard_scripts_styles_frontend(){
        wpsstm_tracklists()->tracklists_script_styles();
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    
    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper-wizard', 
            __('Tracklist Importer','wpsstm'),
            array($this,'wizard_display'),
            wpsstm_tracklists()->scraper_post_types, 
            'normal', //context
            'default' //priority
        );

    }
    
    function save_frontend_wizard(){

        if ( !$post_id = $this->tracklist->post_id ){
            
            //TO FIX limit for post creations ? (spam/bots, etc.)

            //user check - guest user
            $user_id = $guest_user_id = null;
            if ( !$user_id = get_current_user_id() ){
                $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
            }

            //capability check
            $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
            $required_cap = $post_type_obj->cap->edit_posts;

            if ( !user_can($user_id,$required_cap) ){
                return new WP_Error( 'wpsstm_wizard_cap_missing', __('You have not the capability required to create a new live playlist','wpsstm') );
            }else{

                if( !$this->tracklist->tracks ){
                    return new WP_Error( 'wpsstm_wizard_empty_tracks', __('No remote tracks found, abord creating live playlist','wpsstm') );
                }

                $post_args = array(
                    'post_title'    => $this->tracklist->title,
                    'post_type'     => wpsstm()->post_type_live_playlist,
                    'post_status'   => 'wpsstm-wizard',
                    'post_author'   => $user_id,
                    'meta_input'   => array(
                        wpsstm_live_playlists()->frontend_wizard_meta_key => true
                    )
                );

                $new_post_id = wp_insert_post( $post_args );
                if ( !is_wp_error($new_post_id) ){
                    $this->tracklist->post_id = $new_post_id;
                }
            }
        }
        
        if ( !$post_id = $this->tracklist->post_id ) return;
        $wizard_url = ( isset($_REQUEST[ 'wpsstm_feed_url' ]) ) ? $_REQUEST[ 'wpsstm_feed_url' ] : null;
        $this->save_feed_url($wizard_url);
        
        //TO FIX
        if ( isset($_REQUEST[ 'save-playlist']) ){
            $post_status = 'publish';
        }

        return $post_id;

    }
    
    function save_backend_wizard(){

        if ( !$post_id = $this->tracklist->post_id ) return;

        if ( isset($_REQUEST[ 'wpsstm_wizard' ]['reset']) ){
            
            delete_post_meta( $post_id, WP_SoundSytem_Remote_Tracklist::$meta_key_scraper_url );
            delete_post_meta( $post_id, WP_SoundSytem_Remote_Tracklist::$live_playlist_options_meta_name );
            
        }else{
            
            $wizard_url = ( isset($_REQUEST[ 'wpsstm_feed_url' ]) ) ? $_REQUEST[ 'wpsstm_feed_url' ] : null;
            $this->save_feed_url($wizard_url);

            if ( $this->is_advanced ){
                $wizard_settings = ( isset($_REQUEST['save-scraper-settings']) ) ? $_REQUEST['save-scraper-settings'] : null;
                $this->save_wizard_settings($wizard_settings);

                //TO FIX set tracklist property 'import_tracks' ?
                if ( isset($_REQUEST['import-tracks'])){
                    if ($this->tracklist->tracks){
                        $this->tracklist->save_subtracks();
                    }
                }
            }

        }
        
        return $post_id;
        
    }
                        
    function save_feed_url($feed_url){
            
        if ( !$post_id = $this->tracklist->post_id ) return;
            
        //save feed url
        $feed_url = trim($feed_url);

        if (!$feed_url){
            return delete_post_meta( $post_id, WP_SoundSytem_Remote_Tracklist::$meta_key_scraper_url );
        }else{
            return update_post_meta( $post_id, WP_SoundSytem_Remote_Tracklist::$meta_key_scraper_url, $feed_url );
        }
    }
                        
    function save_wizard_settings($wizard_data){

        if ( !$wizard_data ) return;
        if ( !$post_id = $this->tracklist->post_id ) return;

        //save wizard settings
        $wizard_settings = ( isset($wizard_data[ 'wpsstm_wizard' ]) ) ? $wizard_data[ 'wpsstm_wizard' ] : null;

        //while updating the live tracklist settings, ignore caching
        $this->tracklist->delete_cache();

        if ( !$wizard_settings ) return;

        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //keep only NOT default values
        $default_args = $this->tracklist->options_default;
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_args);

        if (!$wizard_settings){
            delete_post_meta($post_id, WP_SoundSytem_Remote_Tracklist::$live_playlist_options_meta_name);
        }else{
            update_post_meta( $post_id, WP_SoundSytem_Remote_Tracklist::$live_playlist_options_meta_name, $wizard_settings );
        }

        do_action('spiff_save_wizard_settings', $wizard_settings, $post_id);

    }

    function wizard_settings_init(){

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
        
        if ($this->tracklist->tracks){
            $this->add_wizard_field(
                'feedback_tracklist_content', 
                __('Tracklist','wpsstm'), 
                array( $this, 'feedback_tracklist_callback' ), 
                'wpsstm-wizard-step-source', 
                'wizard_section_source_feedback'
            );
        }
        
        if ( $this->is_advanced ){
            
            /*
            Source feedback
            */

            if ( $this->tracklist->variables ){
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
                __('Enable tracks cache','wpsstm'), 
                array( $this, 'cache_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );

            $this->add_wizard_field(
                'enable_musicbrainz', 
                __('Use MusicBrainz','wpsstm'), 
                array( $this, 'musicbrainz_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );

        }
        

    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_wizard_settings($input){

        $previous_values = $this->tracklist->get_options();
        $new_input = $previous_values;
        
        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //cache
        if ( isset($input['datas_cache_min']) && ctype_digit($input['datas_cache_min']) ){
            $new_input['datas_cache_min'] = $input['datas_cache_min'];
        }

        //selectors 

        foreach ((array)$input['selectors'] as $selector_slug=>$value){

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

        //order
        $new_input['tracks_order'] = ( isset($input['tracks_order']) ) ? $input['tracks_order'] : null;

        //musicbrainz
        $new_input['musicbrainz'] = ( isset($input['musicbrainz']) ) ? $input['musicbrainz'] : null;
        
        $default_args = $default_args = $this->tracklist->options_default;
        $new_input = array_replace_recursive($default_args,$new_input); //last one has priority

        return $new_input;
    }
    
    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="wpsstm-wizard-selector-toggle-advanced"><i class="fa fa-cog" aria-hidden="true"></i></a>',
            __('Use Regular Expression','wpsstm')
        );
    }
    
    function css_selector_block($selector){
        
        ?>
        <div class="wpsstm-wizard-selector">
            <?php

            //path
            $path = $this->tracklist->get_options( array('selectors',$selector,'path') );
            $path = ( $path ? htmlentities($path) : null);

            //regex
            $regex = $this->tracklist->get_options( array('selectors',$selector,'regex') );
            $regex = ( $regex ? htmlentities($regex) : null);
        
            //attr
            $attr_disabled = ( $this->tracklist->response_type != 'text/html');
            $attr = $this->tracklist->get_options( array('selectors',$selector,'attr') );
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

        $option = $this->tracklist->feed_url;

        printf(
            '<input type="text" name="wpsstm_feed_url" value="%s" class="fullwidth" placeholder="%s" />',
            $option,
            __('URL of the tracklist you would like to get','wpsstm')
        );
        
        //presets
        $presets_list = array();
        $presets_list_str = null;
        foreach ((array)WP_SoundSytem_Core_Live_Playlists::get_available_presets() as $preset){
            if ( !$preset->wizard_suggest ) continue;
            $preset_str = $preset->preset_name;
            if ($preset->preset_url){
                $preset_str = sprintf('<a href="%s" title="%s" target="_blank">%s</a>',$preset->preset_url,$preset->preset_desc,$preset_str);
            }
            $presets_list[] = $preset_str;
        }

        if ( !empty($presets_list) ){
            $presets_list_str = implode(', ',$presets_list);
            printf('<p><small><strong>%s</strong> : %s</small></p>',__('Available presets','wpsstm'),$presets_list_str);
        }
        

        

    }

    function feedback_data_type_callback(){

        $output = "—";

        if ( $this->tracklist->response_type ){
            $output = $this->tracklist->response_type;
        }
        
        echo $output;

    }
    
    function feedback_regex_matches_callback(){

        foreach($this->tracklist->variables as $variable_slug => $variable){
            $value_str = ( $variable ) ? sprintf('<code>%s</code>',$variable) : '—';
            printf('<p><strong>%s :</strong> %s',$variable_slug,$value_str);
        }
    }   

    function feedback_source_content_callback(){

        $output = "—";
        
        if ( $body_node = $this->tracklist->body_node ){
            
            $content = $body_node->html();

            //force UTF8
            $content = iconv("ISO-8859-1", "UTF-8", $content); //ISO-8859-1 is from QueryPath

            $content = esc_html($content);
            $output = '<pre class="spiff-raw"><code class="language-markup">'.$content.'</code></pre>';

        }
        
        echo $output;
        

    }
    
    function section_tracks_desc(){

        printf(
            __('Enter a <a href="%s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %s.','wpsstm'),
            'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
            '<code>#content #tracklist .track</code>'
        );
        
        $this->tracklist->display_notices('wizard-step-tracks');
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function feedback_tracks_callback(){

        $output = "—"; //none
        $tracks_output = array();
        
        if ( $track_nodes = $this->tracklist->track_nodes ){

            foreach ($track_nodes as $single_track_node){
                
                $single_track_html = $single_track_node->innerHTML();

                //force UTF8
                $single_track_html = iconv("ISO-8859-1", "UTF-8", $single_track_html); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($single_track_html) );

            }
            if ($tracks_output){
                
                //reverse
                if ( $this->tracklist->get_options('tracks_order') == 'asc' ){
                    $tracks_output = array_reverse($tracks_output);
                }
                
                $output = sprintf('<div id="spiff-station-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
            }

            
        }


        echo $output;

    }

    function section_single_track_desc(){
        
        $jquery_selectors_link = sprintf('<a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">%s</a>',__('jQuery selectors','wpsstm'));
        $regexes_link = sprintf('<a href="http://regex101.com" target="_blank">%s</a>',__('regular expressions','wpsstm'));

        printf(__('Enter a %s to extract the data for each track.','wpsstm'),$jquery_selectors_link);
        echo"<br/>";
        printf(__('It is also possible to target the attribute of an element or to filter the data with a %s by using %s advanced settings for each item.','wpsstm'),$regexes_link,'<i class="fa fa-cog" aria-hidden="true"></i>');
        
        $this->tracklist->display_notices('wizard-step-single-track');
        
    }
    
    function get_track_detail_selector_prefix(){

        $selector = $this->tracklist->get_options(array('selectors','tracks','path'));

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
    
    function feedback_tracklist_callback(){
        echo $this->tracklist->get_tracklist_table();
    }

    function cache_callback(){
        $option = $this->tracklist->get_options('datas_cache_min');

        printf(
            '<input type="number" name="%1$s[datas_cache_min]" size="4" min="0" value="%2$s" /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            $option,
            __('Time the remote tracks should be cached (in minutes).','spiff')
        );

        
    }

    function musicbrainz_callback(){
        
        $option = $this->tracklist->get_options('musicbrainz');
        
        printf(
            '<input type="checkbox" name="%1$s[musicbrainz]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked((bool)$option, true, false),
            sprintf(
                __('Try to fix tracks information using <a href="%1$s" target="_blank">MusicBrainz</a>.'),
                'http://musicbrainz.org/').'  <small>'.__('This makes the station render slower : each track takes about ~1 second to be checked!').'</small>'
        );

        
    }
    
    function tracks_order_callback(){
        
        $option = $this->tracklist->get_options('tracks_order');
        
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
    
    function wizard_display(){
        
        $classes = array();
        $classes[]  = ( $this->is_advanced ) ? 'wizard-wrapper-advanced' : 'wizard-wrapper-simple';
        $classes[]  = ( is_admin() ) ? 'wizard-wrapper-backend' : 'wizard-wrapper-frontend';
        
        ?>
        <div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr($classes);?>>
            <?php

            $reset_checked = false;

            $this->tracklist->display_notices('wizard-header');

            if ( !$this->is_advanced ){
                $this->wizard_simple();
            }else{

                $this->tracklist->display_notices('wizard-header-advanced');

                $this->wizard_advanced();
            }

            if ( wpsstm_is_backend() ){
                $post_type = get_post_type();
                if ( ($post_type != wpsstm()->post_type_live_playlist ) && ($this->tracklist->tracks) ){
                    $reset_checked = true;
                    $this->submit_button(__('Import Tracks','wpsstm'),'primary','import-tracks');

                }
            }elseif( $this->tracklist->tracks ){
                
                //user check - guest user
                $user_id = $guest_user_id = null;
                if ( !$user_id = get_current_user_id() ){
                    $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
                }
                
                if ( $user_id ){
                    
                    //capability check
                    $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
                    $required_cap = $post_type_obj->cap->edit_posts;
                    
                    if ( current_user_can($required_cap) ){
                        $this->submit_button(__('Save Playlist','wpsstm'),'primary','save-playlist');
                    }
                    
                }

            }

            $submit_bt_txt = ( !$this->is_advanced ) ? __('Load URL','wpsstm') : __('Save Changes');
            $this->submit_button($submit_bt_txt,'primary','save-scraper-settings');

            if ( $this->tracklist->feed_url && wpsstm_is_backend() ){

                printf(
                    '<small><input type="checkbox" name="%1$s[reset]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span></small>',
                    'wpsstm_wizard',
                    checked($reset_checked, true, false),
                    __('Clear wizard','wpsstm')
                );
            }
        
            if ( $this->is_advanced ){
                ?>
                <input type="hidden" name="advanced_wizard" value="1" />
                <?php
            }
            if ( $this->tracklist->post_id ){
                printf('<input type="hidden" name="%s[post_id]" value="%s" />','wpsstm_wizard',$this->tracklist->post_id);
            }

            wp_nonce_field('wpsstm_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
            ?>
        </div>
        <?php
        
    }
    
    private function wizard_simple(){
        ?>

        <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
            <?php $this->do_wizard_sections( 'wpsstm-wizard-step-source' );?>
        </div>
        <?php
        
        if ( wpsstm_is_backend() ){
            if ( $this->tracklist->feed_url && !isset($_REQUEST['advanced_wizard']) ){
                $advanced_wizard_url = get_edit_post_link();
                $advanced_wizard_url = add_query_arg(array('advanced_wizard'=>true),$advanced_wizard_url);
                echo '<p><a href="'.$advanced_wizard_url.'">' . __('Advanced Settings','wpsstm') . '</a></p>';
            }
        }
    }
    
    private function wizard_advanced(){

        ?>
        <div id="wpsstm-wizard-tabs">

            <ul id="wpsstm-wizard-tabs-header">
                <?php $this->wizard_tabs(); ?>
            </ul>

            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-source' );?>
            </div>

            <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-tracks' );?>
            </div>

            <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-single-track' );?>
            </div>

            <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-options' );?>
            </div>

        </div>
        <?php
    }
    
    function wizard_tabs( $active_tab = '' ) {

        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $source_tab = $tracks_selector_tab = $track_details_tab = $options_tab = $tracklist_tab = array();
        
        $status_icons = array(
            '<i class="fa fa-times-circle" aria-hidden="true"></i>',
            '<i class="fa fa-check-circle" aria-hidden="true"></i>'
        );

        $icon_source_tab = $status_icons[0];
        if ( $this->tracklist->body_node ){
            $icon_source_tab = $status_icons[1];
        }

        $source_tab = array(
            'icon'    => $icon_source_tab,
            'title'     => __('Source','spiff'),
            'href'      => '#wpsstm-wizard-step-source-content'
        );

        $icon_tracks_tab = $status_icons[0];
        if ( $this->tracklist->track_nodes ){
            $icon_tracks_tab = $status_icons[1];
        }

        $tracks_selector_tab = array(
            'icon'    => $icon_tracks_tab,
            'title'  => __('Tracks','spiff'),
            'href'  => '#wpsstm-wizard-step-tracks-content'
        );

        $icon_track_details_tab = $status_icons[0];

        if ( $this->tracklist->tracks ){
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
    Inspired by WP function submit_button()
    */
    
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
        echo $this->get_submit_button( $text, $type, $name, $wrap, $other_attributes );
    }
    
    /*
    Inspired by WP function get_submit_button()
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

    
}
