<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1));

$tracklist = $wpsstm_tracklist;



//TO FIX move at a smarter place ?
if ( $wpsstm_tracklist->get_options('can_play') ){
    do_action('init_playable_tracklist'); //used to know if we must load the player stuff (scripts/styles/html...)
}

?>

<div class="<?php echo implode(' ',$tracklist->get_tracklist_class());?>" <?php echo $tracklist->get_tracklist_attr();?>>
    <meta itemprop="numTracks" content="<?php echo $tracklist->track_count;?>" />
    <div class="tracklist-header tracklist-wpsstm_live_playlist top">
        <div>
            <i class="wpsstm-tracklist-icon wpsstm-icon"></i>
            <strong class="wpsstm-tracklist-title" itemprop="name">
                <a href="<?php echo $tracklist->get_tracklist_permalink();?>"><?php echo $tracklist->title;?></a>
            </strong>
            <small class="wpsstm-tracklist-time">
                <time class="wpsstm-tracklist-published"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo wpsstm_get_datetime( $tracklist->updated_time );?></time>
                <?php 
                if ( ($tracklist->tracklist_type == 'live') && ( $rate = $tracklist->get_time_before_refresh() ) ){
                    ?>
                    <time class="wpsstm-tracklist-refresh-time"><i class="fa fa-rss" aria-hidden="true"></i> <?php printf(__('cached for %s','wpsstm'),$rate);?></time>
                    <?php
                }
                ?>
            </small>
            <?php 
                //tracklist actions
                if ( $actions = $tracklist->get_tracklist_actions('page') ){
                    echo output_tracklist_actions($actions,'tracklist');
                }
            ?>
        </div>
        

        
        
    </div>
    <?php
    //tracklist notices

    //wizard temporary tracklist notice
    //TO FIX should be in get_wizard_tracklist() ?
    if ( !wpsstm_is_backend() && $tracklist->user_can_get_autorship() ){
        $autorship_url = $tracklist->get_tracklist_admin_gui_url('get-autorship');
        $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
        $message = __("This is a temporary playlist.","wpsstm");
        $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
        $tracklist->add_notice( 'tracklist-header', 'get-autorship', $message );

    }

    if ( $notices_el = $tracklist->get_notices_output('tracklist-header') ){
        echo sprintf('<div class="wpsstm-tracklist-notices">%s</div>',$notices_el);
    }
    ?>
    <?php
    if ( $tracklist->have_tracks() ) {
    ?>
        <table class="wpsstm-tracks-list">
            <tr class="wpsstm-tracks-list-header">
                <th class="wpsstm-track-image wpsstm-toggle-same-value" itemprop="image"></th>
                <th class="wpsstm-track-play-bt"></th>
                <th class="wpsstm-track-position">#</th>
                <th class="wpsstm-track-info wpsstm-track-artist wpsstm-toggle-same-value"><?php _e('Artist','wpsstm');?></th>
                <th class="wpsstm-track-info wpsstm-track-title wpsstm-toggle-same-value"><?php _e('Title','wpsstm');?></th>
                <th class="wpsstm-track-info wpsstm-track-album wpsstm-toggle-same-value"><?php _e('Album','wpsstm');?></th>
                <th class="wpsstm-track-actions"><?php _e('Actions','wpsstm');?></th>
                <th class="wpsstm-track-sources"><?php _e('Sources','wpsstm');?></th>
            </tr>
            <?php
            while ( $tracklist->have_tracks() ) {
                $tracklist->the_track();
                wpsstm_locate_template( 'content-track-table.php', true, false );
            } 
            ?>
       </table>
    <?php 
    }elseif( $error = $tracklist->empty_tracks_error() ){
        ?>
        <p class="wpsstm-tracks-list wpsstm-empty-tracks-list wpsstm-notice wpsstm-notice-<?php echo $error->get_error_code();?>">
            <?php echo $error->get_error_message();?>
        </p>
        <?php
    }

    ?>
</div>