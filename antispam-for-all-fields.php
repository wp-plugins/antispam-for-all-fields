<?php
/*
Plugin Name: Antispam for all fields
Plugin URI: http://www.mijnpress.nl
Description: Class and functions
Author: Ramon Fincken
Version: 0.4
Author URI: http://www.mijnpress.nl
*/

add_filter('pre_comment_approved', 'plugin_antispam_for_all_fields', 0);

// Disabled due to sessions, I want to store it otherwise ( I know that session_start() is an option )
// add_action ('comment_form', 'plugin_antispam_for_all_fields_insertfields');
// add_action ('comment_post', 'plugin_antispam_for_all_fields_checkfields');

add_action('activity_box_end', 'plugin_antispam_for_all_fields_stats');

define('PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION', '0.4');

function plugin_antispam_for_all_fields_checkfields()
{
	$pass = false;
	if(wp_verify_nonce($_POST[$_SESSION['plugin_afaf_nonce1']], 'plugin_afaf1') )
	{
		// Found first nonce, and was correct
		$nonce2 = $_POST[$_SESSION['plugin_afaf_nonce1']];
		if(!empty($nonce2) && $nonce2 == $_SESSION['plugin_afaf_nonce2'])
		{
			// Was correct
			if(isset($_POST[$nonce2]) && empty($_POST[$nonce2]))
			{
				$pass = true;
			}
		}
	}
}

function plugin_antispam_for_all_fields_insertfields()
{
	$nonce1= wp_create_nonce('plugin_afaf1');
	$_SESSION['plugin_afaf_nonce1'] = $nonce1;
	
	set_transient('plugin_afaf_nonce1', $nonce1, 60*60); // 60*60 = 1hour
	
	
	$nonce2= wp_create_nonce('plugin_afaf2');
	$_SESSION['plugin_afaf_nonce2'] = $nonce2;
	echo '<input type="hidden" name="'.$nonce1.'" value="'.$nonce2.'" />';
	echo '<input type="hidden" name="'.$nonce2.'" value="" />';
}

/**
 * Displays stats in dashboard
 */
function plugin_antispam_for_all_fields_stats() {
	$statskilled = intval(get_option('plugin_antispam_for_all_fields_statskilled'));
	$statsspammed = intval(get_option('plugin_antispam_for_all_fields_statsspammed'));

	echo '<p>' . sprintf(__('<a href="%1$s" target="_blank">Antispam for all fields</a> has blocked <strong>%2$s</strong> and spammed <strong>%3$s</strong> comments.'), 'http://wordpress.org/extend/plugins/antispam-for-all-fields/', number_format($statskilled), number_format($statsspammed)) . '</p>';
}

function plugin_antispam_for_all_fields($status) {
	global $commentdata;
	$afaf = new antispam_for_all_fields();
	$afaf->do_bugfix();
	return $afaf->init($status, $commentdata);
}

/**
 * Class, based on my PhpBB2 antispam for all fields module: http://www.phpbbantispam.com
 * @author Ramon Fincken
 */
class antispam_for_all_fields {
	/**
	 * Core function to init spamchecks
	 */
	function init($status, $commentdata) {
		if ($commentdata['comment_type'] == 'trackback' || $commentdata['comment_type'] == 'pingback') {
			return 0;
		}
		$this->lower_limit = 2;
		$this->upper_limit = 10;
		$this->wpdb_spam_status = 'spam';
		$this->user_ip = htmlspecialchars(preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR']));
		$this->user_ip_fwd = htmlspecialchars(preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['HTTP_X_FORWARDED_FOR'])); // For future use

		$email = $commentdata['comment_author_email'];
		$author = $commentdata['comment_author'];
		$url = $commentdata['comment_author_url'];
		$comment_content = $commentdata['comment_content'];
		$words = $this->get_words();

		if (!empty ($email)) {
			$count = $this->check_count('comment_author_email', $email);
			$temp = $this->compare_counts($count, 'comment_author_email', $commentdata);
			if ($temp) {
				return $temp;
			}
		}
		if (!empty ($author)) {
			$count = $this->check_count('comment_author', $author);
			$temp = $this->compare_counts($count, 'comment_author', $commentdata);
			if ($temp) {
				return $temp;
			}
		}

		if (!empty ($url)) {
			$count = $this->check_count('comment_author_url', $url);
			$temp = $this->compare_counts($count, 'comment_author_url', $commentdata);
			if ($temp) {
				return $temp;
			}

			// Now check for words
			if ($html_body = wp_remote_retrieve_body(wp_remote_get($url))) {
				if (!empty ($html_body)) {
					foreach ($words as $word) {
						$string_is_spam = $this->string_is_spam($word, $html_body);
						if ($string_is_spam) {
							$body = "Details are below: \n";
							$body .= "action: found spamword in URL of commenter, comment denied \n";

							$body .= "IP adress " . $this->user_ip . "\n";
							$body .= "low threshold " . $this->lower_limit . "\n";
							$body .= "upper threshold " . $this->upper_limit . "\n";

							$body .= "word found  : " . $word . " \n\n";

							foreach ($commentdata as $key => $val) {
								$body .= "$key : $val \n";
							}

							$this->mail_details('rejected comment based on word', $body);

							Header('HTTP/1.1 403 Forbidden');
							echo $word;
							die('spam');
						}
					}
				}
			}
		}
		if (!empty ($comment_content)) {
			// 
			$number_of_sites = $this->count_number_of_sites($comment_content);
			if($number_of_sites > 10)
			{
				$body = "Details are below: \n";
				$body .= "action: found ".$number_of_sites. " URIs in comment that is a lot, comment marked as spam \n";

				$body .= "IP adress " . $this->user_ip . "\n";
				$body .= "low threshold " . $this->lower_limit . "\n";
				$body .= "upper threshold " . $this->upper_limit . "\n";

				foreach ($commentdata as $key => $val) {
					$body .= "$key : $val \n";
				}
				
				$this->mail_details('rejected spammed based on '.$number_of_sites. ' URIs in comment', $body);
				$temp = get_option('plugin_antispam_for_all_fields_statsspammed');
				update_option('plugin_antispam_for_all_fields_statsspammed', intval($temp) + 1);			
				return 'spam';
			}
			
			foreach ($words as $word) {
				$string_is_spam = $this->string_is_spam($word, $comment_content);
				if ($string_is_spam) {

					$body = "Details are below: \n";
					$body .= "action: found spamword in comment, comment denied \n";

					$body .= "IP adress " . $this->user_ip . "\n";
					$body .= "low threshold " . $this->lower_limit . "\n";
					$body .= "upper threshold " . $this->upper_limit . "\n";

					$body .= "word found  : " . $word . " \n\n";

					foreach ($commentdata as $key => $val) {
						$body .= "$key : $val \n";
					}

					$this->mail_details('rejected comment based on word', $body);

					Header('HTTP/1.1 403 Forbidden');
					echo $word;
					die('spam');

				}
			}
		}

		// IP check
		$count = $this->check_count('comment_author_IP', $this->user_ip);
		$temp = $this->compare_counts($count, 'comment_author_IP', $commentdata);
		if ($temp) {
			return $temp;
		}

		return 0;
	}

	/**
	 * Checks if regex is applicable for this word in a string
	 */
	private function string_is_spam($stringtotest, $spamword) {
		$spamstatus = false;
		if (preg_match("#\b(" . str_replace("\*", ".*?", preg_quote($stringtotest, '#')) . ")\b#i", $spamword)) {
			$spamstatus = true;
		}
		return $spamstatus;
	}

	/**
	 * Returns an array of words
	 * TODO: DB table
	 */
	private function get_words() {
		$words = array (
			'*.ru*',
				// '*sex*', positive for sex and the city
	'*pharmac*',
				// '*CIALIS*', positive for gespecialiseerd
	'*viagra*',
			'*mortgage*',
			'*drug*',
			'*blogspot.com*',
			'*casino*',
			'*rumer*',
			'*porn*',
			'*diet*',
			'*tramad*',
			'*credit*',
			'*invest*',
			'*adult',
			'*pharm*',
			'*free-*',
			'*pill*',
			'*.by*',
			'*-and*',
			'*-video*',
			'*poker*',
			'*t35*',
			'*games.*',
			'*meds*',
			'*spam.*',
			'*squidoo*',
			'*rdto*',
			'*-buy*',
			'*PHENTERMINE*',
			'*bitch*',
			'*penis*',
			'*fuck*',
			'*asian*',
			'*romot*',
			'*shippin*',
			'*nude*',
			'*gay*',
			'*wares*',
			'*gambl*'
		);
		return $words;
	}

	/**
	 * Notifies admin or custom inserted replacement > get_option('plugin_antispamfaf_emaillog')
	 * TODO: Make admin option page
	 */
	private function mail_details($subject, $body) {
		$admin_email = get_option('admin_email');
		$log_email = get_option('plugin_antispamfaf_emaillog');
		$blogname = get_option('blogname');
		if (isset ($log_email) && !empty ($log_email)) {
			$email_to = $log_email;
		} else {
			$email_to = $admin_email;
		}
		$body .= "\n\nAntispam for all fields by Ramon Fincken\nhttp://wordpress.org/extend/plugins/profile/ramon-fincken";
		wp_mail($email_to, '[' . $blogname . '][Antispam] ' . $subject . ' ' . date('r'), $body);
	}

	/**
	 * Checks if this value has been marked as spam before
	 */
	private function compare_counts($count, $field, $commentdata) {
		if ($count > $this->upper_limit) {
			$body = "Details are below: \n";
			$body .= "action: exceeded upper threshold, comment denied \n";

			$body .= "IP adress " . $this->user_ip . "\n";
			$body .= "low threshold " . $this->lower_limit . "\n";
			$body .= "upper threshold " . $this->upper_limit . "\n";

			$body .= "number of simular comments for field ($field) : " . $count . " times\n\n";
			foreach ($commentdata as $key => $val) {
				$body .= "$key : $val \n";
			}

			$this->mail_details('rejected comment', $body);
			$temp = get_option('plugin_antispam_for_all_fields_statskilled');
			update_option('plugin_antispam_for_all_fields_statskilled', intval($temp) + 1);
			Header('HTTP/1.1 403 Forbidden');
			die('spam');
		} else {
			if ($count > $this->lower_limit) {
				$body = "Details are below: \n";
				$body .= "action: exceeded lower threshold, comment marked as spam \n";

				$body .= "IP adress " . $this->user_ip . "\n";
				$body .= "low threshold " . $this->lower_limit . "\n";
				$body .= "upper threshold " . $this->upper_limit . "\n";

				$body .= "number of simular comments for field ($field) : " . $count . " times\n\n";
				foreach ($commentdata as $key => $val) {
					$body .= "$key : $val \n";
				}

				$this->mail_details('spammed comment', $body);

				$temp = get_option('plugin_antispam_for_all_fields_statsspammed');
				update_option('plugin_antispam_for_all_fields_statsspammed', intval($temp) + 1);
				return 'spam';
			}
		}
		return false;
	}

	/**
	 * Performs bugfix
	 */
	function do_bugfix() {
		// Bugfix for v 0.2
		if ((PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION == '0.3' || PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION == '0.4') && get_option('plugin_antispam_for_all_fields_v02fix') != 'yes') {
			global $wpdb;

			$sql = 'UPDATE ' . $wpdb->comments . ' SET comment_approved = 0 WHERE comment_approved = \'\'';
			$wpdb->get_results($sql, ARRAY_A);
			update_option('plugin_antispam_for_all_fields_v02fix', 'yes');
		}
		// Bugfix for v 0.2
	}

	// ---------------- SYNTAX TEST FUNCTIONS
	/**
	 * Counts occurences of webadresses (including [url])
	 */
	private function count_number_of_sites($txt) {
		// 1.2.9
		// http://phpbbantispam.com/viewtopic.php?t=129
		// [url=somesites.nw]Some site[/url] becomes http://somesites.nw https://somesites.nw Some site
		$txt = preg_replace('/\[url=([^http](.+))\](.+)\[\/(.+)\]/', "http://$1 https://$1 $3", $txt);

		// 1.2.7 Check max websites
		// Partially re-coded from : http://www.phpbb.com/community/viewtopic.php?f=16&t=360188&start=30&st=0&sk=t&sd=a   
		$out = array ();
		preg_match_all("/http:\/\/|ftp:\/\/|[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}/si", $txt, $out, PREG_SET_ORDER);
		// Removed |www
		$number = count($out);
		return $number;
	}

	function website_syntax_ok($url) {
		$url = strtolower($url);
		if (empty ($url))
			return false;
		if (!preg_match('#^http[s]?\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i', $url)) {
			return false;
		}
		$pattern = '/\[url/';
		preg_match($pattern, $url, $matches, PREG_OFFSET_CAPTURE);
		if (count($matches) > 0) {
			return false;
		}
		return true;
	}

	function mail_syntax_ok($mail) {
		if (empty ($mail))
			return false;
		@ list ($local, $host) = explode("@", $mail);
		$pattern_local = "^([0-9a-z]*([-|_]?[0-9a-z]+)*)(([-|_]?)\.([-|_]?)[0-9a-z]*([-|_]?[0-9a-z]+)+)*([-|_]?)$";
		$pattern_host = "^([0-9a-z]+([-]?[0-9a-z]+)*)(([-]?)\.([-]?)[0-9a-z]*([-]?[0-9a-z]+)+)*\.[a-z]{2,4}$";
		$match_local = eregi($pattern_local, $local);
		$match_host = eregi($pattern_host, $host);

		if ($match_local && $match_host) {
			return true;
		} else {
			return false;
		}
	}
	// ---------------- SYNTAX TEST FUNCTIONS

	// ---------------- CHANGE STRING FUNCTIONS
	/**
	 * L33t filter :)
	 */
	private function change_txt($txt, $mode) {
		// 1.1.2, 1.1.3
		switch ($mode) {
			case 1 :
				// [url=http://www.badurl.com]Click[/url]
				$txt = preg_replace("/\[url=(\W?)(.*?)(\W?)\](.*?)\[\/url\]/", "$2" . " " . "$4", $txt);

				// [url]http://www.badurl.com[/url]
				$txt = preg_replace("/\[url\](.*?)\[\/url\]/", "$1", $txt);

				// [b ][/b ]
				$txt = preg_replace('/\[.+\]\[\/.+\]/', '', $txt);

				// 1.1.3
				// [size=0]hidden_txt[/size]
				$txt = preg_replace("/\[size=0\:(.*?)\](.*?)\[\/size\:(.*?)\]/", "$4", $txt);
				// [size=-{int}]really small txt[/size]
				$txt = preg_replace("/\[size=-(.*?)\:(.*?)\](.*?)\[\/size\:(.*?)\]/", "$5", $txt);

				// Soften the txt for the algoritm is too strong..
				// you can also -> anal  ? I'll -> pill
				// ggg@yahoo.com -> g@y ...
				// 1.2.7 Thanks WebSnail! http://www.phpbbantispam.com/viewtopic.php?t=75
				$search = array (
					'can always',
					'can allow',
					'can also',
					' except',
					' example',
					'? I',
					'? i',
					'casino mod',
					'casino hack',
					'@yahoo.com',
					'http://www.google-analytics.com',
					'https://www.google-analytics.com',
					'google-analytics.com/ga.js',

					
				);
				$replace = array (
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					''
				);
				$txt = str_replace($search, $replace, $txt);
				break;
			case 2 :
				// 1.1.2
				$search = array (
					'4',
					'@',
					'$',
					'8',
					'3',
					'!',
					'1',
					'0',
					'?',
					'7'
				);
				$replace = array (
					'a',
					'a',
					's',
					'b',
					'e',
					'i',
					'i',
					'o',
					'p',
					't'
				);
				$txt = str_replace($search, $replace, $txt);
				break;
			case 3 :
				//    & goes to aamp;
				$search = array (
					'.',
					',',
					' ',
					'_',
					':',
					'[',
					']',
					'|',
					'\\',
					'/',
					'&',
					'aamp;'
				);
				$replace = array (
					'',
					'',
					'',
					'',
					'',
					'i',
					'i',
					'i',
					'i',
					'i',
					'a',
					'a'
				);
				$txt = str_replace($search, $replace, $txt);
				break;
			case 4 :
				// 1.2.6
				// Remove double chars
				$txt = strip_doublechars($txt);
				break;
			case 5 :
				// [size=1]small_txt[/size]
				$txt = preg_replace("/\[size=1\:(.*?)\](.*?)\[\/size\:(.*?)\]/", "$4", $txt);
				// [{}][/{}]
				$txt = preg_replace("/\[(.*?)\]\[(.*?)\]/", "$5", $txt);

				$txt = ereg_replace("[^a-zA-Z0-9]", "", $txt);
				break;
			default :
				break;
		}
		return $txt;
	}

	/**
	 * Strips double chars
	 * Partial/rewrote code from: Forum Assassin, Dom Walenczak http://spam.wulfslair.com/ (Cybertronian Alliance Corp)
	 */
	private function strip_doublechars($txt) {
		/* Partial/rewrote code from: Forum Assassin, Dom Walenczak http://spam.wulfslair.com/ (Cybertronian Alliance Corp) */

		$txt_stripped = '';
		$last_character = '';
		for ($i = 0; $i < strlen($txt); $i++) {
			if ($txt[$i] != $last_character) {
				$txt_stripped .= $txt[$i];
			}
			$last_character = $txt[$i];
		}
		return $txt_stripped;
	}
	// ---------------- CHANGE STRING FUNCTIONS

	/**
	 * Returns internal SQL results
	 */
	private function check_count($field, $value) {
		global $wpdb;

		$sql = 'SELECT COUNT(`' . $field . '`) AS numberofrows FROM ' . $wpdb->comments . ' WHERE `' . $field . '` = %s AND comment_approved =%s';
		$preparedsql = $wpdb->prepare($sql, $value, $this->wpdb_spam_status);
		$results = $wpdb->get_results($preparedsql, ARRAY_A);
		return $results[0]['numberofrows'];
	}
}
?>