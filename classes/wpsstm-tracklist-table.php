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
        
        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);

    }
    
    function get_columns(){
        
        $columns = array(
            'trackitem_image'   =>      '',
            'trackitem_order'   =>      '',
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
    
    function get_sortable_columns() {
        $sortable_columns = array(
            //'title'     => array('title',false),     //true means it's already sorted
        );
        return $sortable_columns;
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
            'data-tracks-count' =>          $this->tracklist->pagination['total_items'],
            'itemtype' =>                   'http://schema.org/MusicPlaylist',
        );
        
        if ( property_exists($this->tracklist,'feed_url') && ($this->tracklist->feed_url) ) $classes[] = 'wpsstm-tracklist-live';

        $next_refresh_sec = null;

        if ( property_exists($this->tracklist,'expire_time') ) {

            $next_refresh_sec = $this->tracklist->expire_time - current_time( 'timestamp', true ); //UTC
            
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

        //static playlist time
        if ( $this->tracklist->updated_time ){

            $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->updated_time ), get_option( 'date_format' ) );
            $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->updated_time ), get_option( 'time_format' ) );

            $icon_time = '<i class="fa fa-clock-o" aria-hidden="true"></i>';
            $text_time = sprintf(__('on %s - %s','wpsstm'),$date,$time);
            $updated_time_el = sprintf('<time class="wpsstm-tracklist-published">%s %s</time>',$icon_time,$text_time);
            $refresh_time_el = wpsstm_get_tracklist_refresh_frequency_human($this->tracklist->post_id);

        }

        $time_el = sprintf(' <small class="wpsstm-tracklist-time">%s %s %s</small>',$updated_time_el,$refresh_time_el,$refresh_link_el);

        //notices
        $notices_el = $this->tracklist->get_notices('tracklist-header');

        $actions_el = null;
        if ( $actions = $this->tracklist->get_tracklist_row_actions() ){
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
     * Print column headers, accounting for hidden and sortable columns.
     *
     * @since 3.1.0
     * @access public
     *
     * @param bool $with_id Whether to set the id attribute or not
     */
    public function get_header( $with_id = true ) {
            list( $columns, $hidden, $sortable ) = $this->get_column_info();

            $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
            $current_url = remove_query_arg( WP_SoundSystem_Tracklist::$paged_var, $current_url );

            if ( isset( $_GET['orderby'] ) )
                    $current_orderby = $_GET['orderby'];
            else
                    $current_orderby = '';

            if ( isset( $_GET['order'] ) && 'desc' == $_GET['order'] )
                    $current_order = 'desc';
            else
                    $current_order = 'asc';

            foreach ( $columns as $column_key => $column_display_name ) {
                    $class = array( 'manage-column', "column-$column_key" );

                    $style = '';
                    if ( in_array( $column_key, $hidden ) )
                            $style = 'display:none;';

                    $style = ' style="' . $style . '"';

                    if ( isset( $sortable[$column_key] ) ) {
                            list( $orderby, $desc_first ) = $sortable[$column_key];

                            if ( $current_orderby == $orderby ) {
                                    $order = 'asc' == $current_order ? 'desc' : 'asc';
                                    $class[] = 'sorted';
                                    $class[] = $current_order;
                            } else {
                                    $order = $desc_first ? 'desc' : 'asc';
                                    $class[] = 'sortable';
                                    $class[] = $desc_first ? 'asc' : 'desc';
                            }

                            $column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
                    }

                    $id = $with_id ? "id='$column_key'" : '';

                    if ( !empty( $class ) )
                            $class = "class='" . join( ' ', $class ) . "'";
                
                    return sprintf("<th scope='col' %s %s %s>%s</th>",$id,$class,$style,$column_display_name);
            }
    }
    
    /**
     * Get a list of all, hidden and sortable columns, with filter applied
     *
     * @since 3.1.0
     * @access protected
     *
     * @return array
     */
    protected function get_column_info() {
            if ( isset( $this->_column_headers ) )
                    return $this->_column_headers;

            $columns = get_column_headers( $this->screen );
            $hidden = get_hidden_columns( $this->screen );

            $sortable_columns = $this->get_sortable_columns();
            /**
             * Filter the list table sortable columns for a specific screen.
             *
             * The dynamic portion of the hook name, `$this->screen->id`, refers
             * to the ID of the current screen, usually a string.
             *
             * @since 3.5.0
             *
             * @param array $sortable_columns An array of sortable columns.
             */
            $_sortable = apply_filters('spiff_manage_tracklist_sortable_columns', $sortable_columns );

            $sortable = array();
            foreach ( $_sortable as $id => $data ) {
                    if ( empty( $data ) )
                            continue;

                    $data = (array) $data;
                    if ( !isset( $data[1] ) )
                            $data[1] = false;

                    $sortable[$id] = $data;
            }

            $this->_column_headers = array( $columns, $hidden, $sortable );

            return $this->_column_headers;
    }
    
    /**
     * Generate the tbody element for the list table.
     *
     * @since 3.1.0
     * @access public
     */
    public function get_rows_or_placeholder() {
            if ( $this->has_items() ) {
                return $this->get_display_rows();
            } else {
                return sprintf('<tr class="no-items"><td class="colspanchange" colspan="%s">%s</td></tr>',$this->get_column_count(),$this->no_items());
            }
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
     * Generate the table rows
     *
     * @since 3.1.0
     * @access public
     */
    public function get_display_rows() {
        $rows = array();
        $rows_str = null;
        foreach ( $this->items as $item ){
            $rows[] = $this->get_single_row( $item );
        }
        return implode("\n",$rows);
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
            'data-wpsstm-sources-count' =>  count($item->source_ids),
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
        list( $columns, $hidden ) = $this->get_column_info();

        $columns_html = array();

        foreach ( $columns as $column_name => $column_display_name ) {

            $attr_str = null;
            $attr = array();

            $classes = array(
                'wpsstm-track-column',
                $column_name,
                'column-'.$column_name
            );

            $attr['class'] = implode(' ',$classes);

            $attr['style'] = ( in_array( $column_name, $hidden ) ) ? 'display:none;' : null;

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

            $attr_str = wpsstm_get_html_attr($attr);

            if ( method_exists( $this, 'column_' . $column_name ) ) {
                $content = call_user_func( array( $this, 'column_' . $column_name ), $item );
            }else {
                $content = $this->column_default( $item, $column_name );
            }

            $columns_html[] = sprintf('<span %s>%s</span>',$attr_str,$content);

            
        }
        return implode("\n",$columns_html);
    }
    
    function column_default($item, $column_name){
        switch($column_name){
            case 'trackitem_order':
                $this->curr_track_idx++;
                
                $loading_icon = '<i class="wpsstm-player-icon wpsstm-player-icon-buffering fa fa-circle-o-notch fa-spin fa-fw"></i>';
                $text = sprintf('<span itemprop="position">%s</span>',$this->curr_track_idx);
                return $loading_icon.$text;
                
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
                if ( $actions = $item->get_track_row_actions($this->tracklist) ){
                    $actions_list = wpsstm_get_actions_list($actions,'track');
                    return $actions_list;
                }
            default:
                if ( !is_admin() ) break;
                return print_r($item,true); //Show the whole array for troubleshooting purposes
        }
    }
    
    public function get_column_count() {
            list ( $columns, $hidden ) = $this->get_column_info();
            $hidden = array_intersect( array_keys( $columns ), array_filter( $hidden ) );
            return count( $columns ) - count( $hidden );
    }
    
    /**
     * Message to be displayed when there are no items
     *
     * @since 3.1.0
     * @access public
     */
    public function no_items() {
        echo $this->no_items_label;
    }
    
    
    
}
?>
