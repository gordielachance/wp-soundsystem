<section class="wpsstm-tracklist-header top">
    <?php
    global $wpsstm_tracklist;
    $wpsstm_tracklist->html_metas();
    ?>
    <div class="wpsstm-tracklist-infos">
        <div class="wpsstm-tracklist-cover">
            <div><!--for square ratio-->
                <?php
                if ( $wpsstm_tracklist->get_options('playable') ){
                  ?>
                  <div class="wpsstm-tracklist-play-bt">
                      <i class="wpsstm-icon"></i>
                  </div>
                  <?php
                }
                ?>
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
            <ul>
                <?php
                //updated
                if ($updated = $wpsstm_tracklist->last_import_time){
                    ?>
                    <li class="wpsstm-tracklist-date">
                        <time class="wpsstm-tracklist-updated"><?php echo wpsstm_get_datetime( $updated );?></time>
                        <?php
                        //refreshed
                        if ( ($wpsstm_tracklist->tracklist_type == 'live') && $wpsstm_tracklist->get_options('cache_timeout') ){
                            $next_refresh = $wpsstm_tracklist->get_human_next_refresh_time();
                            $pulse = $wpsstm_tracklist->get_human_pulse();
                            ?>
                             <time class="wpsstm-tracklist-refresh-time" title="<?php printf(__('Still cached for %s','wpsstm'),$next_refresh);?>"><?php echo $pulse;?></time>
                            <?php
                        }
                        ?>
                    </li>
                    <?php
                }

                //tracks count
                if ( $count = $wpsstm_tracklist->get_subtracks_count() ){
                    ?>
                    <li class="wpsstm-tracklist-tracks-count">
                        <?php printf( _n( '%s track', '%s tracks', $count, 'wpsstm' ), $count );?>
                    </li>
                    <?php
                }

                ?>
                <?php
                    //original link
                    if ($wpsstm_tracklist->tracklist_type == 'live'){

                        $wpsstm_tracklist_url = ($wpsstm_tracklist->website_url) ? $wpsstm_tracklist->website_url : $wpsstm_tracklist->feed_url;

                        if ($wpsstm_tracklist_url){
                            ?>
                            <li class="wpsstm-live-tracklist-link">
                                <a target="_blank" href="<?php echo esc_attr($wpsstm_tracklist_url);?>">
                                    <?php echo wpsstm_shorten_text(esc_html($wpsstm_tracklist_url));?>
                                </a>
                            </li>
                            <?php
                        }


                    }
                ?>
            </ul>
        </div>
    </div>
    <?php
    //actions
    if ( $items = $wpsstm_tracklist->get_tracklist_context_menu_items() ){
      echo get_context_menu($items,'tracklist');
    }
    ?>
</section>
