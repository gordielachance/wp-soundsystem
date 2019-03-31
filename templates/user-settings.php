<?php
global $wpsstm_lastfm;
$enabled = $wpsstm_lastfm->lastfm_user->is_user_enabled();
$connected = ( $wpsstm_lastfm->lastfm_user->is_user_connected() === true);

/**
 * BuddyPress - Members Single Profile
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 * @version 3.0.0
 */

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/settings/profile.php */
do_action( 'bp_before_member_settings_template' ); ?>

<form action="<?php echo bp_displayed_user_domain() . bp_get_settings_slug() . '/general'; ?>" method="post" class="standard-form" id="settings-form">

    
    <h2>Last.fm</h2>
    <?php
    if (!$connected){
        $lastfm_auth_url = $wpsstm_lastfm->get_app_auth_url();
        $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
        ?>
    
        <div class="info bp-feedback">
            <span class="bp-icon" aria-hidden="true"></span>
            <p class="text">
                <?php _e("We need your permission to connect to your Last.fm account.");?><br/>
                <?php printf(__("Click %s.","wpsstm"),$lastfm_auth_link);?><br/>
            </p>
        </div>
    
        <?php
    }else{
        $user_metas = $wpsstm_lastfm->lastfm_user->get_lastfm_user_metas();
        $lastfm_user_url = 'https://www.last.fm/user/' . $user_metas['username'];
        $lastfm_user_link = sprintf('<a href="%s" target="_blank">%s</a>',$lastfm_user_url,$user_metas['username']);
        $revoke_url = 'https://www.last.fm/settings/applications';
        $revoke_link = sprintf('<a href="%s" target="_blank">%s</a> ?',$revoke_url,__('Revoke'));
        ?>
        <div class="info bp-feedback">
            <span class="bp-icon" aria-hidden="true"></span>
            <p class="text">
                <?php printf(__("We are connected to your Last.fm account, %s.","wpsstm"),$lastfm_user_link);?>  <?php echo $revoke_link;?>
            </p>
        </div>
        <?php
    }
    ?>
	<label for="user-lastfm-scrobble">
    <input type="checkbox" name="user-lastfm-scrobble" value="on" <?php checked($enabled);?>/> <?php _e('Scrobble tracks to Last.fm','wpsstm');?>
    </label>
    
	<div class="submit">
		<input type="submit" name="submit" value="<?php esc_attr_e( 'Save Changes', 'buddypress' ); ?>" id="submit" class="auto" />
	</div>

	<?php wp_nonce_field( 'wpsstm_user_settings' ); ?>

</form>

<?php

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/settings/profile.php */
do_action( 'bp_after_member_settings_template' );
