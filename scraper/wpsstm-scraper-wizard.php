<?php

class WP_SoundSytem_Playlist_Scraper_Wizard{

    var $scraper;
    var $advanced = true; //advanced wizard ?
    
    function __construct($post_id){
        
        $this->scraper = new WP_SoundSytem_Playlist_Scraper();
        $this->scraper->is_wizard = true;
        $this->scraper->init_post($post_id);
        $this->advanced = ( ( $this->scraper->feed_url && !$this->scraper->tracklist->tracks ) || isset($_REQUEST['advanced_wizard']) );
        //$this->advanced = false;

        //populate settings
        $this->wizard_settings_init();

        //populate metabox
        add_action( 'add_meta_boxes', array($this, 'metabox_scraper_wizard_register') );
        add_action( 'admin_enqueue_scripts', array( $this, 'metabox_wizard_scripts_styles' ) );

    }
    
    function metabox_wizard_scripts_styles(){
        // CSS
        wp_enqueue_style( 'wpsstm-admin-metabox-scraper',  wpsstm()->plugin_url . 'scraper/_inc/css/wpsstm-admin-metabox-scraper.css',wpsstm()->version );
        
        // JS
        wp_enqueue_script( 'wpsstm-admin-metabox-scraper', wpsstm()->plugin_url . 'scraper/_inc/js/wpsstm-admin-metabox-scraper.js', array('jquery','jquery-ui-tabs'),wpsstm()->version);
    }
    
    function metabox_scraper_wizard_register(){

        $metabox_id = ($this->advanced) ? 'wpsstm-scraper-advanced' : 'wpsstm-scraper-simple';
        
        

        add_meta_box( 
            $metabox_id, 
            __('Tracklist Parser','wpsstm'),
            array($this,'wizard'),
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
            $this->scraper->page->delete_cache();
        }

    }

    function wizard_settings_init(){
        
        //$this->reduce_settings_errors();
        
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

        add_settings_section(
             'wizard_section_source_feedback', //id
             __('Feedback','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );
        
        if ($this->scraper->tracklist->tracks){
            add_settings_field(
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



            if ( $this->scraper->preset && isset($this->scraper->preset->variables) ){
                add_settings_field(
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
                __('Track details','wpsstm'),
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
                'track_location_selector', 
                __('File Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_location_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            add_settings_field(
                'track_image_selector', 
                __('Image Selector','wpsstm'), 
                array( $this, 'track_image_selector_callback' ), 
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
                __('Enable Cache','wpsstm'), 
                array( $this, 'cache_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );

            add_settings_field(
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
            $this->scraper->page->delete_cache();
        }

        //website URL
        $new_input['website_url'] = trim($input['website_url']);

        //regexes
        if ( isset($input['regexes']) ){
            foreach($input['regexes'] as $regex){
                $new_input['regexes'][] = trim($regex);
            }
            $new_input['regexes'] = array_unique($new_input['regexes']);
            $new_input['regexes'] = array_filter($new_input['regexes']);
        }

        //selectors 

        foreach ($input['selectors'] as $selector_slug=>$value){

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
            '<input type="text" name="wpsstm_feed_url" value="%s" class="fullwidth" />',
            $option
        );

    }

    
    function feedback_data_type_callback(){
        
        settings_errors('wizard-row-feed_content_type');

        $output = "—";

        if ( $this->scraper->page->response_type ){
            $output = $this->scraper->page->response_type;
        }
        
        echo $output;

    }
    
    function feedback_regex_matches_callback(){
        $variables = $this->scraper->preset->variables;

        foreach($variables as $variable_slug => $variable){
            $value_str = ( isset($variable['value']) ) ? sprintf('<code>%s</code>',$variable['value']) : '—';
            printf('<p><strong>%s <small>(%s)</small>:</strong> %s',$variable['name'],$variable_slug,$value_str);
        }
    }
    

    function feedback_source_content_callback(){

        $output = "—";
        
        if ( $source_content = $this->scraper->page->response_body ){

            $content = $source_content->html();
            
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
        
        settings_errors('wizard-step-tracks_selector');
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function feedback_tracks_callback(){

        $output = "—"; //none
        $tracks_output = array();
        
        if ( $nodes = $this->scraper->page->track_nodes ){

            foreach ($nodes as $node){

                $node_content = $node->innerHTML();
                
                //force UTF8
                $node_content = iconv("ISO-8859-1", "UTF-8", $node_content); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($node_content) );

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
    
    function wizard(){
        
        $reset_checked = false;
        $post_type = get_post_type();
        
        settings_errors('wizard-header');
        if (!$this->advanced){
            $this->wizard_simple();
        }else{
            settings_errors('wizard-header-advanced');
            $this->wizard_advanced();
        }
        
        if ( ($post_type != wpsstm()->post_type_live_playlist ) && ($this->scraper->tracklist->tracks) ){
            $reset_checked = true;
            submit_button(__('Import Tracks','wpsstm'),'primary','import-tracks');

        }
        
        
        submit_button(__('Save Changes'),'primary','save-scraper-settings');
        
        if ( $this->scraper->feed_url ){

            printf(
                '<small><input type="checkbox" name="%1$s[reset]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span></small>',
                'wpsstm_wizard',
                checked($reset_checked, true, false),
                __('Clear wizard','wpsstm')
            );
        }
        
        wp_nonce_field('wpsstm_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
        
    }
    
    private function wizard_simple(){
        ?>

        <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
            <?php do_settings_sections( 'wpsstm-wizard-step-source' );?>
        </div>
        <?php
        
            
        if ( $this->scraper->feed_url && !isset($_REQUEST['advanced_wizard']) ){
            $advanced_wizard_url = get_edit_post_link();
            $advanced_wizard_url = add_query_arg(array('advanced_wizard'=>true),$advanced_wizard_url);
            echo '<p><a href="'.$advanced_wizard_url.'">' . __('Advanced Settings','wpsstm') . '</a></p>';
        }
    }
    
    private function wizard_advanced(){

        ?>
        <div id="wpsstm-wizard-tabs">
            <?php settings_errors('wizard-header-advanced');?>

            <ul id="wpsstm-wizard-tabs-header">
                <?php $this->wizard_tabs(); ?>
            </ul>

            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php do_settings_sections( 'wpsstm-wizard-step-source' );?>
            </div>

            <?php
       
            if ($this->can_show_step('tracks_selector')){
                ?>
                <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-tracks' );?>
                </div>
                <?php
            }
            ?>

            <?php         
            if ($this->can_show_step('track_details')){
                ?>
                <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-single-track' );?>
                </div>
                <?php
            }
            ?>

            <?php
            if ($this->can_show_step('playlist_options')){
                ?>
                <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                    <?php do_settings_sections( 'wpsstm-wizard-step-options' );?>
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
            if ( $this->scraper->page->response_body ){
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
            if ( $this->scraper->tracklist->tracks ){
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
                //if ( !$this->scraper->page->response_body ) break;
                
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
    
    /**
     * Removes duplicate settings errors (based on their messages)
     * @global type $wp_settings_errors
     */
    
    function reduce_settings_errors(){
        //remove duplicates errors
        global $wp_settings_errors;

        if (empty($wp_settings_errors)) return;
        $wp_settings_errors = array_values(array_unique($wp_settings_errors, SORT_REGULAR));

    }
    
}
