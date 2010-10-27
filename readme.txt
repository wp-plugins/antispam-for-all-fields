=== Antispam for all fields ===
Contributors: Ramon Fincken
Donate link: http://donate.ramonfincken.com
Tags: spam,antispam,phpbbantispam,anti-spam,wordpressantispam
Requires at least: 2.0.2
Tested up to: 3.0.1
Stable tag: 0.4

Plugin to reject spam. Port from same author from http://www.phpbbantispam.com
Actually visits the URL from commenter to spider for spamwords. Plugin does a lot more.

== Description ==

Plugin to reject spam. Port from same author from http://www.phpbbantispam.com <br>
Actually visits the URL from commenter to spider for spamwords. <br>
Plugin does a lot more such as:<br>
* Count for number of web-URI's in comment<br>
* Count on email, IP, URI compared with allready spammed comments<br>
* Future feature: Add hidden fields with random names

== Installation ==

1. Upload directory `antispam-for-all-fields` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

None available


== Changelog ==

= 0.4 =
Bugfix: plugin_antispam_for_all_fields_stats for spammed stats
Added: Check for number of websites in comment, if above 10 then spam comment

= 0.3 =
Bugfix: forgot to report status, fix that will run once is included.
Fix triggers when a new comment is submitted.
Added counter
Changed wordlist a bit

= 0.2 =
Implemented visit of URL of commenter to spider for spamwords.

= 0.1 =
First release


== Screenshots ==

None available yet