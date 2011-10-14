# Information

South Park Downloader to get South Park episodes from official sources.

# Features

 - Cross-platform support.
 - Full support for English and German episodes.
 - Ready for additional languages.
 - Renaming of episodes to contain title of each language.
 - Fix audio delay for certain acts/parts (e.g. S13E08A1).
 - XML file containing all information.
 - Checksum verification of downloaded parts.
 - Resuming of incomplete downloads (which occur starting with S15E07).

# Installation

## Requirements

 - PHP (>= 5.3) from http://php.net/downloads.php
 - RTMPDump from http://rtmpdump.mplayerhq.hu/download/
 - FFmpeg from http://ffmpeg.zeranoe.com/builds/
 - MKVToolnix from http://www.bunkus.org/videotools/mkvtoolnix/downloads.html

Or, if you are using Windows, just take the bundle of pre-packaged tools from https://github.com/craue/SouthParkDownloader/downloads.

## Configuration

 - Copy one of the `config-sample-*.php` files to `config.php`.
 - Edit `config.php` and set all the values to fit your environment.

# Usage

To download all episodes of season 15 in English:

	php download.php s=15 l=en

To download episode 8 of season 13 in German and English, while taking video from the German source and setting the default audio language to German:

	php download.php s=13 e=8 l=de+en
