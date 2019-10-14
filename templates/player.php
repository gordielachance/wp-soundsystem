<?php
global $wpsstm_tracklist;
?>
<section class="wpsstm-player">
    <div class="player-row player-track"><wpsstm-track><!--loaded through JS--></wpsstm-track></div>     
    <div class="player-row player-controls">
            <span id="" class="wpsstm-player-extra wpsstm-previous-track-bt"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></span>
            <span id="wpsstm-audio-container">
                <audio <?php echo $wpsstm_tracklist->get_audio_attr();?>></audio>
            </span>
            <span class="wpsstm-player-extra wpsstm-next-track-bt"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></span>
            <span class="wpsstm-player-extra wpsstm-loop-bt"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></span>
            <span class="wpsstm-player-extra wpsstm-shuffle-bt"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></span>
            <?php
            //player actions
            if ( $actions = $wpsstm_tracklist->get_player_actions() ){
                $list = get_actions_list($actions,'player');
                echo $list;
            }                       
            ?>
    </div>
</section>