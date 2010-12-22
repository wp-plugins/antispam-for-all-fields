=== Antispam for all fields ===
Contributors: Ramon Fincken
Donate link: http://donate.ramonfincken.com
Tags: spam,antispam,phpbbantispam,anti-spam,wordpressantispam
Requires at least: 2.0.2
Tested up to: 3.0.3
Stable tag: 0.6

Plugin to reject spam. Port from same author from http://www.phpbbantispam.com
Actually visits the URL from commenter to spider for spamwords.

== Description ==

Plugin to reject spam. Port from same author from http://www.phpbbantispam.com <br>
Actually visits the URL from commenter to spider for spamwords. <br>
Plugin does a lot more such as:<br>
* Count for number of web-URI's in comment<br>
* Count on email, IP, URI compared with allready spammed comments<br>
* Detailed information by email about the spammed comment. You can approve the comment later on, or blacklist the IP adres.<br>
* Future feature: Add hidden fields with random names

<br>
<br>Coding by: <a href="http://www.mijnpress.nl">MijnPress.nl</a> <a href="http://twitter.com/#!/ramonfincken">Twitter profile</a> <a href="http://wordpress.org/extend/plugins/profile/ramon-fincken">More plugins</a>


== Installation ==

1. Upload directory `antispam-for-all-fields` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I have a lot of questions and I want support where can I go? =

The support forums over here, drop me a tweet to notify me of your support topic over here.


== Changelog ==

= 0.6 =
Bugfix: Private function instead of protected, causing the wordlist to halt on error

= 0.5.2 =
Bugfix: Limit bug (array)..

= 0.5.1 =
Bugfix: Random nonce was given multiple times

= 0.5 =
Bugfix: Counter<br>
Added: GUI, you can set thresholds and edit/add/delete spamwords to search for<br>
Added: Mail with more details<br>
Changed: Core file and admin_menu file<br>
Added: Store comment for 7 days, email contains a link to approve comment or blacklist the IP adres<br>

= 0.4 =
Bugfix: plugin_antispam_for_all_fields_stats for spammed stats<br>
Added: Check for number of websites in comment, if above 10 then spam comment

= 0.3 =
Bugfix: forgot to report status, fix that will run once is included.<br>
Fix triggers when a new comment is submitted.<br>
Added counter<br>
Changed wordlist a bit

= 0.2 =
Implemented visit of URL of commenter to spider for spamwords.

= 0.1 =
First release


== Screenshots ==

1. Settings admin GUI
<a href="http://s.wordpress.org/extend/plugins/antispam-for-all-fields/screenshot-1.png">Fullscreen Screenshot 1</a><br>

2. Email notification
<a href="http://s.wordpress.org/extend/plugins/antispam-for-all-fields/screenshot-2.png">Fullscreen Screenshot 2</a><br>