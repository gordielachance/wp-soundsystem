<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WP_SoundSytem_MB_Entries extends WP_List_Table {
    
    function display_tablenav($which){
        
    }
    
    function prepare_items() {
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
    }
    
    function get_columns(){
        global $post;
        
        $columns = array(
            'cb'            => '',//<input type="checkbox" />', //Render a checkbox instead of text
        );
        
        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $columns['mbitem_artist'] = __('Artist','wpsstm');
            break;
                
            case wpsstm()->post_type_track:
                $columns['mbitem_track'] = __('Track','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                $columns['mbitem_album'] = __('Album','wpsstm');
            break;
                
            case wpsstm()->post_type_album:
                $columns['mbitem_album'] = __('Album','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                
            break;

        }
        
        //mbid
        $columns['mbitem_mbid'] = __('MusicBrainz ID','wpsstm');
        
        if (wpsstm_mb()->is_switch_entries){
            $columns['mbitem_score'] = __('Score','wpsstm');
        }

        return $columns;
    }

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @param object $item The current link object.
	 */
	public function column_cb( $item ) {
        global $post;
        
        if (!wpsstm_mb()->is_switch_entries) return;

        $mbid = wpsstm_get_post_mbid($post->ID);

		?>
		<input type="radio" name="wpsstm_mbid" id="cb-select-<?php echo $item['id']; ?>" value="<?php echo esc_attr( $item['id'] ); ?>" <?php checked($item['id'], $mbid );?> />
		<?php
	}
    
	public function column_mbitem_artist( $item ) {
        global $post;
        
        $output = '—';
        $artist = null;

        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $artist = $item;
            break;
                
            case wpsstm()->post_type_track:
            case wpsstm()->post_type_album:
                $artist = $item['artist-credit'][0]['artist'];
            break;

        }
        
        if (!$artist) return $output;
        
        $output = $artist['name'];

        if ( isset($artist['disambiguation']) ){
            $output.=' '.sprintf('<small>%s</small>',$artist['disambiguation']);
        }

        return $output;
    }
    
	public function column_mbitem_track( $item ) {
        global $post;
        
        $output = '—';
        
        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                $output = $item['title'];
            break;

        }
        
        return $output;
    }
    
	public function column_mbitem_album( $item ) {
        global $post;
        
        $album = null;
        $output = '—';

        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                 $album = $item['releases'][0];
            break;
                
            case wpsstm()->post_type_album:
                $album = $item;
            break;

        }
        
        if (!$album) return $output;

        $output = $album['title'];
        $small_title_arr = array();
        
        
        //date
        if ( isset($album['date']) ){
            $small_classes = array('item-info-title');
            $small_classes_str = wpsstm_get_classes_attr($small_classes);
            $small_title_arr[]=' '.sprintf('<small %s>%s</small>',$small_classes_str,$album['date']);
        }
        
        $output .= implode("",$small_title_arr);

        return $output;
    }
    
	/**
	 * Handles the link URL column output.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @param object $item The current link object.
	 */
	public function column_mbitem_mbid( $item ) {
        global $post;
        
        $mbid = $item['id'];
        $url = null;
        
        $mbtype = wpsstm_mb()->get_musicbrainz_type_by_post_id($post->ID);
        $url = wpsstm_mb()->get_mb_url($mbtype,$mbid);
        
        printf('<a href="%1s" target="_blank">%2s</a>',$url,$mbid);
	}
    
	public function column_mbitem_score( $item ) {
        echo wpsstm_get_percent_bar($item['score']);
	}

    
}



