<?php
global $wpsstm_tracklist;
?>
<wpsstm-player>
    <div class="player-row">
            <span id="wpsstm-player-extra-previous-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-backward" aria-hidden="true"></i></a></span>
            <span id="wpsstm-audio-container">
                <audio <?php echo $wpsstm_tracklist->get_audio_attr();?>></audio>
            </span>
            <span id="wpsstm-player-extra-next-track" class="wpsstm-player-extra"><a href="#"><i class="fa fa-forward" aria-hidden="true"></i></a></span>
            <span id="wpsstm-player-loop" class="wpsstm-player-extra"><a title="<?php _e('Loop','wpsstm');?>" href="#"><i class="fa fa-refresh" aria-hidden="true"></i></a></span>
            <span id="wpsstm-player-shuffle" class="wpsstm-player-extra"><a title="<?php _e('Random Wisdom','wpsstm');?>" href="#"><i class="fa fa-random" aria-hidden="true"></i></a></span>
            <?php
            //player actions
            if ( $actions = $wpsstm_tracklist->get_player_actions() ){
                $list = get_actions_list($actions,'player');
                echo $list;
            }                       
            ?>
    </div>
</wpsstm-player>