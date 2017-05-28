<?php


/**
 * Based on class WP_List_Table
 */

class WP_SoundSytem_TracksList_Table{
    var $tracklist;
    var $curr_track_idx = null;
    
    var $no_items_label = null;
    var $can_player = true;
    var $sources_db_only = true;

    function __construct($tracklist){
        global $page;
        
        $this->tracklist = $tracklist;
        
        $this->no_items_label = __( 'No tracks found.','wpsstm');

        $this->can_player = ( !wpsstm_is_backend() && wpsstm()->get_options('player_enabled') == 'on' );
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
        
        if ( $this->show_property_column('image') ){
            $columns['trackitem_image']     = '';
        }
        
        $columns['trackitem_order']     = '';

        //play button
        if ( $this->can_player ){
            foreach ($this->items as $item){
                $columns['trackitem_play_bt']     = '';
                break;
            }
        }
        
        $columns['trackitem_artist']    = __('Artist','wpsstm');
        $columns['trackitem_track']     = __('Title','wpsstm');

        
        if ( $this->show_property_column('album') ){
            $columns['trackitem_album']     = __('Album','wpsstm');
        }

        if ( current_user_can('administrator') && !wpsstm_is_backend() ){ //TO FIX remove this condition when feature ready
             $columns['trackitem_actions']     = '';
        }
        
        $columns['trackitem_sources']     = __('Sources','wpsstm');

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
            'wpsstm-tracklist',
            'wpsstm-tracklist-table'
        );
        
        if ($this->tracklist->feed_url) $classes[] = 'wpsstm-tracklist-live';
        
        if ( property_exists($this->tracklist,'expire_time') && ($expire_timestamp = $this->tracklist->expire_time ) ) {
            $remaining = $this->tracklist->expire_time - current_time( 'timestamp', true );
            if ($remaining <= 0) $classes[] = 'can-refresh';
        }

        $classes_str = wpsstm_get_classes_attr($classes);

        printf('<div %s itemscope itemtype="http://schema.org/MusicPlaylist" data-tracklist-id="%s" data-tracks-count="%s" data-wpsstm-next-refresh="%s">',$classes_str,$this->tracklist->post_id,$this->tracklist->pagination['total_items'],$this->tracklist->expire_time); ?>
            <?php $this->display_tablenav( 'top' );?>
            <table>
                    <thead>
                    <tr>
                            <?php $this->print_column_headers(); ?>
                    </tr>
                    </thead>

                    <tbody>
                            <?php $this->display_rows_or_placeholder(); ?>
                    </tbody>

                    <tfoot>
                    <tr>
                            <?php //$this->print_column_headers( false ); ?>
                    </tr>
                    </tfoot>

            </table>
            <?php //$this->display_tablenav( 'bottom' );?>
        </div>

        <?php

	}
    
    protected function extra_tablenav($which){
        global $post;
        ?>
        <div>
            <?php
            /*
            if ( $post && ($post->ID == $this->tracklist->post_id) ) { //don't show title if post = tracklist
                printf('<meta itemprop="name" content="%s" />',$this->tracklist->title);
            }else{
            */
                $tracklist_link = sprintf('<a href="%s">%s</a>',get_permalink($this->tracklist->post_id),$this->tracklist->title);
                printf('<strong class="wpsstm-tracklist-title" itemprop="name">%s</strong>',$tracklist_link);
            //}
            
            printf('<meta itemprop="numTracks" content="%s" />',$this->tracklist->pagination['total_items']);
        
            $updated_time_el = $refresh_time_el = $refresh_countdown_el = $refresh_link_el = null;

            //static playlist time
            if ( $this->tracklist->updated_time ){

                $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->updated_time ), get_option( 'date_format' ) );
                $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->tracklist->updated_time ), get_option( 'time_format' ) );

                $icon_time = '<i class="fa fa-clock-o" aria-hidden="true"></i>';
                $text_time = sprintf(__('on  %s - %s','wpsstm'),$date,$time);
                $updated_time_el = sprintf('<time class="wpsstm-tracklist-published">%s %s</time>',$icon_time,$text_time);
                
                //live playlist refresh time
                if ( property_exists($this->tracklist,'expire_time') && ($expire_timestamp = $this->tracklist->expire_time ) ) {

                    $remaining = $expire_timestamp - current_time( 'timestamp', true );

                    if ($remaining <= 0){
                        $this->no_items_label = __("The tracklist cache has expired.","wpsstm"); 
                    }

                    //expire time
                    //$refresh_time = get_date_from_gmt( date( 'Y-m-d H:i:s', $expire_timestamp ), get_option( 'time_format' ) );
                    $refresh_time_human = human_time_diff( $this->tracklist->updated_time, $this->tracklist->expire_time );
                    $refresh_time_human = sprintf('every %s',$refresh_time_human);
                    $refresh_time_el = sprintf('<time class="wpsstm-tracklist-refresh-time"><i class="fa fa-rss" aria-hidden="true"></i></i> %s</time>',$refresh_time_human);

                }
                
            }
        
            //refresh link
            if ( property_exists($this->tracklist,'expire_time') && ($expire_timestamp = $this->tracklist->expire_time ) ) {
                $refresh_link_el = $this->tracklist->get_refresh_link();
            }

            printf('<small class="wpsstm-tracklist-time">%s %s %s</small>',$updated_time_el,$refresh_time_el,$refresh_link_el);

            if ( !wpsstm_is_backend() ){
                ?>
                <div class="alignright actions wpsstm-tracklist-actions">
                    <?php
                        $tracklist_links = array();
                
                        //share
                        $share_url = wpsstm_get_tracklist_link($this->tracklist->post_id);
                        $share_icon = '<i class="fa fa-share-alt" aria-hidden="true"></i>';
                        $share_text = __('Share', 'wpsstm');
                        $tracklist_links[] = sprintf('<a title="%2$s" href="%3$s" target="_blank" class="wpsstm-tracklist-action-share">%1$s<span> %2$s</span></a>',$share_icon,$share_text,$share_url);

                        //xspf
                        $xspf_url = wpsstm_get_tracklist_link($this->tracklist->post_id,'xspf');
                        $xspf_icon = '<i class="fa fa-rss" aria-hidden="true"></i>';
                        $xspf_text = __('XSPF', 'wpsstm');
                        $tracklist_links[] = sprintf('<a title="%2$s" href="%3$s" target="_blank" class="wpsstm-tracklist-action-xspf">%1$s<span> %2$s</span></a>',$xspf_icon,$xspf_text,$xspf_url);

                        //favorite
                        if ( $this->tracklist->post_id && current_user_can('administrator') ) { //TO FIX remove current_user_can when feature is ready
                            $tracklist_links[] = wpsstm_get_tracklist_loveunlove_icons($this->tracklist->post_id);
                        }
                        
                        $tracklist_links = apply_filters('wpsstm_tracklist_links',$tracklist_links);

                        echo implode("\n",$tracklist_links);
                    ?>
                </div>
            <?php 
            }
            ?>
        </div>

    <?php
    }
        
    protected function display_tablenav( $which ) {
        
        $post_type = get_post_type($this->tracklist->post_id);
        
        $classes = array(
            'tracklist-nav',
            'tracklist-' . $post_type,
            esc_attr( $which )
        );

        ?>
        <div <?php echo wpsstm_get_classes_attr($classes);?>>

        <?php
                $this->extra_tablenav( $which );
                $this->pagination( $which );
        ?>

                <br class="clear" />
        </div>
        <?php
    }
    
    /**
     * Display the pagination.
     *
     * @since 3.1.0
     * @access protected
     *
     * @param string $which
     */
    protected function pagination( $which ) {
        
        if ( !$this->tracklist->pagination['per_page'] ) return;

        $big = 999999999; // need an unlikely integer

        $pagination_args = array(
            'base' => str_replace( $big, '%#%', esc_url( wpsstm_get_tracklist_link( $this->tracklist->post_id, $big ) ) ),
            'format' => sprintf('?%s=%#%',WP_SoundSytem_Tracklist::$paged_var),
            'current' => max( 1,$this->tracklist->pagination['current_page']  ),
            'total' => $this->tracklist->pagination['total_pages']
        );

        echo paginate_links( $pagination_args );
    }
    
    /**
     * Print column headers, accounting for hidden and sortable columns.
     *
     * @since 3.1.0
     * @access public
     *
     * @param bool $with_id Whether to set the id attribute or not
     */
    public function print_column_headers( $with_id = true ) {
            list( $columns, $hidden, $sortable ) = $this->get_column_info();

            $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
            $current_url = remove_query_arg( WP_SoundSytem_Tracklist::$paged_var, $current_url );

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

                    echo "<th scope='col' $id $class $style>$column_display_name</th>";
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
    public function display_rows_or_placeholder() {
            if ( $this->has_items() ) {
                    $this->display_rows();
            } else {
                    echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
                    $this->no_items();
                    echo '</td></tr>';
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
    public function display_rows() {
            foreach ( $this->items as $item )
                    $this->single_row( $item );
    }
    
    /**
     * Generates content for a single row of the table
     *
     * @since 3.1.0
     * @access public
     *
     * @param object $item The current item
     */
    public function single_row( $item ) {
        
            $sources = wpsstm_player()->get_playable_sources($item,true);

            $track_classes = array();
            if ( !$item->validate_track() ) $track_classes[] = 'wpsstm-invalid-track';
        
            printf( '<tr itemprop="track" itemscope itemtype="http://schema.org/MusicRecording" %s data-wpsstm-track-id="%s" data-wpsstm-sources-count="%s">',
                   wpsstm_get_classes_attr($track_classes),
                   $item->post_id,
                   count($sources)
            );
            $this->single_row_columns( $item );
            echo '</tr>';
    }
    
    /**
     * Generates the columns for a single row of the table
     *
     * @since 3.1.0
     * @access protected
     *
     * @param object $item The current item
     */
    protected function single_row_columns( $item ) {
            list( $columns, $hidden ) = $this->get_column_info();

            foreach ( $columns as $column_name => $column_display_name ) {
                
                $attr_str = null;
                $attr = array();

                $classes = array(
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

                foreach ($attr as $key=>$value){
                    $attr_str.=sprintf(' %s="%s"',$key,$value);
                }

                printf('<td %s>',$attr_str);

                if ( method_exists( $this, 'column_' . $column_name ) ) {
                    echo call_user_func( array( $this, 'column_' . $column_name ), $item );
                }else {
                    echo $this->column_default( $item, $column_name );
                }

                echo '</td>';
            }
    }
    
    function column_default($item, $column_name){
        switch($column_name){
            case 'trackitem_order':
                $this->curr_track_idx++;
                return $this->curr_track_idx;
            case 'trackitem_play_bt':
                return wpsstm_player()->get_track_button($item);
            case 'trackitem_track':
                $artist = $item->artist;
                $track = $item->title;
                return wpsstm_get_post_track_link_by_name($artist,$track,null);
            break;
            case 'trackitem_artist':
                $artist = wpsstm_get_post_artist_link_by_name($item->artist);
                return $artist;
            break;
            case 'trackitem_album':
                $artist = $item->artist;
                $album = $item->album;
                return wpsstm_get_post_album_link_by_name($album,$artist,true);
            break;
            case 'trackitem_image':
                if ( $item->image ){
                    return sprintf('<img src="%s" itemprop="image"/>',$item->image);
                }
            break;
            case 'trackitem_sources':
                return wpsstm_sources()->get_track_sources_list($item,$this->sources_db_only); //db sources only. we'll fetch new sources using ajax.
            break;
            case 'trackitem_actions':
                $love_unlove = wpsstm_get_track_loveunlove_icons($item);
                return $love_unlove;
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
