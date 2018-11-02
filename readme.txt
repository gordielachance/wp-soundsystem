=== WP SoundSystem ===
Contributors: grosbouff
Donate link: http://bit.ly/gbreant
Tags: music,library,playlists,collection,artists,tracks,albums,MusicBrainz,xspf
Requires at least: 4.9
Tested up to: 5
Stable tag: trunk
License: GPLv2 or later

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !

== Description ==

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  It's the perfect fit for your music blog !

= Several new post types =

Playlists, Live Playlists, Albums, Artists, Tracks and Track Sources each uses a custom post type, so you can easily manage/extend them.

= Playlists =

Managing the playlist tracks is a piece of cake using the *Tracklist metabox*:
Add or remove tracks on the fly, reorder them, and link one or several music sources to each track.

Import a tracklist from a file or a music service like Spotify using the *Remote Tracklist Manager* (see below).

= Audio player =

When viewing a post that contains a tracklist, an audio player will show up to play your tracks !

**Supported sources**: Youtube, Soundcloud, regular audio files.

= Track Sources =

If you didn't set sources for your tracks (see below) and that the **autosource** option is enabled; the audio player will try to find an online source automatically (Youtube, Soundcloud, ...) based on the track informations.

Those links will be used by the audio player (see above) to play the track if the source URL is supported.

= Remote Tracklist Manager Metabox =

Enter the URL of a tracklist (eg. a local XSPF file, a Spotify Playlist, a radio station page...) to scrape its data.

Popular services like Spotify or Radionomy are automated through presets; and you will not need to do anything to retrieve their tracklists.

But if the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.

This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

**Native presets**: Last.fm, Spotify, Radionomy, Deezer, SomaFM, BBC, Slacker, Soundcloud, Twitter, Soundsgood, Hype Machine, Reddit, Indie Shuffle, RadioKing, Online Radio Box.

You may also propose a **Frontend Tracklist Importer** to your visitors: just create a blank page and set its ID for the *Frontend wizard page ID* field in the plugin settings page.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?p=213).

= Live Playlists =

Live Playlists lets you grab a tracklist from a remote URL and remains **synchronised** with it.  
For example, you can load a radio station page and the plugin will keep its tracklist up-to-date automatically; and eventually temporary store the tracks if tracklist cache is enabled.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?post_type=wpsstm_live_playlist).

= MusicBrainz =

When managing a track, artist or album, the plugin can search for its **MusicBrainz ID**.
It makes it easier to identify the items, and loads various metadatas from [MusicBrainz](https://musicbrainz.org/) — an open data music database.
For example, when creating an album post, you can load its tracklist from the MusicBrainz datas; so you don't need to enter each track manually.

= Last.fm =

The audio player can **scrobble** tracks to your Last.fm account; or add tracks to your Last.fm favorites.

= Shortcodes =

`[wpsstm-track post_id="150"]`

To embed the single track #150.
Optional arguments: *post_id*.

`[wpsstm-tracklist post_id="160"]`

To embed the tracklist from the post #160.  
Works for albums, playlists and live playlists.
Optional arguments: *post_id*,*max_rows*.

= Donate! =

It truly took me a LOT of time to code this plugin, and I decided to release it for free - without any "Premium" plans.
If you like it, please consider [making a donation](http://bit.ly/gbreant).
This would be very appreciated — Thanks !

= Dependencies =

* [phpQuery](https://github.com/punkave/phpQuery) - a PHP port of jQuery selectors
* [PHP Last.fm API](https://github.com/matt-oakes/PHP-Last.fm-API) - Last.fm scrobbling
* [forceutf8](https://github.com/neitanod/forceutf8) - fixes mixed encoded strings

= Contributors =

Contributors [are listed here](https://github.com/gordielachance/wp-soundsystem/contributors)

= Notes =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/wp-soundsystem/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/wp-soundsystem). Any contribution would be very welcome.

== Installation ==

This plugin requires PHP Version 5.4 or later.

1. Upload the plugin to your blog and Activate it.
2.  Go to the settings page and setup the plugin.

== Frequently Asked Questions ==

= The audio player does not go to the next track ! =

This only happens with Chrome: due to a restriction of this browser, the player will not be able to load the next track if it is opened in a background tab. 
Here's [how to fix it](https://github.com/gordielachance/wp-soundsystem/issues/18).

= How can I display the tracklist of a post in my templates ? =

Use the tracklist shortcode **[wpsstm-tracklist]** in your post content (see the *shortcodes* section above), or use those functions directly in your templates:

`<?php
$tracklist = wpsstm_get_tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_html();
?>`

= What are community tracks and when are they created ? =

Community tracks are tracks that are automatically created by the plugin and for which the author is the community user (see settings).
They are created when a live tracklist is updated; only if its cache is disabled/expired.
A community track is also created when we autosource a track; so the sources query is ran only once.

There is an option in the plugin settings to flush those community tracks : they will be deleted; but only if they do not appear in a tracklist and are not favorited by any users.

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

= 1.9.8 =
* Improved wizard
* Improved autosource
* Improved templates
* Better tracks source GUI
* Spotify metabox
* Static track unlink action
* Cache remote HTML file
* PHP : pass tracklist object  to the track object / pass track object  to the source object
* tracklist_log, track_log, source_log
* store durations as milliseconds instead of seconds
* new filter 'wpsstm_pre_save_autosources'
* TOFIX remote request pagination

= 1.9.4 =
* Better settings page errors
* Improved metaboxes for artist/track/album/MusicBrainz
* new track length meta
* remove frontend edit functions for track (load backend in popup)
* new playlist subtrack : create it and load backend in popup
* save autosource time in meta '_wpsstm_autosource_time'

= 1.9.3 =
* Cleaned up a lot of code : https://github.com/gordielachance/wp-soundsystem/commit/9b2742e9e4fc0a021766c115dd81a58bd0b90073

= 1.9.2 =
* Do not wait for JS document.ready (https://github.com/gordielachance/wp-soundsystem/issues/50)

= 1.9.1 =
* Improve popup template
* Improve track/tracklist frontend admin

= 1.9.0 =
* Subtracks are now stored in the custom 'wp_wpsstm_subtracks' SQL table.  It contains the track ID, tracklist ID, and track position.  This makes easier to handle subtracks.
* Improved sources reorder
* Improved source delete

= 1.8.9 =
* Single template for tracklists
* When wizard input is not an URL, redirect to last.fm track search
* Autosourcing: create post if it does not exists yet

= 1.8.8 =
* presets: use filters instead of extending the tracklist class
* JS - improved toggleChildren.js
* 'edit backend' action for tracklists/tracks/sources
* new 'autoload' setting for tracklists
* .playable-opacity setting / CSS
* improved 'services' wizard widget
* new plugin option 'Flush Community Tracks'
* live playlists : get_the_title() now returns the remote tracklist title if WP post title is empty ('the_cached_remote_title' hooked on 'the_title')
* remove title support for artist/album/track
* some live tracklist & wizard improvements
* fixed shuffle mode

= 1.8.7 =
* restored XSPF preset
* removed FontAwesome from SCSS
* use jQuery UI Dialog instead of Thickbox
* improved notices
* improved player/tracklists/tracks/sources actions
* cleaned SCSS/CSS + plugin option to disable default styles
* embed player backend

= 1.8.6 =
* better code for tracklists and tracks actions
* ability to redirect to a track action even if the track does not exists in the DB yet - see get_track_action_url() and get_new_track_url()
* better sources manager
* WIP sources list: add link to reorder sources (drag & drop) - not yet working but started to implement it

= 1.8.5 =
* welcome Wordpress 4.9! Finally!
* improved wizard (GUI, presets, widgets), etc.
* ajax artist autocomplete
* fixed WPSSTM_Preset_LastFM_Artist_Scraper
* strict validate tracks on the frontend wizard
* fix shortcode fatal error when post requested does not exists

= 1.8.0 =
* better Last.fm presets (including last.fm stations)
* improved JS player
* improved caching stuff
* improved CSS

= 1.7.5 =
* live tracklists : rewritten some code, improved caching stuff
* frontend wizard: if a tracklist is successfully loaded; add the tracklist title to the page title
* wizard: wizard input can be not only URLs (eg. spotify playlists URIs)
* new custom event 'wpsstmTrackSourcesDomReady' and renamed 'wpsstmTrackSourceDomReady' to 'wpsstmTrackSingleSourceDomReady'
* tracklist/track/source 'position' property renamed to 'index'
* JS: don't use tracklist_idx/track_idx/source_idx but reference original object (tracklist/track/source)
* JS: removed a lot of references to get_tracklist_obj() and renamed to get_page_tracklist(), removed function get_tracklist_track_obj()

= 1.7.4 =
* new preset for 8tracks.com
* sources: improved styles frontend
* sources: icon to delete a track source frontend
* tracks JS: new 'to_ajax()' function
* tracks JS & lastm JS: send track data instead of track ID only
* tracks : can be loved even if track does not exists yet (thus create it)
* if loved track has been created, updates the 'data-wpsstm-track-id' attribute

= 1.7.3 =
* Fix broken frontend wizard
* add support for Spotify Playlists URIs
* Better backend settings notices
* Submenus now registered in each post type + new hook 'wpsstm_register_submenus'
* Sources: 'Filter sources' (by track) link in the 'match' column + edit track link

= 1.7.2 =
* JS : improve player

= 1.7.1 =
* option to disable default styling
* improved CSS
* JS: pass tracklist options in get_tracklist_request()

= 1.7.0 =
* wizard : share / export works properly + better way of populating wizard
* wizard : notice if using a preset and that the scraper options do not match the preset scraper options
* some bug fixes & improvements

= 1.6.9 =
* fix typo when including reddit preset

= 1.6.8 =
* better way to include presets
* more tracklist settings

= 1.6.7 =
* fix broken JS player navigation
* fix previous_tracklist_jump() / next_tracklist_jump() when repeat is enabled and that no other playlist is available to play
* tracklist option to hide columns (JS) when all of its cells have the same value (or are empty)

= 1.6.5 =
* new filter 'wpsstm_input_tracks'
* upgraded validate_tracks()
* ability to load tracklist and sources without storing the tracklist neither the sources; this is a huge thing!
* lots of improvements on the Wizard; including that it doesn't store things anymore when previewing a tracklist.
* Custom loop functions for tracks & sources
* new preset for Online Radio Box

= 1.6.1 =

* JS: better way to iterate promises in get_first_availablelist() and get_first_available()
* tracklists : fixed tracks reordering and tracks removing
* improved tracklists/tracks/sources classes HTML attribute
* load tracklist options as a json string in the 'data-wpsstm-tracklist-options' HTML attribute

= 1.6.0 =

* use templates files to display stuff (tracklists, wizard, etc); see /templates directory
* improved JS player
* improved capabilities
* javascript expiration : use UTC timestamp instead of remaining sec
* set auto source status to 'pending' if it seems unreliable

= 1.5.0 =

* tracks from live playlists are now saved as regular posts (with the community user as author) and flushed at each tracklist refresh
* track sources are now saved as regular posts (with the community user as author if they are auto-populated)
* new RadioKing preset
* better code structure (splitted into files) for tracklists / tracks / track sources (JS & CSS)
* new JS events: wpsstmTracklistRefreshed,wpsstmTrackDomReady - wpsstmTrackSingleSourceDomReady
* Improved wizard backend & frontend
* Removed class 'WPSSTM_Subtrack': cleaner to handle everything with WPSSTM_Track
* removed WPSSTM_TracksList_Admin_Table, now everything is handled by WPSSTM_Tracklist_Table
* Abord auto_auto_mbid() for tracks when saving subtracks (too slow); or if post is trashed
* improved actions for tracks & tracklists; according to the logged user capabilities, and with popups.
* new option autosource_filter_ban_words (experimental)
* new option autosource_filter_requires_artist (experimental)
* more code cleanup & fixes

= 1.0.2.9 =

* player.js - refresh playlists: use timeout instead of interval
* wpsstm-shortenTables > jquery.toggleChildren
* scroll to playlist track when clicking the player's track number
* tracklists GUI fixes

= 1.0.2.6 =

* improved tracks GUI (buffering, active, etc)

= 1.0.2.5 =

* wp_unslash() ajax_data 
* improved wizard cache
* WPSSTM_Preset_Radionomy_Playlists_API
* new dependency: forceutf8 + composer update
* wizard: better cache handling for wizard
* player.js: fix playlist no more refreshing
* fix refresh link not always displayed
* fix remove notices when playlist request failed
* play_or_skip: ignore action if we've skipped the track + small timeout to fix fast tracks skips

= 1.0.2 =

* Setting for Last.fm bot scrobbler (scrobbles every track listened by any user)
* new class WPSSTM_LastFM_User()

= 1.0.1 =

* Improved sources / autosource code
* lastfm.js: fixed lastfm_auth_notice()
* removed 'autoredirect' option
* WPSSTM_Core_Wizard: option to delete current cache
* fixed ignore cache in wizard
* bottom player: better GUI for source selection
* if the track has 'native' sources and that they cannot play, try to autosource
* improved WPSSTM_Player_Provider subclasses
* new action hook 'init_playable_tracklist'
* fixed crash when Live Playlists are not enabled (always include wpsstm-core-playlists-live.php)
* track & tracklist: sources popup (thickbox) + ajaxed sources suggestions

= 1.0.0 =

* player.js: now uses javascript classes
* player.js: promises & deferred objects
* "random" button
* "loop" button
* new preset for indieshuffle.com

= 0.9.9.6 =

* Ajax for live playlists refresh
* Improved player / tracklists
* remove editor/author support for albums, artists & tracks
* started to implement user functions (favorite tracks / favorite playlists)

= 0.9.9.5 =

* remove WPSSTM_Playlist_Scraper class, new class WPSSTM_Remote_Tracklist instead (much simplier)
* improved saving wizard options
* tracklists pagination !
* live tracklists: new expiration_time var
* new Reddit preset (to be improved)
* improved validate_track() 
* last.FM scrobbling
* settings: last.FM client ID & secret
* Player: 'Choose a source'
* improved tracklist table (schema.org) 
* removed tracklist duration column

= 0.9.9.4 =

* WPSSTM_Track:: get_unique_id(): use sanitize_title
* Tracklists Table: share link
* Tracklists Table: fixed time scraped
* Scraper: handle Dropbox links
* Scraper: Fix get_track_image() and get_track_source_urls()
* Scraper: fixed XSPF options when xpsf content is loaded
* Player: tracks sources preloads
* Player: track infos (track title, provider link)
* Player: sources switch
* Player: redirection notice with timer
* Player: confirmation popup when leaving the page with a media playing
* Player: track button: new 'has-played' class

= 0.9.9.3 =

* player buttons: previous/next track & previous/next page
* splitter presets into multiple files
* improved wpsstm-live-tracklist-class.php

= 0.9.9.2 =

* new "autosource" feature !  Try to find a track source online if none is set in the database (ajaxed).
* player: new settings: "enabled", "autoplay", "auto-redirect" and "autosource".

= 0.9.9.1 =

* sources: now an array (url,title,description) instead of just an url.

= 0.9.9.0 =

* new Deezer preset
* Youtube sources (if any) for Last.fm preset
* improved player
* improved sources

= 0.9.8.9 =

* scraper now able to get an array of sources urls for each track.
* parse_node(): new argument '$single_value' (default to TRUE)

= 0.9.8.8 =

* improved wizard GUI
* scraper: now we can query an element's attribute (disabled if not HTML content)
* scraper: improved options
* scraper: now uses a dynamic selector for the tracklist title

= 0.9.8.7 =

* varioux fixes
* improved shortenTables
* mute unecessary columns in the backend listings
* improved frontend tracklists

= 0.9.8.6 =

* new "sources" metabox - don't use Post Bookmarks for this anymore.

= 0.9.8.5 =

* scraper: set tracklist informations only if not already defined - So tracklist that has been populated with a post ID has not its
informations overriden
* sanitize string at the end of WPSSTM_Remote_Tracklist::parse_node()
* new Hype Machine preset

= 0.9.8.4 =

* XSPF output: added title, author, timestamp, location and annotation nodes
* xspf urls: moved download argument at the end
* added rewrite rule for frontend wizard

= 0.9.8.3 =

* doc
* live playlists: when not displaying a single page, add a notice to load the tracklist
* scraper: cache only if several post are displayed (like an archive page)

= 0.9.8.2 =

* try to use parser without converting encoding
* fixed Spotify preset (now uses API)
* new API settings for Spotify
* post_tag taxonomy for albums & tracks
* frontend CSS fixes

= 0.9.8.1 =

* sort stations by health / trending / popular
* fixed ajaxed row actions for tracklist rows
* improved classes WPSSTM_Subtrack() and WPSSTM_Track()
* fix title comparaison check when updating artist/album/track
* fix Spiff plugin upgrade routine
* renamed Array2XML > WPSSTM_Array2XML
* new function WP_SoundSystem::is_admin_page()
* no CSS background for tracklist table

= 0.9.8 =

* show player column only if at least one track has sources
* tracks: auto guess ID if not defined
* improved presets + fixed Soundsgood preset
* header admin notice (review / donations)
* abord update_title_artist() and update_title_album() if value missing
* improved fill_post_with_mbdatas()
* improved presets suggestions in the wizard
* changed live tracks transient name

= 0.9.7 =

* Better track & album auto title
* Hide title input as we set it automatically - but keep the feature since it outputs the permalink and 'view' link
* no more options for the "Fill with datas" button from the MusicBrainz Metabox
* improved how posts are automatically filled with MusicBrainz data

= 0.9.6 =

* Changed how subtracks are stored: now we store an array of subtrack IDs in the tracklist post; while before we were saving the tracklist ID in each track.
* Hide subtracks' filter now works - still some work to do on this

= 0.9.5 =

* live playlists disabled by default
* ajax: reorder tracklist tracks with ajax only
* include scraper metabox even if live playlists are disabled
* fixed bug when deleting subtracks
* populate track sources only once
* Add title support for albums, artists & tracks (wasn't showing the 'View Post' or permalink on the post page)
* tracklists: add 'data-tracks-count' attribute
* WIP: audio player
* Show a play button in tracklist tables
* Embed a tracklist for single tracks too
* WIP: frontend scraper

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
