<?php

global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;

global $wpsstm_track;
$track = $wpsstm_track;
$track->populate_sources();


?>
<tr class="<?php echo implode(' ',$track->get_track_class());?>" <?php echo $track->get_track_attr();?>>
    <td class="wpsstm-track-image" itemprop="image">
        <?php 
        if ($track->image_url){
            ?>
            <img src="<?php echo $track->image_url;?>" />
            <?php
        }
        ?>
    </td>
    <?php if ( $wpsstm_tracklist->get_options('can_play') ){ ?>
        <td class="wpsstm-track-play-bt">
            <a class="wpsstm-track-icon wpsstm-icon" href="#"></a>
        </td>
    <?php } ?>
    <td class="wpsstm-track-position">
        <i class="wpsstm-player-icon wpsstm-player-icon-loading fa fa-circle-o-notch fa-spin fa-fw"></i>
        <span class="wpsstm-reposition-track"><i class="fa fa-arrows-v" aria-hidden="true"></i></span>
        <span itemprop="position"><?php echo $tracklist->current_track + 1;?></span>
    </td>
    <td class="wpsstm-track-info wpsstm-track-artist"><span itemprop="byArtist"><?php echo $track->artist;?></span></td>
    <td class="wpsstm-track-info wpsstm-track-title"><span itemprop="name"><?php echo $track->title;?></span></td>
    <td class="wpsstm-track-info wpsstm-track-album"><span itemprop="inAlbum"><?php echo $track->album;?></span></td>
    <td class="wpsstm-track-actions">
        <?php
        if ( $actions = $track->get_track_actions($tracklist,'page') ){
            echo output_tracklist_actions($actions,'track');
        }
        ?>
    </td>
    <td class="wpsstm-track-sources">
        <?php
        //track sources
        wpsstm_locate_template( 'track-sources.php', true, false );
        ?>
    </td>
</tr>
<?php
