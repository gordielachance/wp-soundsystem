<?php

class WP_SoundSystem_Live_Playlist_Stats{ //TO FIX TO UPGRADE
    
    static $meta_key_health = 'wpsstm_health';
    static $meta_key_requests = 'wpsstm_total_requests';
    static $meta_key_monthly_requests = 'wpsstm_monthly_requests';
    static $meta_key_monthly_requests_log = 'wpsstm_monthly_requests_log';
    
    private $tracklist;
    
    function __construct($tracklist = null){
        $this->tracklist = $tracklist;
        if (!$this->tracklist->post_id) return;
        
        $this->set_health();
        $this->set_request_count();
        $this->set_monthly_request_count();
        
    }

    /**
     * Get the number of time tracks have been requested
     * @global type $post
     * @param type $post_id
     * @return boolean
     */

    static function get_request_count($post_id = null){

        global $post;
        if (!$post_id) $post_id = $post->ID;

        $count = get_post_meta($post_id, self::$meta_key_requests, true);

        return (int)$count;
    }
    
    static function get_monthly_request_count($post_id = null){

        global $post;
        if (!$post_id) $post_id = $post->ID;

        $count = get_post_meta($post_id, self::$meta_key_monthly_requests, true);

        return (int)$count;
    }
    
    /**
     * Checks if the playlist is still alive : each time tracks are populated,
     * A "health" meta is added with the time and number of tracks found.
     * If health fell to zero, maybe the playlist is no more alive.
     * @return boolean
     */

    static function get_health($post_id = null){

        global $post;
        if (!$post_id) $post_id = $post->ID;

        $meta = get_post_meta($post_id, self::$meta_key_health, true);

        $percent = (int)$meta * 100;

        return $percent;     

    }
    
    static function get_health_log($post_id = null){

        global $post;
        if (!$post_id) $post_id = $post->ID;

        $time = current_time( 'timestamp' ); //not UTC
        $transient_log_prefix = sprintf('wpsstm_%s_total_tracks_',$post_id);
        $transient_log_name = $transient_log_prefix . $time;

        //get health log
        $log = array();
        if ( $log_transients = wpsstm_get_transients_by_prefix($transient_log_prefix) ){
            
            foreach((array)$log_transients as $log_transient_name){
                $time = str_replace($transient_log_prefix,'',$log_transient_name);
                $log[$time] = get_transient($log_transient_name);
            }

        }

        return $log;
        
    }
    
    /**
     * Each time the tracks are requested for this station, update the meta.
     * @return type
     */
    
    function set_request_count(){
        $post_id = $this->tracklist->post_id;
        
        if ( get_post_status($post_id) != 'publish') return;
        
        //total count
        $count = (int)get_post_meta($post_id, self::$meta_key_requests, true);
        $count++;
        
        if ( $success = update_post_meta($post_id, self::$meta_key_requests, $count) ){
            wpsstm()->debug_log($count,"WP_SoundSystem_Live_Playlist_Stats::set_request_count()"); 
            return $success;
        }

    }
    
    /**
     * Update the number of tracks requests for the month, in two metas :
     * self::$meta_key_monthly_requests_log (array of entries with the timestamp tracks where requested)
     * self::$meta_key_monthly_requests (total requests)
     * @return type
     */
    
    function set_monthly_request_count(){

        $post_id = $this->tracklist->post_id;

        if ( get_post_status($post_id) != 'publish') return;

        $log = array();
        $time = current_time( 'timestamp' ); //not UTC
        $time_remove = strtotime('-1 month',$time); 
        
        if ($existing_log = get_post_meta($post_id, self::$meta_key_monthly_requests_log, true)){ //get month log
            $log = $existing_log;
        }

        //remove entries that are too old from log metas (multiple)
        foreach ((array)$log as $key=>$log_time){
            if ($log_time <= $time_remove){
                unset($log[$key]);
            }
        }
        
        //update log
        $log[] = $time;
        update_post_meta($post_id, self::$meta_key_monthly_requests_log, $log);
        
        //avoid duplicates
        $log = array_filter($log);

        //update requests count
        $count = count($log);

        if ( $success = update_post_meta($post_id, self::$meta_key_monthly_requests, $count ) ){
            wpsstm()->debug_log($count,"WP_SoundSystem_Live_Playlist_Stats::set_monthly_request_count()"); 
            return $success;
        }
        
    }
    
    /**
     * Each time the tracks are requested for this live playlist, log the number of tracks fetched.
     * @param type $tracks
     * @return type
     */
    
    function set_health(){

        $post_id = $this->tracklist->post_id;

        if ( get_post_status($post_id) != 'publish') return;

        $time = current_time( 'timestamp' ); //not UTC
        $transient_name_freeze = sprintf('wpsstm_%s_freeze_health',$post_id);
        $transient_log_prefix = sprintf('wpsstm_%s_total_tracks_',$post_id);
        $transient_log_name = $transient_log_prefix . $time;
        
        //abord if last updated <10 min
        if ( get_transient($transient_name_freeze) ) return;
        
        //get health log
        $log = $this->get_health_log($post_id);

        //add new log entry
        $total_tracks = count( $this->tracklist->tracks );
        set_transient( $transient_log_name, $total_tracks, 7 * DAY_IN_SECONDS ); //max 1 week
        $log[$time] = $total_tracks;
        
        //update health
        $log_success_pc = 0;
        if ( $log_count = count($log) ){
            $log_success = array_filter($log, function($total_tracks){return ($total_tracks);} ); //remove items where tracks count is null
            $log_success_count = count($log_success);
            $log_success_pc = $log_success_count / $log_count;
            $log_success_pc = round($log_success_pc, 1);
        }

        //freeze for 10 mins
        set_transient( $transient_name_freeze, true, 10 * MINUTE_IN_SECONDS );

        wpsstm()->debug_log($log_success_pc,"WP_SoundSystem_Live_Playlist_Stats::set_health()"); 
        return update_post_meta($post_id, self::$meta_key_health, $log_success_pc);
        
    }
    
}