<?php
class WPSSTM_Core_Importer{

    static $is_wizard_tracklist_metakey = '_wpsstm_is_wizard';
    static $importers_transient_name = 'wpsstmapi_importers';

    function __construct(){

        //frontend
        add_action( 'wp', array($this,'handle_frontend_importer' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );
        add_filter( 'the_content', array($this,'frontend_importer_content'));
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_ignore_bot_tracklists') );

        //backend
        add_action( 'add_meta_boxes', array($this, 'metabox_importer_register'), 11 );
        add_action( 'save_post', array($this,'metabox_save_importer_settings') );

        add_action( 'admin_enqueue_scripts', array( $this, 'importer_register_scripts_styles' ) );

        /*
        AJAX
        */

    }

    /*
    Usually, we don't want bot playlists; it's only used by the importer.
    So ignore those playlists frontend.
    */

    function pre_get_posts_ignore_bot_tracklists( $query ){

        //main query check
        if ( !$query->is_main_query() ) return $query;

        //archive check
        if ( $query->is_singular() ) return $query;

        //post type check
        $post_type = $query->get('post_type');
        if ( !in_array($post_type,wpsstm()->tracklist_post_types) ) return $query;

        //we HAVE an author query
        if ( $query->get('author') || $query->get('author_name') || $query->get('author__in') ) return $query;

        if ( !$bot_id = wpsstm()->get_options('bot_user_id') ) return $query;

        //ignore bot posts
        $author_not_in = $query->get('author__not_in');
        $author_not_in[] = $bot_id;
        $query->set('author__not_in',$author_not_in);

        return $query;
    }

    /*
    We're requesting the frontend wizard page, load the wizard template
    */

    function frontend_importer_content($content){
        if ( !is_page(wpsstm()->get_options('importer_page_id')) ) return $content;

        //check bot user
        $bot_ready = wpsstm()->is_bot_ready();
        if ( is_wp_error($bot_ready) ){
            WP_SoundSystem::debug_log('Bot user not ready','Frontend import template' );
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
        wp_register_script( 'wpsstm-importer', wpsstm()->plugin_url . '_inc/js/wpsstm-importer.js',array('jquery'),wpsstm()->version);

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

        //TOUFIX we should be able to import (append) tracks to a static playlist without having to create a radio first.

        if ($wpsstm_tracklist->tracklist_type=='live'){
            wpsstm_locate_template( 'tracklist-importer.php', true );
        }else{
            $notice = __("For now, the only way to import a tracklist is to create a new Radio (not a Playlist), fill the 'Tracklist Importer' metabox, then click the 'Stop Sync' button under the Radio header.  This will convert the Radio to a Playlist.",'wpsstm');
            printf('<div class="notice notice-warning inline"><p>%s</p></div>',$notice);
        }



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

        //feed URL -sanitized as a string because could be a bang too.
        $old_feed_url = $tracklist->feed_url;
        $new_feed_url = sanitize_text_field( wpsstm_get_array_value('feed_url',$data) );

        if ($new_feed_url != $old_feed_url){

          if ($new_feed_url){
              update_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name,$new_feed_url);
          }else{
              delete_post_meta( $post_id, WPSSTM_Post_Tracklist::$feed_url_meta_name);
          }

          //force refresh importer slug
          delete_post_meta( $post_id, WPSSTM_Core_Radios::$importer_slug_meta_name);
        }

        //website URL
        $website_url = esc_url_raw( wpsstm_get_array_value('website_url',$data) );

        if ($website_url){
            update_post_meta( $post_id, WPSSTM_Post_Tracklist::$website_url_meta_name,$website_url);
        }else{
            delete_post_meta( $post_id, WPSSTM_Post_Tracklist::$website_url_meta_name);
        }

        /*
        importer
        */

        $old_importer_options = get_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name,true);
        $new_importer_options = self::sanitize_importer_settings($data);

        //TOUFIX URGENT sanitize all datas ?

        //settings have been updated, clear tracklist cache
        if ($old_importer_options != $new_importer_options){
            //TOUFIX OR if cache time has been updated ?
            WP_SoundSystem::debug_log('scraper settings have been updated, clear import timestamp.','Save wizard' );
            $tracklist->set_is_expired();
        }

        if (!$new_importer_options){
            $success = delete_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name);
        }else{
            $success = update_post_meta($post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name, $new_importer_options);
        }

        //reload settings
        $tracklist->populate_tracklist_post();

        return $success;

    }

    /*
    Create a tracklist from the frontend wizard search input and redirect to it.
    Set the bot user as post author so we can detect it as a wizard tracklist.
    */

    function handle_frontend_importer(){

        global $wpsstm_tracklist;

        if ( !wpsstm()->get_options('importer_page_id') ) return;

        $url = sanitize_text_field( wpsstm_get_array_value('wpsstm_frontend_wizard_url',$_POST) );
        if (!$url) return;

        //check bot user
        $bot_ready = wpsstm()->is_bot_ready();
        if ( is_wp_error($bot_ready) ){
            WP_SoundSystem::debug_log('Bot user not ready','Frontend import URL' );
            return;
        }
        $bot_id = wpsstm()->get_options('bot_user_id');


        $duplicate_args = array(
            'post_type'         => wpsstm()->post_type_radio,
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
        Check for radio duplicates, by bot user ID
        */

        $bot_duplicate_args = $duplicate_args;
        $bot_duplicate_args['post_author'] = $bot_id;

        $duplicate_query = new WP_Query( $bot_duplicate_args );
        if ( $duplicate_query->have_posts() ){
            $existing_id = $duplicate_query->posts[0];
            $link = get_permalink($existing_id);
            wp_safe_redirect($link);
            exit;
        }

        /*
        Create a new temporary radio and redirect to it
        */

        //store as wizard tracklist (author = bot user / ->is_wizard_tracklist_metakey = true)

        $post_args = array(
            'post_type' =>      wpsstm()->post_type_radio,
            'post_status' =>    'publish',
            'post_author' =>    $bot_id,
            'meta_input' =>     array(
                WPSSTM_Post_Tracklist::$feed_url_meta_name => $url,
                self::$is_wizard_tracklist_metakey  => true,
            )
        );

        $success = wp_insert_post( $post_args, true );

        if ( is_wp_error($success) ){
            $link = get_permalink(wpsstm()->get_options('importer_page_id'));
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

    static function advanced_selectors_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="wpsstm-importer-selector-toggle-advanced"><i class="fa fa-cog" aria-hidden="true"></i></a>',
            __('Advanced selectors','wpsstm')
        );
    }

    //when a node has a $ref property, merge it with its definition
    static function instantiate_schema_references($node,$parent = null,$schema = null){

      if (!$parent) $schema = $node;

      $refpath = isset($node['$ref']) ? $node['$ref'] : null;

      //if this node has a $ref, get it then merge it.
      if ($refpath){
        $refpath = ltrim($refpath,"#/");//remove root prefix

        $refarr = explode('/',$refpath);
        if ( $refnode = wpsstm_get_array_value($refarr,$schema) ){
          $node = array_replace_recursive($refnode,$node);//merge
        }

      }

      if ( isset($node['properties']) ){
        foreach($node['properties'] as $key=>&$property){
          $property = self::instantiate_schema_references($property,$node,$schema);
          //$property['$id'] = $node['$id'] . '/' . $key;
        }
      }

      return $node;

    }

    private static function get_schema_node_id($nodekeys){
      //remove the 'properties' values out of the nodekeys
      $nodekeys = array_values(array_diff( $nodekeys, array('properties') ));
      return implode('_',$nodekeys);
    }

    private static function get_schema_node_input_name($nodekeys){
      //remove the 'properties' values out of the nodekeys
      $nodekeys = array_values(array_diff( $nodekeys, array('properties') ));

      $chain = array_map(
         function ($el) {
            return sprintf('[%s]',$el);
         },
         $nodekeys
      );
      return 'wpsstm_importer' . implode('',$chain);
    }

    private static function get_schema_node_classes($node,$nodekeys=array(),$tree=array()){
      $classes = array(
        'wpsstm-wizard-node',
        'wpsstm-wizard-node-active',
        sprintf('wpsstm-wizard-node-%s',$node['type'])
      );

      //ref class
      if( isset($node['$ref']) ){
        //$refpath = ltrim($refpath,"#/");//remove root prefix
        $classes[] = sprintf('wpsstm-wizard-node-%s',sanitize_title($node['$ref']));
      }

      if ( self::is_schema_node_required($tree,$nodekeys) ){
        $classes[] = 'wpsstm-wizard-node-required';
      }

      if ( self::is_schema_node_readonly($tree,$nodekeys) ){
        $classes[] = 'wpsstm-wizard-node-readonly';
      }

      if ( isset($node['properties']) && count($node['properties']) ){
        $classes[] = 'wpsstm-wizard-parent-node';
      }

      return $classes;
    }

    private static function is_schema_node_required($tree,$nodekeys=array()){
      $requiredPath = $nodekeys;
      $lastKey = array_pop($requiredPath);
      array_pop($requiredPath);
      $requiredPath = array_merge($requiredPath,array('required'));
      $required = in_array($lastKey,(array)wpsstm_get_array_value($requiredPath,$tree));
      //printf("REQUIRED: %s - %s in %s<br/>",$required,$lastKey,json_encode($requiredPath));µ
      return $required;
    }

    private static function is_schema_node_readonly($tree,$nodekeys=array()){

      $node = wpsstm_get_array_value($nodekeys,$tree);

      if ( wpsstm_get_array_value('readOnly',$node) ){ //this node
        return true;
      }

      if ( empty($nodekeys) ) return;

      //check ancestors
      array_pop($nodekeys);


      return self::is_schema_node_readonly($tree,$nodekeys);
    }

    private static function get_schema_node_db_value($nodekeys){
      global $wpsstm_tracklist;
      //remove the 'properties' values out of the nodekeys
      $db_nodekeys = array_values(array_diff( $nodekeys, array('properties') ));
      return $wpsstm_tracklist->get_importer_options($db_nodekeys);
    }

    static function parse_schema_node($tree,$nodekeys=array()){
      global $wpsstm_tracklist;

      $is_root = empty($nodekeys);
      $node = wpsstm_get_array_value($nodekeys,$tree);

      if ( self::is_schema_node_readonly($tree,$nodekeys) ) return;

      $el_id  = self::get_schema_node_id($nodekeys);
      $el_name = self::get_schema_node_input_name($nodekeys);

      $title = wpsstm_get_array_value('title',$node);
      $default = wpsstm_get_array_value('default',$node);
      $readonly = self::is_schema_node_readonly($tree,$nodekeys);
      $required = self::is_schema_node_required($tree,$nodekeys);
      $examples = wpsstm_get_array_value('examples',$node);
      $properties = isset($node['properties']) ? $node['properties'] : array();
      $db_value = self::get_schema_node_db_value($nodekeys);
      $value = ( $readonly || !$db_value ) ? $default : $db_value;

      $container_attributes = array(
        'id'=>      sprintf('%s_container',$el_id),
        'class'=>   implode(' ',self::get_schema_node_classes($node,$nodekeys,$tree))
      );

      $attributes = array(
        'name' =>         $el_name,
        'id' =>           $el_id,
        'disabled'=>      (bool)$readonly,
        'required'=>      (bool)$required,
      );

      ob_start();

      ?>
      <div <?php echo wpsstm_get_html_attr($container_attributes);?>>
        <?php

        //title
        if ( !$is_root && $title ){
          $title = $required ? $title.=' *' : $title;
        }

        switch ($node['type']){
          case 'object':

            if ($title){
              $title = count($properties) ? $title.='<a class="wpsstm-wizard-node-handle wpsstm-wizard-node-handle-close" href="#"><i class="fa fa-angle-up" aria-hidden="true"></i></a><a class="wpsstm-wizard-node-handle wpsstm-wizard-node-handle-open" href="#"><i class="fa fa-angle-down" aria-hidden="true"></i></a>' : $title;
              printf('<p><strong>%s</strong></p>',$title);
            }

            foreach($properties as $key=>$property){
              $new_nodekeys = array_merge($nodekeys,array('properties',$key));
              echo self::parse_schema_node($tree,$new_nodekeys);
            }

          break;

          case 'string':

            $attributes = array_merge(
              $attributes,
              array(
                'placeholder' =>  $examples ? sprintf(__('eg. %s, ...','wpsstm'),implode(',',$examples)) : null,
                'type' =>         'text'
              )
            );

            //Since wpsstm_get_html_attr is converting our HTML entities, keep value OUT of it (we want the raw value eg for regexes!)
            $value = htmlentities($value);

            //regex exception
            if (end($nodekeys) === 'regex'){
              $input = sprintf('<span><code>~</code><input %s value="%s"/><code>~mi</code></span>',wpsstm_get_html_attr($attributes),$value);
            }else{
              $input = sprintf('<span><input %s value="%s"/></span>',wpsstm_get_html_attr($attributes),$value);
            }

            $label = sprintf('<label for="%s">%s</label>',$el_id,$title);

            printf('<div class="wpsstm-wizard-node-content">%s</div>',$input.$label);

          break;

          case 'boolean':

            $attributes = array_merge(
              $attributes,
              array(
                'type' =>         'checkbox',
                'value'=>         'on',
                'checked'=>       (bool)$value,
              )
            );



            $input = sprintf('<span><input %s /></span>',wpsstm_get_html_attr($attributes));
            $label = sprintf('<label for="%s">%s</label>',$el_id,$title);

            printf('<div class="wpsstm-wizard-node-content">%s</div>',$input.$label);

          break;

          default:
            print_r(json_encode($node));echo"<br/>";echo"<br/>";
          break;
        }
      ?>
      </div>
      <?php

      return ob_get_clean();

    }

    static function sanitize_importer_settings($input){
      //TOUFIX TOUCHECK be sure that we sanitize correctly.
      //https://wordpress.stackexchange.com/questions/360429/how-to-santize-store-and-restore-a-regex-pattern-string

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

    static function get_importers(){

      $importers = get_transient( self::$importers_transient_name );

      if (false === $importers){
        $importers = WPSSTM_Core_API::api_request('v2/importers');
        if ( is_wp_error($importers) || !$importers ) return $importers;
        set_transient( self::$importers_transient_name, $importers, 1 * DAY_IN_SECONDS );
      }

      return $importers;
    }

    static function get_importers_by_domain(){
        $importers = self::get_importers();
        if ( is_wp_error($importers) ) return $importers;

        /*
        sort importers by domain
        */

        $domains = array();

        foreach((array)$importers as $importer){

          $name = wpsstm_get_array_value(array('infos','name'),$importer);
          $url = wpsstm_get_array_value(array('infos','service_url'),$importer);
          $image = wpsstm_get_array_value(array('infos','image'),$importer);
          $domain = wpsstm_get_url_domain($url);
          $key = sanitize_title($domain);

          //first one of this domain
          if ( !isset($domains[$key]) ){
              $domains[$key]['image'] = $image;
              $domains[$key]['name'] = $name;
              $domains[$key]['service_url'] = $url;
          }else{
              $domains[$key]['name'] .= ', ' . $name;
          }

          //set item
          $domains[$key]['importers'] = $importer;

        }

        return $domains;
    }

}

function wpsstm_wizard_init(){
    new WPSSTM_Core_Importer();
}

add_action('plugins_loaded','wpsstm_wizard_init');
