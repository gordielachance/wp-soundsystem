<?php
global $wpsstm_tracklist;
?>
<div class="<?php echo implode(' ',$wpsstm_tracklist->get_tracklist_class('wpsstm-post-tracklist'));?>" <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php $wpsstm_tracklist->html_metas();?>
    <div class="tracklist-header tracklist-wpsstm_live_playlist top">
        <i class="wpsstm-tracklist-icon wpsstm-icon"></i>
        <strong class="wpsstm-tracklist-title" itemprop="name" title="<?php echo $wpsstm_tracklist->get_title();?>">
            <a target="_parent" href="<?php echo get_permalink($wpsstm_tracklist->post_id);?>"><?php echo $wpsstm_tracklist->get_title();?></a>
        </strong>
        <small class="wpsstm-tracklist-time">
            <?php
            //updated
            if ($updated = $wpsstm_tracklist->updated_time){
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
        </small>
        <?php
            //original link
            if ( ($wpsstm_tracklist->tracklist_type == 'live') && ($wpsstm_tracklist_url = $wpsstm_tracklist->feed_url) ){
                
                //$wpsstm_tracklist_url = substr($wpsstm_tracklist_url, 0, strrpos($wpsstm_tracklist_url, ' ')) . " ...";
                
                ?> 
                <a class="wpsstm-live-tracklist-link" target="_blank" href="<?php echo $wpsstm_tracklist_url;?>">
                    <i class="fa fa-link" aria-hidden="true"></i> 
                    <?php echo wpsstm_get_short_url($wpsstm_tracklist_url);?>
                </a>
                <?php
            }
        ?>
    </div>
    <?php
    
    if ( $actions = $wpsstm_tracklist->get_tracklist_links() ){
        $list = get_actions_list($actions,'tracklist');
        echo $list;
    }
    
    //tracklist notices

    //wizard temporary tracklist notice
    //TO FIX should be in populate_wizard_tracklist() ?
    if ( !wpsstm_is_backend() && $wpsstm_tracklist->can_get_tracklist_authorship() ){
        $autorship_url = $wpsstm_tracklist->get_tracklist_action_url('get-autorship');
        $autorship_link = sprintf('<a href="%s">%s</a>',$autorship_url,__("add it to your profile","wpsstm"));
        $message = __("This is a temporary playlist.","wpsstm");
        $message .= '  '.sprintf(__("Would you like to %s?","wpsstm"),$autorship_link);
        $wpsstm_tracklist->add_notice( 'tracklist-header', 'get-autorship', $message );

    }

    /*
    empty tracklist
    */
    if( $error = $wpsstm_tracklist->tracks_error ){
        $msg = sprintf( '<strong>%s</strong><br/><small>%s</small>',__('No tracks found.','wpsstm'),$error->get_error_message() );
        $wpsstm_tracklist->add_notice( 'tracklist-header', 'empty-tracklist', $msg );
    }

    if ( $notices_el = $wpsstm_tracklist->get_notices_output('tracklist-header') ){
        echo sprintf('<div class="wpsstm-tracklist-notices">%s</div>',$notices_el);
    }
    ?>

    <?php

    if ( $wpsstm_tracklist->have_tracks() ) {
    ?>
        <ul class="wpsstm-tracks-list">
            <?php
            while ( $wpsstm_tracklist->have_tracks() ) {
                $wpsstm_tracklist->the_track();
                global $wpsstm_track;
                wpsstm_locate_template( 'content-track.php', true, false );
            }
            
            //add subtrack form
            if ( $wpsstm_tracklist->user_can_reorder_tracks() ){
                ?>
                <li class="wpsstm-new-subtrack">
                <form action="<?php echo $wpsstm_tracklist->get_tracklist_action_url('render');?>">
                    <input type="text" name="wpsstm-new-subtrack[artist]" placeholder="<?php _e('Artist','wpsstm');?>"/>
                    <input type="text" name="wpsstm-new-subtrack[title]" placeholder="<?php _e('Title','wpsstm');?>"/>
                    <input type="text" name="wpsstm-new-subtrack[album]" placeholder="<?php _e('Album','wpsstm');?>"/>
                    <input type="hidden" name="wpsstm-new-subtrack[tracklist_id]" value="<?php echo $wpsstm_tracklist->post_id;?>"/>
                    <input type="hidden" name="<?php echo WPSSTM_Core_Tracklists::$qvar_tracklist_action;?>" value="append-subtrack"/>
                    <button type="submit" class="button button-primary wpsstm-icon-button"><i class="fa fa-plus" aria-hidden="true"></i><span> <?php _e('Add subtrack','wpsstm');?></span></button>
                </form>
                </li>
                <?php
            }
            ?>
       </ul>
    <?php
        wp_reset_postdata(); //TOFIXTOCHECK useful ? Since we don't use the_post here...
    }

    ?>
</div>