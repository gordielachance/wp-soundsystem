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

    static $blank_jspf = [
      "title"=>        null,
      "creator"=>      null,
      "annotation"=>   null,
      "info"=>         null,
      "location"=>     null,
      "identifier"=>   null,
      "image"=>        null,
      "date"=>         null,
      "license"=>      null,
      "attribution"=>  [],
      "link"=>         [],
      "meta"=>         [],
      "extension"=>    [],
      "track"=>        []
    ];

    function from_jspf($jspf){

      $jspf = wpsstm_get_array_value('playlist',$jspf);
      if (!$jspf){
        return new WP_Error('missing_playlist_node','no playlist node in the response');
      }

      //remove properties that do not exists in our blank array
      $arr = array_intersect_key($jspf, self::$blank_jspf);
      $arr = array_merge(self::$blank_jspf,$arr);

      $playlist->title = $arr['title'] ?? null;
      $playlist->author = $arr['creator'] ?? null;
      $playlist->location = $arr['location'] ?? null;

      $date = $arr['date'] ?? null;
      if ( $date ){
        $playlist->date_timestamp = strtotime($date);//TOUFIX TOUCHECK
      }

      //TRACKS
      $tracks_out = array();
      $tracks_in = $arr['track'] ?? [];


      foreach ((array)$tracks_in as $track_arr) {

        $track = new WPSSTM_Track();
        $track->from_jspf($track_arr);

        $tracks_out[] = $track;
      }

      $this->add_tracks($tracks_out);
    }

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

        foreach($tracks as $track){
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

    /*
    Return one level array
    */

    function to_array(){
        $arr = array(
            'post_id' => $this->post_id,
            'index' => $this->index,
        );

        return array_filter($arr);

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

  function get_player_actions(){
      $actions = array();
      return apply_filters('wpsstm_get_player_actions',$actions);
  }

  function get_audio_attr($values_attr=null){

      //https://www.w3schools.com/tags/tag_audio.asp
      $values_defaults = array();

      $values_attr = array_merge($values_defaults,(array)$values_attr);

      return wpsstm_get_html_attr($values_attr);
  }

  function tracklist_log($data,$title = null){
      WP_SoundSystem::debug_log($data,$title);
  }

}
