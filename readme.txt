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

= MusicBrainz =

When managing a track, artist or album, the plugin can search for its MusicBrainz ID.
It makes it easier to identify the items, and loads various metadatas from [MusicBrainz](https://musicbrainz.org/) (an open data music database).
For example, when creating an album post, you can load its tracklist from the MusicBrainz datas; so you don't need to enter each track manually.

= Playlists =
Create and manage playlists entries easily with the Tracklist metabox.  Each entry added creates a new Track post.
Import a tracklist from a file or a music service like Spotify using the Tracklist Parser metabox.
Export playlists in [XSPF](https://en.wikipedia.org/wiki/XML_Shareable_Playlist_Format) (XML Shareable Playlist Format).

= Live Playlists =
The Tracklist Parser metabox allows you to enter the URL of a remote tracklist (eg. a Last.FM profile or a radio station tracklist) and to scrape its data.
The tracklist will be synced to the remote page, and tracks will be updated each time someone access the Live Playlist post.

For popular services like Spotify, no need to go any further.

If the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.
This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

NB : unlike playlists and albums, the Live Playlists tracklist entries are not stored as Track posts but as a post meta, to avoid creating too much posts over and over.

= Donate! =
It truly took me a LOT of time to code this plugin.
If it saved you the time to backup manually a few hundred (or more!) pins, please consider converting this time into [a donation](http://bit.ly/gbreant).
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

== Screenshots ==

== Changelog ==

= 0.9 =
* First release

== Upgrade Notice ==

== Localization ==

