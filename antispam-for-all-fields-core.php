<?php
/**
 Antispam for all fields core file
 @author: Ramon Fincken http://www.mijnpress.nl
 */

if (!defined('ABSPATH')) die("Aren't you supposed to come here via WP-Admin?");

if(!class_exists('mijnpress_plugin_framework'))
{
	include('mijnpress_plugin_framework.php');
}

class antispam_for_all_fields_core extends mijnpress_plugin_framework
{

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
	protected function get_words() {
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
	 * Adds IP to WP blacklist 
	 * @param string $ip
	 */
	protected function blacklist_ip($ip) {
		$blacklist_keys = trim(stripslashes(get_option('blacklist_keys')));
		$blacklist_keys_update = $blacklist_keys."\n".$ip;
		update_option('blacklist_keys', $blacklist_keys_update);
	}
	
	
	/**
	 * Notifies admin or custom inserted replacement
	 */
	protected function mail_details($subject, $mailbody,$commment_key) {
		if(!$this->mail['sent']) return;
		
		if(empty($this->mail['admin']) || $this->mail['admin'] == 'default')
		{
			$email_to = get_option('admin_email');
		}
		else
		{
			$email_to = $this->mail['admin'];
		}
		
		$blogname = get_option('blogname');

		$body ="This comment is stored for ".$this->store_comment_in_days. " days.\n";
		$body .= sprintf( __('Approve it: %s'), admin_url($this->plugin_config_url."&action=approve&comment_key=$commment_key") ) . "\r\n";
		$body .= sprintf( __('Blacklist IP: %s'), admin_url($this->plugin_config_url."&action=blacklist_ip&ip=".$this->user_ip) ) . "\r\n\r\n";
		
		$body .= $mailbody;
		
		$body .= "\n\nAntispam for all fields by Ramon Fincken\nhttp://wordpress.org/extend/plugins/profile/ramon-fincken";
		wp_mail($email_to, '[' . $blogname . '][Antispam] ' . $subject . ' ' . date('r'), $body);
	}

	/**
	 * 
	 * Stores comment for x days in a transient
	 * @param array $commentdata
	 * @return	string	comment_store_key
	 */
	protected function store_comment($commentdata)
	{
		$expiration = 60*60*24*$this->store_comment_in_days;
		
		$random = wp_create_nonce('plugin_afaf');
		$key = 'plugin_afaf_'.md5(time().$random);
		
		set_transient($key, $commentdata, $expiration);
		
		return $key;
	}
	
	/**
	 *
	 * Updates counter for stats
	 * @param void
	 */
	private function update_stats($type = 'killed')
	{
		if($type == 'killed')
		{
			$type = 'plugin_antispam_for_all_fields_statskilled';
		}
		else
		{
			$type = 'plugin_antispam_for_all_fields_statsspammed';
		}

		// Get current count
		$temp = get_option($type);

		// Update counter with 1
		update_option($type, intval($temp) + 1);
		unset($temp);
	}

	/**
	 * Checks if this value has been marked as spam before
	 */
	protected function compare_counts($count, $field, $commentdata) {
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

			
			$commment_key = $this->store_comment($commentdata,'spammed');
			$this->mail_details('rejected comment', $body,$commment_key);
			$this->store_comment($commentdata,'killed');
			$this->update_stats('killed');
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

				$commment_key = $this->store_comment($commentdata,'spammed');
				$this->mail_details('spammed comment', $body,$commment_key);
				$this->update_stats('spammed');
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
			$wpdb->get_results($sql);
			update_option('plugin_antispam_for_all_fields_v02fix', 'yes');
		}
		// Bugfix for v 0.2
	}

	// ---------------- SYNTAX TEST FUNCTIONS
	/**
	* Counts occurences of webadresses (including [url])
	*/
	protected function count_number_of_sites($txt) {
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

	// ---------------- SYNTAX TEST FUNCTIONS

	// ---------------- CHANGE STRING FUNCTIONS
	/**
	* L33t filter :)
	*/
	protected function change_txt($txt, $mode) {
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
	protected function strip_doublechars($txt) {
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
	protected function check_count($field, $value) {
		global $wpdb;

		$sql = 'SELECT COUNT(`' . $field . '`) AS numberofrows FROM ' . $wpdb->comments . ' WHERE `' . $field . '` = %s AND comment_approved =%s';
		$preparedsql = $wpdb->prepare($sql, $value, $this->wpdb_spam_status);
		$results = $wpdb->get_results($preparedsql, ARRAY_A);
		return $results[0]['numberofrows'];
	}
}
?>