=== WP SoundSystem ===
Contributors:grosbouff
Donate link:http://bit.ly/gbreant
Tags: music,library,playlists,collection,artists,tracks,albums,MusicBrainz,xspf
Requires at least: 3.6
Tested up to: 4.7.4
Stable tag: trunk
License: GPLv2 or later

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !

== Description ==

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !

= Several new post types =
Tracks, artists, albums, playlists and live playlists uses each a custom post type, so you can easily manage them.

= Playlists =

Managing the playlist tracks is a piece of cake using the Tracklist metabox :
Add or remove tracks on the fly, reorder them, and link one or several music sources to each track.

Import a tracklist from a file or a music service like Spotify using the Tracklist Importer (see below).
Export playlists in [XSPF](https://en.wikipedia.org/wiki/XML_Shareable_Playlist_Format) (XML Shareable Playlist Format).

= MusicBrainz =
When managing a track, artist or album, the plugin can search for its MusicBrainz ID.
It makes it easier to identify the items, and loads various metadatas from [MusicBrainz](https://musicbrainz.org/) (an open data music database).
For example, when creating an album post, you can load its tracklist from the MusicBrainz datas; so you don't need to enter each track manually.

= Audio player =
When viewing a post that contains a tracklist, an audio player will show up to play your tracks !

The audio player uses the native [MediaElement.js](http://www.mediaelementjs.com/) media framework.
The current version that is shipped with Wordpress is obsolete; so you'll need to upgrade it manually (see [ticket#39686](https://core.trac.wordpress.org/ticket/39686)).  It should be OK when Wordpress 4.8 is released.

Supported sources : regular audio files, Youtube, Soundcloud.

= Auto-source =
If you didn't set sources for your tracks (see below) and that the "auto-source" setting is checked; the audio player will try to find an online source automatically (Youtube, Soundcloud, ...) based on the track informations.

= Music Sources Metabox =
Set one or several music sources for your tracks when editing them; as on screenshot #8.
It could be a local audio file or a link to a music service.

Those links will be used by the audio player (see above) to play the track if the source URL is supported.

= Tracklist Importer Metabox =

Enter the URL of a tracklist (eg. a local XSPF file, a Spotify Playlist, a radio station page...) to scrape its data.

For popular services like Spotify or Radionomy, no need to go any further.
But if the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.
This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

Native presets : Last.FM, Spotify, Radionomy, Deezer, SomaFM, BBC, Slacker, Soundcloud, Twitter, Soundsgood, Hype Machine.

= Live Playlists =

(disabled by default, you can enable them by acessing the plugin's settings page)

Live Playlists lets you grab a tracklist from a remote URL (eg. a radio station page) using the Tracklist Importer (see above).
The tracklist will stay synchronized with its source : it will be updated each time someone access the Live Playlist post.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?post_type=wpsstm_live_playlist).

You may also propose a Frontend Tracklist Importer to your visitors. 
They will be able to use it to get a remote tracklist; which could be useful for backups or to load a tracklist in a software like [Tomahawk](https://www.tomahawk-player.org/).
Just create a blank page and set its ID for the *Frontend wizard page ID* field in the plugin settings page.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?p=213).

= Shortcodes =

`[wpsstm-track post_id="150"]`

To embed the single track #150.
Optional arguments : *post_id*.

`[wpsstm-tracklist post_id="160"]`

To embed the tracklist from the post #160.  
Works for albums, playlists and live playlists.
Optional arguments : *post_id*,*max_rows*.

= Post Bookmarks plugin =
If the [Post Bookmarks plugin](https://wordpress.org/plugins/post-bookmarks/) is enabled, links from MusicBrainz will be suggested in the Post Bookmarks metabox.

= Donate! =
It truly took me a LOT of time to code this plugin, and I decided to release it for free - without any "Premium" plans.
If you like it, please consider [making a donation](http://bit.ly/gbreant).
This would be very appreciated — Thanks !

= Dependencies =

* [phpQuery](https://github.com/punkave/phpQuery) - a PHP port of jQuery selectors
* [URI.js](https://github.com/medialize/URI.js) - Javascript URL mutation library

= Contributors =

Contributors [are listed here](https://github.com/gordielachance/wp-soundsystem/contributors)

= Notes =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/wp-soundsystem/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/wp-soundsystem). Any contribution would be very welcome.

== Installation ==

1. Upload the plugin to your blog and Activate it.

== Frequently Asked Questions ==

= How can I display the tracklist of a post in my templates ? =

Use the tracklist shortcode **[wpsstm-tracklist]** in your post content (see the *shortcodes* section above), or use those functions directly in your templates :

`<?php
$tracklist = wpsstm_get_post_tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_table();
?>`

= How can I alter the music sources for a track ? =
Hook a custom function on the filter *wpsstm_get_track_sources_db* or *wpsstm_get_track_sources_remote*.

* use *wpsstm_get_track_sources_db* when populating sources from your database
* use *wpsstm_get_track_sources_remote* when populating sources from a remote URl - like an API (slower and thus requested through ajax).

`<?php
function my_filter_get_source_db($sources,$track){
    //...your code here...
    return $sources;
}
add_filter('wpsstm_get_track_sources_db','my_filter_get_source_db',10,2);
?>`

= Standalone tracks vs Subtracks vs Live Playlist tracks ? =

Playlist and Albums tracks are saved as Track posts.  
The playlist / album will have a post meta *wpsstm_subtrack_ids* that contains an array of ordered track IDs.  It is what we call "subtracks" in the plugin's code.

Unlike playlists and albums, the Live Playlists tracks are not stored as Track posts but as a [transient](https://codex.wordpress.org/Transients_API), to avoid creating too much posts over and over.  The name of that transient starts with *wpsstm_ltracks_*.

== Screenshots ==

1. Settings page
2. Artists menu
3. Tracks menu
4. Albums menu
5. Playlists menu
6. Tracklist metabox
7. Tracklist parser metabox
8. Music sources metabox

== Changelog ==

= 0.9.9.4 =
* WP_SoundSystem_Track:: get_unique_id() : use sanitize_title
* Tracklists Table : share link
* Tracklists Table : fixed time scraped
* Scraper : handle Dropbox links
* Scraper : Fix get_track_image() and get_track_source_urls()
* Scraper : fixed XSPF options when xpsf content is loaded
* Player : tracks sources preloads
* Player : track infos (track title, provider link)
* Player : sources switch
* Player : redirection notice with timer
* Player : confirmation popup when leaving the page with a media playing
* Player : track button : new 'has-played' class

= 0.9.9.3 =
* player buttons : previous/next track & previous/next page
* splitter presets into multiple files
* improved scraper-remote.php

= 0.9.9.2 =
* new "auto-source" feature !  Try to find a track source online if none is set in the database (ajaxed).
* player : new settings : "enabled", "auto-play", "auto-redirect" and "auto-source".

= 0.9.9.1 =
* sources : now an array (url,title,description) instead of just an url.

= 0.9.9.0 =
* new Deezer preset
* Youtube sources (if any) for Last.FM preset
* improved player
* improved sources

= 0.9.8.9 =
* scraper now able to get an array of sources urls for each track.
* get_track_node_content() : new argument '$single_value' (default to TRUE)

= 0.9.8.8 =
* improved wizard GUI
* scraper : now we can query an element's attribute (disabled if not HTML content)
* scraper : improved options
* scraper : now uses a dynamic selector for the tracklist title

= 0.9.8.7 =
* varioux fixes
* improved shortenTables
* mute unecessary columns in the backend listings
* improved frontend tracklists

= 0.9.8.6 =
* new "sources" metabox - don't use Post Bookmarks for this anymore.

= 0.9.8.5 =
* scraper : set tracklist informations only if not already defined - So tracklist that has been populated with a post ID has not its
informations overriden
* sanitize string at the end of WP_SoundSytem_Playlist_Scraper_Datas::get_track_node_content()
* new Hype Machine preset

= 0.9.8.4 =
* XSPF output : added title, author, timestamp, location and annotation nodes
* xspf urls : moved download argument at the end
* hook on 'wpsstm_get_post_tracklist' to get a live playlist tracklist
* added rewrite rule for frontend wizard

= 0.9.8.3 =
* doc
* live playlists : when not displaying a single page, add a notice to load the tracklist
* scraper : cache only if several post are displayed (like an archive page)

= 0.9.8.2 =
* try to use parser without converting encoding
* fixed Spotify preset (now uses API)
* new API settings for Spotify
* post_tag taxonomy for albums & tracks
* frontend CSS fixes

= 0.9.8.1 =
* sort stations by health / trending / popular
* fixed ajaxed row actions for tracklist rows
* improved classes WP_SoundSystem_Subtrack() and WP_SoundSystem_Track()
* fix title comparaison check when updating artist/album/track
* fix Spiff plugin upgrade routine
* renamed Array2XML > WP_SoundSytem_Array2XML
* new function WP_SoundSytem::is_admin_page()
* no CSS background for tracklist table

= 0.9.8 =
* show player column only if at least one track has sources
* tracks : auto guess ID if not defined
* improved presets + fixed Soundsgood preset
* header admin notice (review / donations)
* abord update_title_artist() and update_title_album() if value missing
* improved fill_post_with_mbdatas()
* improved presets suggestions in the wizard
* changed live tracks transient name

= 0.9.7 =
* Better track & album auto title
* Hide title input as we set it automatically - but keep the feature since it outputs the permalink and 'view' link
* no more options for the "Fill with datas" button from the Musicbrainz Metabox
* improved how posts are automatically filled with MusicBrainz data
* improved wpsstm_get_all_subtrack_ids() and wpsstm_get_subtrack_parent_ids()

= 0.9.6 =
* Changed how subtracks are stored : now we store an array of subtrack IDs in the tracklist post; while before we were saving the tracklist ID in each track.
* 'Hide subtracks' filter now works - still some work to do on this

= 0.9.5 =

* live playlists disabled by default
* ajax : reorder tracklist tracks with ajax only
* include scraper metabox even if live playlists are disabled
* fixed bug when deleting subtracks
* populate track sources only once
* Add title support for albums, artists & tracks (wasn't showing the 'View Post' or permalink on the post page)
* tracklists : add 'data-tracks-count' attribute

* WIP : audio player
* Show a play button in tracklist tables
* Embed a tracklist for single tracks too

* WIP : frontend scraper

= 0.9.4 =
* improved presets - much cleaner code here.
* improved scraper notices; don't uses add_settings_error anymore.

= 0.9.3 =
* new 'Twitter' and 'XSPF' presets
* improved presets
* validate_tracks() in function content_append_tracklist_table() and file playlist-xspf.php
* Improved the saving routine for metaboxes

= 0.9.2 =
* New 'BBC Station', 'BBC Playlist', and 'Slacker.com station tops' scraper presets
* New 'wpsstm-track' and 'wpsstm-tracklist' shortcodes

= 0.9.1 =
* New Radionomy preset
* Improved scraper presets system
* Improved Post Bookmarks

= 0.9 =
* First release

== Upgrade Notice ==

== Localization ==
