<?php

/**
 * Based on class WP_List_Table
 */

class WP_SoundSystem_Tracklist_Table{
    var $tracklist;
    var $curr_track_idx = null;
    
    var $no_items_label = null;
    
    //options
    var $can_play;
    var $sources_db_only;
    var $display_type;

    function __construct($tracklist,$args = null){
        global $page;
        
        $defaults = array(
            'can_play' =>           true,
            'sources_db_only' =>    true,
            'display_type' =>       'table',
        );
        $args = wp_parse_args((array)$args,$defaults);

        foreach($defaults as $slug=>$default_value){
            $this->$slug = $args[$slug];
        }

        //can play
        if ( wpsstm()->get_options('player_enabled') !== 'on' ){
            $this->can_play = false;
        }

        $this->tracklist = $tracklist;
        
        $this->no_items_label = __( 'No tracks found.','wpsstm');

    }
    
    function prepare_items() {

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $this->tracklist->tracks;
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to 
         */
        
        $current_page = $this->tracklist->pagination['current_page'];
        $per_page = $this->tracklist->pagination['per_page'];
        
        if ( $per_page > 0 ){
            $current_page = $this->tracklist->pagination['current_page'];
            $this->items = array_slice((array)$this->items,(($current_page-1)*$per_page),$per_page);
        }

        $this->curr_track_idx = $per_page * ( $current_page - 1 );

    }
    
    function get_columns(){
        
        $columns = array(
            'trackitem_image'   =>      '',
            'trackitem_position'   =>      '',
            'trackitem_play_bt' =>      '',
            'trackitem_artist' =>       __('Artist','wpsstm'),
            'trackitem_track' =>        __('Title','wpsstm'),
            'trackitem_album' =>        __('Album','wpsstm'),
            'trackitem_actions' =>      '',
            'trackitem_sources' =>      __('Sources','wpsstm')
        );
        
        //remove properties when it is null for every track of the playlist
        if ( !$this->show_property_column('image') ) unset($columns['trackitem_image']);
        if ( !$this->show_property_column('album') ) unset($columns['trackitem_album']);
        if ($this->display_type != 'table') unset($columns['trackitem_actions']);

        if ( !$this->can_play ){
            unset($columns['trackitem_play_bt']);
        }

        return $columns;
    }
    
    /**
     * @param type $key
     * @return boolean
     */
    
    function show_property_column($key){
        
        //Check that at least one of the items has a certain property ($key) value set.
        $at_least_one_value = false;
        foreach((array)$this->items as $item){
            if( property_exists($item,$key ) && $item->$key ) {
                $at_least_one_value = true;
                break;
            }
        }
        
        if (!$at_least_one_value) return false;
        
        //count the number of different values for this property.  If they are all the same, do not show the column.
        foreach((array)$this->items as $item){
            if( property_exists($item,$key ) && $item->$key ) {
                $all_values[] = $item->$key;
            }
        }
        
        $all_values_count = count(array_unique($all_values));
 
        if ($all_values_count == 1) return false;
        
        return true;
    }    

	/**
	 * Display the table
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display() {
        
        $classes = array(
            'wpsstm-tracklist'
        );
        
        if ($this->can_play){
            do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
            $classes[] = 'wpsstm-playable-tracklist';
        }
        
        $classes[] = sprintf('wpsstm-tracklist-%s',$this->display_type);
        
        $attr_arr = array(
            'class'           =>            implode(' ',$classes),
            'data-wpsstm-tracklist-id' =>   $this->tracklist->post_id,
            'data-wpsstm-tracklist-type' => $this->tracklist->tracklist_type,
            'data-wpsstm-autosource' =>     (int)( $this->tracklist->get_options('autosource') && wpsstm_sources()->can_autosource() ),
            'data-wpsstm-autoplay' =>       (int)$this->tracklist->get_options('autoplay'),
            'data-tracks-count' =>          $this->tracklist->pagination['total_items'],
            'itemtype' =>                   'http://schema.org/MusicPlaylist',
        );
        
        if ( property_exists($this->tracklist,'feed_url') && ($this->tracklist->feed_url) ) $classes[] = 'wpsstm-tracklist-live';

        $next_refresh_sec = null;

        if ( property_exists($this->tracklist,'expiration_time') ) {

            $next_refresh_sec = $this->tracklist->expiration_time - current_time( 'timestamp', true ); //UTC
            
            if ($next_refresh_sec <= 0){
                $next_refresh_sec = 0;
                $this->no_items_label = __("The tracklist cache has expired.","wpsstm"); 
            }
            
            $attr_arr['data-wpsstm-expire-sec'] = $next_refresh_sec;
        }
        
        $entries_html = $this->get_rows_or_placeholder();

        
        $list = sprintf('<ul class="wpsstm-tracklist-entries">%s</ul>',$entries_html);
        
        
        if ($this->display_type == 'table'){
            $nav_top = $this->get_tablenav( 'top' );
            $nav_bottom = null; //$this->get_tablenav( 'bottom' );
            $list = $nav_top . $list . $nav_bottom;
        }
        
        
        $output = sprintf('<div itemscope %s>%s</div>',wpsstm_get_html_attr($attr_arr),$list);
        echo $output;

	}
    
    protected function get_extra_tablenav($which){
        global $post;

        /*
        if ( $post && ($post->ID == $this->tracklist->post_id) ) { //don't show title if post = tracklist
            printf('<meta itemprop="name" content="%s" />',$this->tracklist->title);
        }else{
        */
            $loading_icon = '<i class="wpsstm-tracklist-loading-icon fa fa-circle-o-notch fa-spin fa-fw"></i>';
            $tracklist_title = sprintf('<a href="%s">%s</a>',get_permalink($this->tracklist->post_id),$this->tracklist->title);

            $title_el = sprintf('<strong class="wpsstm-tracklist-title" itemprop="name">%s%s</strong>',$loading_icon,$tracklist_title);
        //}

        $numtracks_el = sprintf('<meta itemprop="numTracks" content="%s" />',$this->tracklist->pagination['total_items']);

        $updated_time_el = $refresh_time_el = $refresh_countdown_el = $refresh_link_el = null;

        //time subtracks were updated



        $icon_time = '<i class="fa fa-clock-o" aria-hidden="true"></i>';
        $text_time = wpsstm_tracklists()->get_human_tracklist_time($this->tracklist->updated_time);
        $updated_time_el = sprintf('<time class="wpsstm-tracklist-published">%s %s</time>',$icon_time,$text_time);
        $refresh_time_el = wpsstm_get_tracklist_refresh_frequency_human($this->tracklist->post_id);

        $time_el = sprintf(' <small class="wpsstm-tracklist-time">%s %s %s</small>',$updated_time_el,$refresh_time_el,$refresh_link_el);

        //notices
        $notices_el = $this->tracklist->get_notices('tracklist-header');

        $actions_el = null;
        if ( $actions = $this->tracklist->get_tracklist_actions('page') ){
            $actions_el = wpsstm_get_actions_list($actions,'tracklist');
        }

        return sprintf('<div>%s%s%s%s%s</div>',$title_el,$numtracks_el,$time_el,$notices_el,$actions_el);
        
    }
        
    protected function get_tablenav( $which ) {
        
        $post_type = get_post_type($this->tracklist->post_id);
        
        $classes = array(
            'tracklist-nav',
            'tracklist-' . $post_type,
            esc_attr( $which )
        );
        
        return sprintf('<div %s>%s%s<br class="clear" /></div>',wpsstm_get_classes_attr($classes),$this->get_extra_tablenav( $which ),$this->get_pagination( $which ));

    }
    
    /**
     * Display the pagination.
     *
     * @since 3.1.0
     * @access protected
     *
     * @param string $which
     */
    protected function get_pagination( $which ) {
        
        if ( !$this->tracklist->pagination['per_page'] ) return;

        $big = 999999999; // need an unlikely integer

        $pagination_args = array(
            'base' => str_replace( $big, '%#%', esc_url( wpsstm_get_tracklist_link( $this->tracklist->post_id, $big ) ) ),
            'format' => sprintf('?%s=%#%',WP_SoundSystem_Tracklist::$paged_var),
            'current' => max( 1,$this->tracklist->pagination['current_page']  ),
            'total' => $this->tracklist->pagination['total_pages']
        );

        return paginate_links( $pagination_args );
    }
    
    
    
    /**
     * Generate the tbody element for the list table.
     *
     * @since 3.1.0
     * @access public
     */
    public function get_rows_or_placeholder() {
        
            if ( !$this->has_items() ) {
                return sprintf('<li class="no-items">%s</li>',$this->no_items_label);
            }
                
        
            $rows = array();
            foreach ( $this->items as $item ){
                $rows[] = $this->get_single_row( $item );
            }
            return implode("\n",$rows);

    }
    
    /**
     * Whether the table has items to display or not
     *
     * @since 3.1.0
     * @access public
     *
     * @return bool
     */
    public function has_items() {
            return !empty( $this->items );
    }

    /**
     * Generates content for a single row of the table
     *
     * @since 3.1.0
     * @access public
     *
     * @param object $item The current item
     */
    public function get_single_row( $item ) {

        $classes = array();
        if ( !$item->validate_track() ) $classes[] = 'wpsstm-invalid-track';

        $attr_arr = array(
            'class' =>                      implode(' ',$classes),
            'data-wpsstm-track-id' =>       $item->post_id,
            'data-wpsstm-sources-count' =>  count($item->sources),
            'itemtype' =>                   'http://schema.org/MusicRecording',
            'itemprop' =>                   'track',
        );

        return sprintf( '<li itemscope %s>%s</li>',wpsstm_get_html_attr($attr_arr),$this->get_single_row_columns( $item ) );

    }
    
    /**
     * Generates the columns for a single row of the table
     *
     * @since 3.1.0
     * @access protected
     *
     * @param object $item The current item
     */
    protected function get_single_row_columns( $item ) {
        $columns_html = array();
        
        //we'll wrap the track text separately a div (for CSS)
        $track_text_html = array();
        $track_text_slugs = array('trackitem_artist','trackitem_track','trackitem_album');

        
        //wrap 
        foreach ( $this->get_columns() as $column_name => $column_display_name ) {
            
            $content = $this->get_column_content( $item, $column_name );

            $classes = array(
                'wpsstm-track-column',
                $column_name,
                'column-'.$column_name
            );
            
            $attr = array(
                'class' =>  implode(' ',$classes)
            );

            switch($column_name){
                case 'trackitem_artist':
                    $attr['itemprop'] = 'byArtist';
                break;
                case 'trackitem_track':
                    $attr['itemprop'] = 'name';
                break;
                case 'trackitem_album':
                    $attr['itemprop'] = 'inAlbum';
                break;
                case 'trackitem_image':
                    $attr['itemprop'] = 'image';
                break;
            }

            $columns_html[$column_name] = sprintf('<span %s>%s</span>',wpsstm_get_html_attr($attr),$content);

        }

        return implode("\n",$columns_html);
    }

    function get_column_content($item, $column_name){
        switch($column_name){
            case 'trackitem_position':
                $this->curr_track_idx++;
                
                $loading_icon = '<i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i>';
                
                /*
                Capability check
                */
                $tracklist_id =             $this->tracklist->post_id;
                $post_type_playlist =       get_post_type($tracklist_id);
                $tracklist_obj =            $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
                $can_edit_tracklist =       ( $tracklist_obj && current_user_can($tracklist_obj->cap->edit_post,$tracklist_id) );
                $can_move_track =           ( $can_edit_tracklist && ($this->tracklist->tracklist_type == 'static') );
                
                $position = sprintf('<span itemprop="position">%s</span>',$this->curr_track_idx);
                
                if ($can_move_track){
                    $position = sprintf('<span class="wpsstm-reposition-track"><i class="fa fa-arrows-v" aria-hidden="true"></i>%s</span>',$position);
                }
                
                return $loading_icon.$position;
                
            case 'trackitem_play_bt':
                return wpsstm_player()->get_track_button($item);
            case 'trackitem_track':
                return $item->title;
            break;
            case 'trackitem_artist':
                return $item->artist;
            break;
            case 'trackitem_album':
                return $item->album;
            break;
            case 'trackitem_image':
                if ( $item->image ){
                    return sprintf('<img src="%s" itemprop="image"/>',$item->image);
                }
            break;
            case 'trackitem_sources':
                return wpsstm_sources()->get_track_sources_list($item); //db sources only. we'll fetch new sources using ajax.
            break;
            case 'trackitem_actions':
                if ( $actions = $item->get_track_actions($this->tracklist,'page') ){
                    $actions_list = wpsstm_get_actions_list($actions,'track');
                    return $actions_list;
                }
            break;
        }
    }

}
?>
