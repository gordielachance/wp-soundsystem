=== WP SoundSystem ===
Contributors: grosbouff
Donate link: https://api.spiff-radio.org?p=31
Tags: music,player,audio,tracklist,importer,stream,MusicBrainz,Spotify,XSPF,artists,albums,tracks,sources
Requires at least: 4.9
Tested up to: 5.1.1
Stable tag: trunk
License: GPLv2 or later

WP Soundsystem is a complete solution to manage a music library within WordPress.  Use it to build or import playlists, manage tracks and audio sources, albums, artists, and play them with our audio player.

== Description ==

*If you are updating the plugin from < version 2.5, please BACKUP your database first, as many, many things have been updated.*

*WP Soundsystem* is a complete solution to manage a music library within WordPress.

Use it to build or import playlists, manage tracks and audio sources, albums, artists, and play them with our audio player.

Several new post types will be available : Playlists, Radios, Artists, Albums, Tracks and Sources.

[See it working on Spiff Radio](https://www.spiff-radio.org/)

= Tracklists =

Creating and editing playlists is a piece of cake (not to mention the *Tracklist Importer*): 
Add or remove tracks on the fly, reorder them, favorite a track or a tracklist, export…

= Tracks =

When editing a track, you can query details from music services like [MusicBrainz](https://musicbrainz.org/) (The Open Music Encyclopedia) or Spotify. 
Audio sources can be linked to your tracks with the *Track Sources* metabox.

= Sources =

You can link several audio sources to any track. 
It can be a the path of an audio file, a Soundcloud or Youtube link, etc. 
The player will then try to play them.

= Radios =

Radios are how we call *live playlists*. 
Those playlists are synced with remote webpages or services, and are refreshed seamlessly after a short delay.

[Check some Radios on Spiff Radio](http://spiff-radio.org/?post_type=wpsstm_live_playlist)

= Tracklist Importer (requires an API key) =

Backup your playlists using the Tracklist Importer. 
Popular services (Spotify, Last.fm, Radionomy, Deezer, BBC, Soundcloud, Soundsgood, Hype Machine, Indie Shuffle, RadioKing,…) are available out-of-the-box, just by pasting a playlist link.
More advanced setups are also available, if you are somewhat familiar with [CSS selectors](https://www.w3schools.com/cssref/css_selectors.asp).

[Frontend Importer on Spiff Radio](https://www.spiff-radio.org/?p=213)

= Autosource (requires an API key) =

If you don’t link audio sources to your track manually, you can enable our *autosource* module. It will search for remote sources (Youtube, Soundcloud…) and attach them to your tracks.

= Last.fm =

The audio player can **scrobble** tracks to your Last.fm account.
When the scrobbler is enabled, every track favorited by a user connected on [Last.fm](https://www.last.fm/) will also be loved on that service.

= BuddyPress =

Users profiles will get a new music section that lists the user favorite tracks, tracklists, and the ones he created. 
It will also fire new *BuddyPress activity* items.

= Contribute =

WP Soundsystem is dev friendly. 
Wanna give a hand as developer ? Check the [Github](https://github.com/gordielachance/wp-soundsystem) repo.

= WP SoundSystem API =

Get more out of this plugin by [registering an API key](https://api.spiff-radio.org/?p=10); which will enable

* the Tracklist Importer
* the Autosource module
* the Radios post type

Those are optionals, but are nice features to a solid plugin.
Consider getting one as a nice way to support the work done – hundred of hours – , and to ensure its durability.

= Donate =
Whatever, if you like this plugin, please also consider [making a donation](https://api.spiff-radio.org/?p=31).
This would be very appreciated !

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

== How can I embed a track or a playlist in a post ? =

Using shortcodes:

`[wpsstm-track post_id="150"]`

`[wpsstm-tracklist post_id="160"]`

Or directly with PHP functions:

`<?php
$tracklist = new WPSSTM_Post_Tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_html();
?>`

= What is the community user ? =

The community user is a Wordpress user you need to create, and that will be assigned as author to the content created automatically by the plugin, for instance imported tracks or sources.

== Screenshots ==

1. Tracklist playing frontend
2. Radio (live) tracklist
3. Plugin settings page & menu
4. Tracklist Importer metabox
5. Track Sources metabox
6. Tracklists manager popup (when favoriting a track)
7. Frontend Tracklist Importer
8. Music menu on a BuddyPress profile

== Changelog ==

= 2.5.1 =
* So much improvements that they cannot even be listed !  More than one year of developpement and 754 commits !
* now compatible with the [Autoplay Policy Change](https://developers.google.com/web/updates/2017/09/autoplay-policy-changes) from Chrome
* Code entirely rewritten
* HTML : uses HTML5 custom elements
* REST API
* ...

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
