<?php
/**
 Plugin Name: Antispam for all fields
 Plugin URI: http://www.mijnpress.nl
 Description: Class and functions
 Author: Ramon Fincken
 Version: 0.5.1
 Author URI: http://www.mijnpress.nl
 */

if (!defined('ABSPATH')) die("Aren't you supposed to come here via WP-Admin?");

if(!class_exists('mijnpress_plugin_framework'))
{
	include('mijnpress_plugin_framework.php');
}

define('PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION', '0.5');

if(!class_exists('antispam_for_all_fields_core'))
{
	include('antispam-for-all-fields-core.php');
}


// Calls core function after a comments has been submit
add_filter('pre_comment_approved', 'plugin_antispam_for_all_fields', 0);

// Shows statistics
add_action('activity_box_end', 'plugin_antispam_for_all_fields_stats');

// Disabled due to sessions, I want to store it otherwise ( I know that session_start() is an option )
// add_action ('comment_form', 'plugin_antispam_for_all_fields_insertfields');
// add_action ('comment_post', 'plugin_antispam_for_all_fields_checkfields');



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

/**
 * Calls core function to perform checks
 * @param unknown_type $status
 */
function plugin_antispam_for_all_fields($status) {
	global $commentdata;
	$afaf = new antispam_for_all_fields();
	$afaf->do_bugfix();
	return $afaf->init($status, $commentdata);
}

// Admin only
if(mijnpress_plugin_framework::is_admin())
{
	add_action('admin_menu',  array('antispam_for_all_fields', 'addPluginSubMenu'));
	add_filter('plugin_row_meta',array('antispam_for_all_fields', 'addPluginContent'), 10, 2);
}


/**
 * Class, based on my PhpBB2 antispam for all fields module: http://www.phpbbantispam.com
 * @author Ramon Fincken
 */
class antispam_for_all_fields extends antispam_for_all_fields_core
{
	function __construct()
	{
		$this->showcredits = true;
		$this->showcredits_fordevelopers = true;
		$this->plugin_title = 'Antispam for all fields';
		$this->plugin_class = 'antispam_for_all_fields';
		$this->plugin_filename = 'antispam-for-all-fields/antispam-for-all-fields.php';
		$this->plugin_config_url = 'plugins.php?page='.$this->plugin_filename;
		
		$this->language = array(); // TODO make seperate file
		$this->language['explain'] = 'Your request has been blocked by our antispam system. <br/>Site administration has been notified and will approve your comment after review.<br/>Do not re-submit your comment!';

		// Defaults
		$this->wpdb_spam_status = 'spam';
		$this->store_comment_in_days = 7;
		
		// Defaults, falltrough by admin panel settings
		$this->limits['lower'] = 2;
		$this->limits['upper'] = 10;
		$this->limits['numbersites'] = 10;
		$this->mail['sent'] = true;
		$this->mail['admin'] = ''; // '' == 'default' and will use admin_email. Values:  '' || 'default' || 'e@mail.com'		
		
		$installed = get_option('plugin_antispam_for_all_fields_installed');
		if($installed == 'true')
		{
			// Get config
			$settings = get_option('plugin_antispam_for_all_fields_settings');
			$this->limits = $settings['limits'];
			$this->mail = $settings['mail'];
			$this->words = $settings['words'];
			
			// Upgrade?
			$version = get_option('plugin_antispam_for_all_fields_version');
			// TODO : compare with PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION and perform upgrades
		}
		else
		{
			// Make install
			add_option('plugin_antispam_for_all_fields_installed','true');
			add_option('plugin_antispam_for_all_fields_version',PLUGIN_ANTISPAM_FOR_ALL_FIELDS_VERSION);
			
			$settings = array();
			$settings['words'] = $this->get_words();
			$settings['mail'] = $this->mail;
			$settings['limits'] = $this->limits;
			// Save default options
			add_option('plugin_antispam_for_all_fields_settings',$settings);
						
			// Store
			$this->words = $settings['words'];
		}
		
		$this->user_ip = htmlspecialchars(preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR']));
		$this->user_ip_fwd = htmlspecialchars(preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['HTTP_X_FORWARDED_FOR'])); // For future use		
	}

	function antispam_for_all_fields()
	{
		$args= func_get_args();
		call_user_func_array
		(
		array(&$this, '__construct'),
		$args
		);
	}

	function addPluginSubMenu()
	{
		$plugin = new antispam_for_all_fields();
		parent::addPluginSubMenu($plugin->plugin_title,array($plugin->plugin_class, 'admin_menu'),__FILE__);
	}

	/**
	 * Additional links on the plugin page
	 */
	function addPluginContent($links, $file) {
		$plugin = new antispam_for_all_fields();
		$links = parent::addPluginContent($plugin->plugin_filename,$links,$file,$plugin->plugin_config_url);
		return $links;
	}

	/**
	 * Shows the admin plugin page
	 */
	public function admin_menu()
	{
		$plugin = new antispam_for_all_fields();
		$plugin->content_start();
	
		// Handle submit here
		if(isset($_POST['action']) && $_POST['action'] == 'afal_update')
		{
			$temp = $_POST['words'];
			$_POST['words'] =explode("\n",$temp);
			
			if($_POST['mail']['sent'] == 1) { $_POST['mail']['sent'] = true; } else { $_POST['mail']['sent'] = false; }
			
			$settings_post = array();
			$settings_post['words'] = $_POST['words'];
			$settings_post['mail'] = $_POST['mail'];
			$settings_post['limits'] = $_POST['limits'];

			// Append POST values
			$settings = $settings_post;
			
			// Update
			update_option('plugin_antispam_for_all_fields_settings',$settings);
			
			// Reload settings
			$plugin = new antispam_for_all_fields();
		}
		
		switch (@$_GET['action'])
		{
			case 'approve':
				if(isset($_GET['comment_key']))
				{
					$comment_key = $_GET['comment_key'];
					$commentdata = get_transient($comment_key);
					
					if($commentdata === false)
					{
						$plugin->show_message('Could not find stored comment.<br/>Did you approve this one earlier on? If not .. must have been here more then '.$plugin->store_comment_in_days. ' days and was auto deleted.');
					}
					else
					{
						// Now insert
						wp_insert_comment($commentdata);
						$plugin->show_message('Comment approved');
						
						// Delete
						delete_transient($comment_key);
					}
				}				
				break;
			case 'blacklist_ip':
				if(isset($_GET['ip']))
				{
					$ip = trim(stripslashes($_GET['ip']));
					
					// Ereg code from wp-spamfree
					if (ereg("^([0-9]|[0-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])\.([0-9]|[0-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])\.([0-9]|[0-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])\.([0-9]|[0-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])$",$ip)) 
					{
						$plugin->blacklist_ip($ip);
						$plugin->show_message('IP blacklisted');
						
						// TODO ? delete or spam comment
						//$plugin->show_message('Comment deleted');
					}					
				}
				
				break;
			
			default:
				echo '<h1>Antispam for all fields settings</h1>';
				echo '<p>Layout is not prio number 1 right now, but everything is working</p>';
				include('admin_menu.php');
			break;
		}
				
		
		$plugin->content_end();
	}

	/**
	 * Core function to init spamchecks
	 */
	function init($status, $commentdata) {
		if ($commentdata['comment_type'] == 'trackback' || $commentdata['comment_type'] == 'pingback') {
			return 0;
		}

		$email = $commentdata['comment_author_email'];
		$author = $commentdata['comment_author'];
		$url = $commentdata['comment_author_url'];
		$comment_content = $commentdata['comment_content'];

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

		if (!empty ($comment_content)) {
			//
			$number_of_sites = $this->count_number_of_sites($comment_content);
			if($number_of_sites > $this->limits['numbersites'])
			{
				$body = "Details are below: \n";
				$body .= "action: found ".$number_of_sites. " URIs in comment that is a lot, comment marked as spam \n";

				$body .= "IP adress " . $this->user_ip . "\n";
				$body .= "low threshold " . $this->limits['lower'] . "\n";
				$body .= "upper threshold " . $this->limits['upper'] . "\n";

				foreach ($commentdata as $key => $val) {
					$body .= "$key : $val \n";
				}

				$commment_key = $this->store_comment($commentdata,'spammed');
				$this->mail_details('rejected spammed based on '.$number_of_sites. ' URIs in comment', $body,$commment_key);
				$this->update_stats('spammed');
				return 'spam';
			}
				
			foreach ($this->words as $word) {
				$string_is_spam = $this->string_is_spam($word, $comment_content);
				if ($string_is_spam) {

					$body = "Details are below: \n";
					$body .= "action: found spamword in comment, comment denied \n";

					$body .= "IP adress " . $this->user_ip . "\n";
					$body .= "low threshold " . $this->limits['lower'] . "\n";
					$body .= "upper threshold " . $this->limits['upper'] . "\n";

					$body .= "word found  : " . $word . " \n\n";

					foreach ($commentdata as $key => $val) {
						$body .= "$key : $val \n";
					}


					Header('HTTP/1.1 403 Forbidden');
					
					echo $this->language['explain'];
					echo '<br/>We found a spamword in your comment: '.$word;

					$commment_key = $this->store_comment($commentdata,'killed');
					$this->mail_details('rejected comment based on word', $body, $commment_key);
					$this->update_stats('killed');
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


		if (!empty ($url)) {
			$count = $this->check_count('comment_author_url', $url);
			$temp = $this->compare_counts($count, 'comment_author_url', $commentdata);
			if ($temp) {
				return $temp;
			}

			// Now check for words
			if ($html_body = wp_remote_retrieve_body(wp_remote_get($url))) {
				if (!empty ($html_body)) {
					foreach ($this->words as $word) {
						$string_is_spam = $this->string_is_spam($word, $html_body);
						if ($string_is_spam) {
							$body = "Details are below: \n";
							$body .= "action: I visited URL of commenter, found spamword on that page, comment denied \n";

							$body .= "IP adress " . $this->user_ip . "\n";
							$body .= "low threshold " . $this->limits['lower'] . "\n";
							$body .= "upper threshold " . $this->limits['upper'] . "\n";

							$body .= "word found  : " . $word . " \n\n";

							foreach ($commentdata as $key => $val) {
								$body .= "$key : $val \n";
							}

							Header('HTTP/1.1 403 Forbidden');
							echo $this->language['explain'];
							echo '<br/>We found a spamword in your comment: '.$word;

							$commment_key = $this->store_comment($commentdata,'spammed');
							$this->mail_details('rejected comment based on word', $body, $commment_key);							
							$this->update_stats('spammed');
							die('spam');
						}
					}
				}
			}
		}
		return 0;
	}
}
?>