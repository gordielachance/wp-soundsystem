<?php

/*
V1.0
*/

if ( class_exists('WPSSTM_Tracklist') ) return;

class WPSSTM_Tracklist{

    //infos
    var $title = null;
    var $author = null;
    var $location = null;
    var $date_timestamp = null;
    
    //datas
    var $tracks = array();
    var $notices = array();
    
    var $track;
    var $current_track = -1;
    var $track_count = null;
    var $in_subtracks_loop = false;

    /*
    $input_tracks = array of tracks objects or array of track IDs
    */
    
    function add_tracks($input_tracks){
        
        $add_tracks = array();

        //force array
        if ( !is_array($input_tracks) ) $input_tracks = array($input_tracks);

        foreach ($input_tracks as $track){

            if ( !is_a($track, 'WPSSTM_Track') ){
                if ( is_array($track) ){
                    $track_args = $track;
                    $track = new WPSSTM_Track(null,$this);
                    $track->from_array($track_args);
                }else{ //track ID
                    $track_id = $track;
                    //TO FIX check for int ?
                    $track = new WPSSTM_Track($track_id,$this);
                }
            }
            
            $add_tracks[] = $track;
        }

        $new_tracks = $this->validate_tracks($add_tracks);
        
        $this->tracks = array_merge($this->tracks,$new_tracks);
        $this->track_count = count($this->tracks);
        
        return $new_tracks;
    }

    private function validate_tracks($tracks){

        $valid_tracks = $rejected_tracks = array();
        $error_codes = array();
        
        $pending_tracks = array_unique($tracks);
        
        foreach($pending_tracks as $track){
            $valid = $track->validate_track();
            if ( is_wp_error($valid) ){

                $error_codes[] = $valid->get_error_code();
                /*
                $this->tracklist_log($valid->get_error_message(), "WPSSTM_Tracklist::validate_tracks - rejected");
                */
                $rejected_tracks[] = $track;
                continue;
            }
            $valid_tracks[] = $track;
        }
        
        if ( $rejected_tracks ){
            $error_codes = array_unique($error_codes);
            
            $cleared_tracks = array();
            foreach ($rejected_tracks as $track){
                $cleared_tracks[] = $track->to_array();
            }
            
            $this->tracklist_log(array( 'count'=>count($rejected_tracks),'codes'=>json_encode($error_codes),'rejected'=>array($cleared_tracks) ), "WPSSTM_Tracklist::validate_tracks");
        }

        return $valid_tracks;
    }

    function to_array(){
        $export = array(
            'post_id' => $this->post_id,
            'index' => $this->index,
        );
        return array_filter($export);
    }

    /**
	 * Set up the next track and iterate current track index.
	 * @return WP_Post Next track.
	 */
	public function next_subtrack() {

		$this->current_track++;

		$this->track = $this->tracks[$this->current_track];
		return $this->track;
	}

	/**
	 * Sets up the current track.
	 * Retrieves the next track, sets up the track, sets the 'in the loop'
	 * property to true.
	 * @global WP_Post $wpsstm_track
	 */
	public function the_subtrack() {
		global $wpsstm_track;
		$this->in_subtracks_loop = true;

		if ( $this->current_track == -1 ) // loop has just started
			do_action_ref_array( 'wpsstm_tracks_loop_start', array( &$this ) );

        $wpsstm_track = $this->next_subtrack();
        //$this->setup_subtrack_data( $wpsstm_track );
	}

	/**
	 * Determines whether there are more tracks available in the loop.
	 * Calls the {@see 'wpsstm_tracks_loop_end'} action when the loop is complete.
	 * @return bool True if tracks are available, false if end of loop.
	 */
	public function have_subtracks() {

		if ( $this->current_track + 1 < $this->track_count ) {
			return true;
		} elseif ( $this->current_track + 1 == $this->track_count && $this->track_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_tracks_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_tracks();
		} elseif ( 0 === $this->track_count ) {
            do_action( 'tracks_loop_no_results', $this );
        }

		$this->in_subtracks_loop = false;
		return false;
	}

	/**
	 * Rewind the tracks and reset track index.
	 * @access public
	 */
	public function rewind_tracks() {
		$this->current_track = -1;
		if ( $this->track_count > 0 ) {
			$this->track = $this->tracks[0];
		}
	}
    
    function tracklist_log($data,$title = null){
        WP_SoundSystem::debug_log($data,$title);
    }
    
}


