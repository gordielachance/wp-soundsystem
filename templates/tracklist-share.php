<?php

global $wpdb;
global $wpsstm_tracklist;
global $wpsstm_track;

add_filter( 'show_admin_bar','__return_false'); //hide admin bar
do_action( 'wpsstm-popup' );
do_action( 'wpsstm-share-tracklist-popup' );
do_action( 'get_header', 'wpsstm-share-tracklist-popup' ); ////since we don't use get_header() here, fire the action so hooks still are loaded.
//

$body_classes = array(
    'wpsstm-popup',
    'wpsstm-share-tracklist-popup'
);

/*
playlists capabilities
*/
$post_type = wpsstm()->post_type_playlist;
$post_type_obj = get_post_type_object( $post_type );
$create_cap = $post_type_obj->cap->create_posts;
$edit_cap = $post_type_obj->cap->edit_posts;

?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" >
    <?php wp_head(); ?>
</head>
<body <?php body_class($body_classes); ?>>
<wpsstm-tracklist <?php echo $wpsstm_tracklist->get_tracklist_attr();?>>
    <?php wpsstm_locate_template( 'content-tracklist-header.php', true, false );?>
</wpsstm-tracklist>
    <div class="wpsstm-copy-link">
        <input type="text" class="wpsstm-fullwidth" value="<?php echo get_permalink($wpsstm_tracklist->post_id);?>" readonly />
    </div>
</body>
</html>
