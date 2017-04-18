<?php

class WP_SoundSytem_Playlist_Scraper_Wizard{

    var $scraper;
    
    var $frontend = false;
    var $advanced = true; //advanced wizard ?

    var $wizard_sections  = array();
    var $wizard_fields = array();
    
    function __construct($post_id_or_feed_url = null){
        
        $this->scraper = new WP_SoundSytem_Playlist_Scraper();
        $this->scraper->is_wizard = true;

        //populate post ID or URL
        if ( $post_id_or_feed_url ){
            if ( ctype_digit($post_id_or_feed_url) ) { //post ID
                $this->scraper->init_post($post_id_or_feed_url);
            }else{ //url
                $this->scraper->init($post_id_or_feed_url);
            }
        }

        $tracklist_validated = clone $this->scraper->tracklist;
        $tracklist_validated->validate_tracks();
        
        $this->frontend = ( !is_admin() );
        $this->advanced = ( (!$this->frontend) && ( ( $this->scraper->feed_url && !$tracklist_validated->tracks ) || isset($_REQUEST['advanced_wizard']) ) );
        
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
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    function wizard_scripts_styles_frontend(){
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    
    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper-wizard', 
            __('Tracklist Parser','wpsstm'),
            array($this,'wizard_display'),
            wpsstm_tracklists()->scraper_post_types, 
            'normal', //context
            'default' //priority
        );

    }
    
    function save_wizard($post_id){

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = false;
        if ( isset($_POST[ 'wpsstm_scraper_wizard_nonce' ]) && wp_verify_nonce( $_POST['wpsstm_scraper_wizard_nonce'], 'wpsstm_scraper_wizard')) $is_valid_nonce=true;

        if ($is_autosave || $is_revision || !$is_valid_nonce) return;
        
        $post_type = get_post_type();

        if ( isset($_POST['save-scraper-settings'])){
        
            $success = false;

            //save feed url
            $feed_url = ( isset($_POST[ 'wpsstm_feed_url' ]) ) ? $_POST[ 'wpsstm_feed_url' ] : null;
            $feed_url = trim($feed_url);
            update_post_meta( $post_id, WP_SoundSytem_Playlist_Scraper::$meta_key_scraper_url, $feed_url );

            //save wizard settings
            $wizard_settings = ( isset($_POST[ 'wpsstm_wizard' ]) ) ? $_POST[ 'wpsstm_wizard' ] : null;
            $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

            $wizard_settings_new = array();
            $default_args = $this->scraper->get_default_options();

            //ignore default values
            foreach ( $default_args as $slug => $default ){
                if ( !isset($wizard_settings[$slug]) ) continue;
                if ($wizard_settings[$slug]==$default) continue;
                $wizard_settings_new[$slug] = $wizard_settings[$slug];
            }

            if ($success = update_post_meta( $post_id, WP_SoundSytem_Playlist_Scraper::$meta_key_options_scraper, $wizard_settings_new )){
                do_action('spiff_save_wizard_settings', $wizard_settings_new, $post_id);
                
            }
        }
        
        if ( isset($_POST['import-tracks'])){
            if ($this->scraper->tracklist->tracks){
                $this->scraper->tracklist->save_subtracks();
            }
        }

        if ( isset($_POST[ 'wpsstm_wizard' ]['reset']) ){
            delete_post_meta( $post_id, WP_SoundSytem_Playlist_Scraper::$meta_key_scraper_url );
            delete_post_meta( $post_id, WP_SoundSytem_Playlist_Scraper::$meta_key_options_scraper );
            $this->scraper->delete_cache();
        }

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
        
        if ($this->scraper->tracklist->tracks){
            $this->add_wizard_field(
                'feedback_tracklist_content', 
                __('Tracklist','wpsstm'), 
                array( $this, 'feedback_tracklist_callback' ), 
                'wpsstm-wizard-step-source', 
                'wizard_section_source_feedback'
            );
        }
        
        if (!$this->advanced){
            
        }else{
            
            /*
            Source feedback
            */

            if ( $this->scraper->page->variables ){
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
                'track_location_selector', 
                __('File Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_location_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_image_selector', 
                __('Image Selector','wpsstm'), 
                array( $this, 'track_image_selector_callback' ), 
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
                __('Enable Cache','wpsstm'), 
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

        $previous_values = $this->scraper->get_options();
        $new_input = $previous_values;
        
        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //cache
        if ( isset($input['datas_cache_min']) && ctype_digit($input['datas_cache_min']) ){
            $new_input['datas_cache_min'] = $input['datas_cache_min'];
        }
        
        //cache has been disabled, delete existing cache
        if ( !isset($new_input['datas_cache_min']) && isset($previous_values['datas_cache_min']) && ( $this->scraper->page->datas_cache ) ) {
            $this->scraper->delete_cache();
        }

        //selectors 

        foreach ((array)$input['selectors'] as $selector_slug=>$value){

            //path
            if ( isset($value['path']) ) {
                $value['path'] = trim($value['path']);
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

        return $new_input;
    }
    
    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="regex-link">[...^]</a>',
            __('Use Regular Expression','wpsstm')
        );
    }
    
    function css_selector_block($selector){
        
        ?>
        <div class="wizard-selector">
            <?php

            //path
            $path = $this->scraper->get_options( array('selectors',$selector,'path') );
            $path = ( $path ? htmlentities($path) : null);
            
        
            //regex
            $regex = $this->scraper->get_options( array('selectors',$selector,'regex') );
            $regex = ( $regex ? htmlentities($regex) : null);
            
            

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
                            '<code>.album-art img</code> '.__('or an url','wpsstm')
                        );
                    break;
                    case 'track_location':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>audio</code> '.__('or an url','wpsstm')
                        );
                    break;
            }
            
            if ($selector!='tracks'){
                echo $this->get_track_detail_selector_prefix();
            }
            
            printf(
                '<input type="text" name="%1$s[selectors][%2$s][path]" value="%3$s" />',
                'wpsstm_wizard',
                $selector,
                $path
            );
        
            if ($info){
                printf('<span class="wizard-field-desc">%s</span>',$info);
            }

            //regex
            //uses a table so the style matches with the global form (WP-core styling)
            ?>
            <table class="form-table regex-row">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Regex pattern','wpsstm');?></th>
                        <td>        
                            <div>
                                <?php

                                printf(
                                    '<span class="regex-field"><input class="regex" name="%1$s[selectors][%2$s][regex]" type="text" value="%3$s"/></span>',
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
        <?php
        
    }
    
    function section_desc_empty(){
    }


    function feed_url_callback(){

        $option = $this->scraper->feed_url;

        printf(
            '<input type="text" name="wpsstm_feed_url" value="%s" class="fullwidth" placeholder="%s" />',
            $option,
            __('URL of the tracklist you would like to get','wpsstm')
        );
        
        //presets
        $presets_list = array();
        $presets_list_str = null;
        foreach ((array)wpsstm_live_playlists()->available_presets as $preset){
            $presets_list[] = $preset->name;
        }
        $presets_list_str = implode(', ',$presets_list);
        
        printf('<p><small><strong>%s</strong> : %s</small></p>',__('Available presets','wpsstm'),$presets_list_str);
        

    }

    function feedback_data_type_callback(){

        $output = "—";

        if ( $this->scraper->page->response_type ){
            $output = $this->scraper->page->response_type;
        }
        
        echo $output;

    }
    
    function feedback_regex_matches_callback(){

        foreach($this->scraper->page->variables as $variable_slug => $variable){
            $value_str = ( $variable ) ? sprintf('<code>%s</code>',$variable) : '—';
            printf('<p><strong>%s :</strong> %s',$variable_slug,$value_str);
        }
    }
    

    function feedback_source_content_callback(){

        $output = "—";
        
        if ( $body_node = $this->scraper->page->body_node ){
            
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
            __('Enter a <a href="%1$s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %2$s.','wpsstm'),
            'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
            '<code>#content #tracklist .track</code>'
        );
        
        $this->scraper->display_notices('wizard-step-tracks');
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function feedback_tracks_callback(){

        $output = "—"; //none
        $tracks_output = array();
        
        if ( $track_nodes = $this->scraper->page->track_nodes ){

            foreach ($track_nodes as $single_track_node){
                
                $single_track_html = $single_track_node->innerHTML();

                //force UTF8
                $single_track_html = iconv("ISO-8859-1", "UTF-8", $single_track_html); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($single_track_html) );

            }
            if ($tracks_output){
                
                //reverse
                if ( $this->scraper->get_options('tracks_order') == 'asc' ){
                    $tracks_output = array_reverse($tracks_output);
                }
                
                $output = sprintf('<div id="spiff-station-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
            }

            
        }


        echo $output;

    }

    function section_single_track_desc(){

        _e('Enter a <a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">jQuery selectors</a> to extract the artist, title, album (optional) and image (optional) for each track.','spiff');
        echo"<br/>";
        _e('Advanced users can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches, using the links <strong>[...^]</strong>.','spiff');
        
        $this->scraper->display_notices('wizard-step-single-track');
        
    }
    
    function get_track_detail_selector_prefix(){

        $selector = $this->scraper->get_options(array('selectors','tracks','path'));

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
    
    function track_location_selector_callback(){
        $this->css_selector_block('track_location');
    }
    
    function feedback_tracklist_callback(){
        echo $this->scraper->tracklist->get_tracklist_table();
    }

    function cache_callback(){
        $option = $this->scraper->get_options('datas_cache_min');

        printf(
            '<input type="number" name="%1$s[datas_cache_min]" size="4" min="0" value="%2$s" /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            $option,
            __('Time the remote tracks should be cached (in minutes).','spiff')
        );

        
    }
    
    function musicbrainz_callback(){
        
        $option = $this->scraper->get_options('musicbrainz');
        
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
        
        $option = $this->scraper->get_options('tracks_order');
        
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
        $classes[]  = ($this->advanced) ? 'wizard-wrapper-advanced' : 'wizard-wrapper-simple';
        $classes[]  = ( is_admin() ) ? 'wizard-wrapper-backend' : 'wizard-wrapper-frontend';
        
        ?>
        <div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr($classes);?>>
            <?php

            $reset_checked = false;

            $this->scraper->display_notices('wizard-header');

            if (!$this->advanced){
                $this->wizard_simple();
            }else{

                $this->scraper->display_notices('wizard-header-advanced');

                $this->wizard_advanced();
            }

            if ( !$this->frontend){
                $post_type = get_post_type();
                if ( ($post_type != wpsstm()->post_type_live_playlist ) && ($this->scraper->tracklist->tracks) ){
                    $reset_checked = true;
                    $this->submit_button(__('Import Tracks','wpsstm'),'primary','import-tracks');

                }
            }


            $submit_bt_txt = (!$this->advanced) ? __('Load URL','wpsstm') : __('Save Changes');
            $this->submit_button($submit_bt_txt,'primary','save-scraper-settings');

            if ( $this->scraper->feed_url && !$this->frontend ){

                printf(
                    '<small><input type="checkbox" name="%1$s[reset]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span></small>',
                    'wpsstm_wizard',
                    checked($reset_checked, true, false),
                    __('Clear wizard','wpsstm')
                );
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
        
        if ( !$this->frontend ){
            if ( $this->scraper->feed_url && !isset($_REQUEST['advanced_wizard']) ){
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

            <?php
       
            if ($this->can_show_step('tracks_selector')){
                ?>
                <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                    <?php $this->do_wizard_sections( 'wpsstm-wizard-step-tracks' );?>
                </div>
                <?php
            }
            ?>

            <?php         
            if ($this->can_show_step('track_details')){
                ?>
                <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                    <?php $this->do_wizard_sections( 'wpsstm-wizard-step-single-track' );?>
                </div>
                <?php
            }
            ?>

            <?php
            if ($this->can_show_step('playlist_options')){
                ?>
                <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                    <?php $this->do_wizard_sections( 'wpsstm-wizard-step-options' );?>
                </div>
                <?php
            }
            ?>
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
                    
        if ($this->can_show_step('source')){

            $icon_source_tab = $status_icons[0];
            if ( $this->scraper->page->body_node ){
                $icon_source_tab = $status_icons[1];
            }
            
            $source_tab = array(
                'icon'    => $icon_source_tab,
                'title'     => __('Source','spiff'),
                'href'      => '#wpsstm-wizard-step-source-content'
            );
        }

        if ($this->can_show_step('tracks_selector')){
            
            $icon_tracks_tab = $status_icons[0];
            if ( $this->scraper->page->track_nodes ){
                $icon_tracks_tab = $status_icons[1];
            }
            
            $tracks_selector_tab = array(
                'icon'    => $icon_tracks_tab,
                'title'  => __('Tracks','spiff'),
                'href'  => '#wpsstm-wizard-step-tracks-content'
            );
        }
        
        if ($this->can_show_step('track_details')){
            
            $icon_track_details_tab = $status_icons[0];
            $tracklist_validated = clone $this->scraper->tracklist;
            $tracklist_validated->validate_tracks();
            
            if ( $tracklist_validated->tracks ){
                $icon_track_details_tab = $status_icons[1];
            }
            
            $track_details_tab = array(
                'icon'    => $icon_track_details_tab,
                'title'  => __('Track details','spiff'),
                'href'  => '#wpsstm-wizard-step-single-track-content'
            );
        }
        
        if ($this->can_show_step('playlist_options')){
            $options_tab = array(
                'title'  => __('Options','spiff'),
                'href'  => '#wpsstm-wizard-step-options'
            );
        }


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
    
    function can_show_step($slug){
        
        return true;

        switch ($slug){
            case 'source':
                return true;
            break;
            case 'tracks_selector':
                
                //TO FIX TO UNCOMMENT
                //if ( !$this->scraper->page ) break;
                //if ( !$this->scraper->page->body_node ) break;
                
                return true;
            break;
            case 'track_details':
                if ( !$this->can_show_step('tracks_selector') ) break;
                return true;
                
            break;
            
            case 'playlist_options':
                if ( !$this->post_id ) break;
                return true;
            break;
            
        }
        return false;
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
