<?php
global $wpsstm_tracklist;
$wpsstm_tracklist->html_metas();
?>
<div class="tracklist-header top">
    <div class="wpsstm-tracklist-cover">
        <div><!--for square ratio-->
            <div class="wpsstm-tracklist-play-bt">
                <i class="wpsstm-icon"></i>
            </div>
            <div itemprop="image">
                <?php
                if ( has_post_thumbnail($wpsstm_tracklist->post_id) ) { 
                    echo get_the_post_thumbnail( $wpsstm_tracklist->post_id, 'post-thumbnail' );
                }
                ?>
            </div>
        </div>
    </div>
    <div class="wpsstm-tracklist-data">
        <h3 class="wpsstm-tracklist-title" itemprop="name" title="<?php echo $wpsstm_tracklist->title;?>">

            <a target="_parent" href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $wpsstm_tracklist->title;?></a>
                <?php
                //radio icon
                if ($wpsstm_tracklist->tracklist_type == 'live'){
                    ?>
                    <span class="wpsstm-live-tracklist-icon wpsstm-reload-bt" title="<?php _e("This is a live tracklist, it will auto-update!","wpsstm");?>">
                        <i class="fa fa-rss" aria-hidden="true"></i>
                    </span>
                    <?php
                }
                ?>
        </h3>
        <p class="wpsstm-tracklist-time">
            <?php
            //updated
            if ($updated = $wpsstm_tracklist->date_timestamp){
                ?>
                <time class="wpsstm-tracklist-updated">
                    <i class="fa fa-clock-o" aria-hidden="true"></i> 
                    <?php echo wpsstm_get_datetime( $updated );?>
                </time>
                <?php 
            }
            //refreshed
            if ( ($wpsstm_tracklist->tracklist_type == 'live') && ( $rate = $wpsstm_tracklist->get_human_next_refresh_time() ) ){
                ?>
                <time class="wpsstm-tracklist-refresh-time">
                    <i class="fa fa-rss" aria-hidden="true"></i> 
                    <?php printf(__('cached for %s','wpsstm'),$rate);?>
                </time>
                <?php
            }
            ?>
        </p>
        <?php
            //original link
            if ($wpsstm_tracklist->tracklist_type == 'live'){

                $wpsstm_tracklist_url = ($wpsstm_tracklist->website_url) ? $wpsstm_tracklist->website_url : $wpsstm_tracklist->feed_url;

                if ($wpsstm_tracklist_url){
                    ?>
                    <a class="wpsstm-live-tracklist-link" target="_blank" href="<?php echo $wpsstm_tracklist_url;?>">
                        <i class="fa fa-link" aria-hidden="true"></i> 
                        <?php echo wpsstm_shorten_text($wpsstm_tracklist_url);?>
                    </a>
                    <?php
                }


            }
        ?>
    </div>
</div>