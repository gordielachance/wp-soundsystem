=== WP SoundSystem ===
Contributors:grosbouff
Donate link:http://bit.ly/gbreant
Tags: music,library,playlists,collection,artists,tracks,albums,MusicBrainz,xspf
Requires at least: 3.5
Tested up to: 4.7.3
Stable tag: trunk
License: GPLv2 or later

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  It's the perfect fit for your music blog !

== Description ==

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  It's the perfect fit for your music blog !

= Several new post types =
Tracks, artists, albums, playlists and live playlists uses each a custom post type, so you can easily manage them.

= MusicBrainz =
When managing a track, artist or album, the plugin can search for its MusicBrainz ID.
It makes it easier to identify the items, and loads various metadatas from [MusicBrainz](https://musicbrainz.org/) (an open data music database).
For example, when creating an album post, you can load its tracklist from the MusicBrainz datas; so you don't need to enter each track manually.

= Playlists =
Create and manage tracklists easily with the Tracklist metabox.  Each entry added creates a new Track post.

Import a tracklist from a file or a music service like Spotify using the Tracklist Parser metabox.
Export playlists in [XSPF](https://en.wikipedia.org/wiki/XML_Shareable_Playlist_Format) (XML Shareable Playlist Format).

= Live Playlists =
The Tracklist Parser metabox allows you to enter the URL of a remote tracklist (eg. a Last.FM profile or a radio station tracklist) and to scrape its data.
The tracklist will be synced to the remote page, and tracks will be updated each time someone access the Live Playlist post.

For popular services like Spotify or Radionomy, no need to go any further.
But if the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.
This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

NB : unlike playlists and albums, the Live Playlists tracklist entries are not stored as Track posts but as a post meta, to avoid creating too much posts over and over.

Native presets : Spotify, Radionomy, Last.FM, SomaFM, BBC, Slacker, Soundcloud, Twitter.

= Frontend Tracklist Parser =
You may propose a frontend tracklist parser to your visitors.  
They will be able to use it to get a remote tracklist; which could be useful for backups or to load it in a software like [Tomahawk](https://www.tomahawk-player.org/).
Just create a blank page and set its ID for the *Frontend wizard page ID* field in the plugin settings page.

= Shortcodes =

`[wpsstm-track post_id="150"]`

To embed the single track #150.
Optional arguments : *post_id*.

`[wpsstm-tracklist post_id="160"]`

To embed the tracklist from the post #160.  
Works for albums, playlists and live playlists.
Optional arguments : *post_id*,*max_rows*.


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

For feature request and bug reports, [please use the forums](http://wordpress.org/support/plugin/wp-soundsystem#postform).

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

== Screenshots ==

1. Settings page
2. Artists menu
3. Tracks menu
4. Albums menu
5. Playlists menu
6. Tracklist metabox
7. Tracklist parser metabox

== Changelog ==

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

