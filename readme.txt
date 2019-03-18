=== WP SoundSystem ===
Contributors: grosbouff
Donate link: http://bit.ly/gbreant
Tags: music,library,playlists,collection,artists,tracks,albums,MusicBrainz,xspf
Requires at least: 4.9
Tested up to: 5.1
Stable tag: trunk
License: GPLv2 or later

Manage a music library within Wordpress ! Build playlists, manage tracks, import/backup remote playlists from various services, and keep it in sync.  The perfect fit for your music blog !

== Description ==

Manage a music library within Wordpress ! Build playlists, manage tracks, import/backup remote playlists from various services, and keep it in sync.  The perfect fit for your music blog !

= Several new post types =

Playlists, Radios, Tracks and Sources each uses a custom post type, so you can easily manage/extend them.

= Playlists =

Managing the playlist tracks is a piece of cake using the *Tracklist metabox*:
Add or remove tracks on the fly, reorder them, and link one or several music sources to each track.

Import a tracklist from a file or a music service like Spotify using the *Remote Tracklist Wizard* (see below).

= Audio player =

When viewing a post that contains a tracklist, an audio player will show up to play your tracks !

**Supported sources**: Youtube, Soundcloud, regular audio files.

= Sources =

If you didn't set sources for your tracks (see below) and that the **autosource** option is enabled; the audio player will try to find an online source automatically (Youtube, Soundcloud, ...) based on the track informations.

Those links will be used by the audio player (see above) to play the track if the source URL is supported.

= Remote Tracklist Wizard Metabox =

Enter the URL of a tracklist (eg. a local XSPF file, a Spotify Playlist, a radio station page...) to scrape its data.

Popular services like Spotify or Radionomy are automated through presets; and you will not need to do anything to retrieve their tracklists.

But if the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.

This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

**Native presets**: Last.fm, Spotify, Radionomy, Deezer, SomaFM, BBC, Slacker, Soundcloud, Twitter, Soundsgood, Hype Machine, Reddit, Indie Shuffle, RadioKing, Online Radio Box.

You may also propose a **Frontend Tracklist Importer** to your visitors: just create a blank page and set its ID for the *Frontend wizard page ID* field in the plugin settings page.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?p=213).

= Radios =

Radios lets you grab a tracklist from a remote URL and remains **synchronised** with it.  
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
Works for albums, playlists and radios.
Optional arguments: *post_id*,*max_rows*.

= BuddyPress =

This plugin is BuddyPress ready, and supports a new "music" menu for users, activity, etc.

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

= How can I display the tracklist of a post in my templates ? =

Use the tracklist shortcode **[wpsstm-tracklist]** in your post content (see the *shortcodes* section above), or use those functions directly in your templates:

`<?php
$tracklist = new WPSSTM_Post_Tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_html();
?>`

= What are community tracks and when are they created ? =

Community tracks are tracks that are automatically created by the plugin and for which the author is the community user (see settings).
They are created when a live tracklist is updated; only if its cache is disabled/expired.
A community track is also created when we autosource a track; so the sources query is ran only once.

There is an option in the plugin settings to flush those community tracks : they will be deleted; but only if they do not appear in a tracklist and are not favorited by any users.

== Screenshots ==

1. Settings page
2. Tracks menu
3. Playlists menu
4. Tracklist metabox
5. Tracklist parser metabox
6. Music sources metabox

== Changelog ==

= 2.0.0 =
* now compatible with the [Autoplay Policy Change](https://developers.google.com/web/updates/2017/09/autoplay-policy-changes) from Chrome
* tracklists loaded as iframes (faster and better for styling)
* loved tracks are now regular subtracks, part of a simple tracklist that is created for each user
* Improved autosource
* Improved wizard
* Improved templates
* Better tracks source GUI
* Spotify metabox
* PHP : pass tracklist object  to the track object / pass track object to the source object
* merged all playlists classes; and new class WPSSTM_Remote_Tracklist
* uses HTML5 custom elements
* removed Artist & Album post types

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
* improved 'services' wizard widget
* new plugin option 'Flush Community Tracks'
* radios : get_the_title() now returns the remote tracklist title if WP post title is empty ('the_cached_remote_title' hooked on 'the_title')
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

== Upgrade Notice ==

== Localization ==
