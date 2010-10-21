<?php
/*
Plugin Name: Antispam for all fields
Plugin URI: http://www.mijnpress.nl
Description: Class and functions
Author: Ramon Fincken
Version: 0.1
Author URI: http://www.mijnpress.nl
*/

add_filter('pre_comment_approved', 'plugin_antispam_for_all_fields', 1);

function plugin_antispam_for_all_fields($status)
{
	global $commentdata;
	$afaf = new antispam_for_all_fields();
	$afaf->init($status, $commentdata);
}

class antispam_for_all_fields
{
	function init($status, $commentdata)
	{
		$this->lower_limit = 2;
		$this->upper_limit = 10;
		$this->wpdb_spam_status = 'spam';
		$this->user_ip = htmlspecialchars(preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] ));
		
			$email = $commentdata['comment_author_email'];
			$author = $commentdata['comment_author'];
			$url = $commentdata['comment_author_url'];
			$comment = $commentdata['comment_content'];
			
			if(!empty($email))
			{
				$count = $this->check_count('comment_author_email',$email);
				$temp = $this->compare_counts($count,'comment_author_email',$commentdata);
				if($temp)
				{
					return $temp;
				}
			}
			if(!empty($author))
			{
				$count = $this->check_count('comment_author',$author);
				$temp = $this->compare_counts($count,'comment_author',$commentdata);
				if($temp)
				{
					return $temp;
				}
			}
			if(!empty($url))
			{
				$count = $this->check_count('comment_author_url',$url);
				$temp = $this->compare_counts($count,'comment_author_url',$commentdata);
				if($temp)
				{
					return $temp;
				}
			}		
			
			// IP check
			$count = $this->check_count('comment_author_IP',$this->user_ip);
			$temp = $this->compare_counts($count,'comment_author_IP',$commentdata);
			if($temp)
				{
					return $temp;
				}

		return 0;
	}
	
	private function compare_counts($count,$field,$commentdata)
	{
		if($count > $this->upper_limit)
		{
			$body = "Details are below: \n";
			$body .= "action: exceeded upper threshold, comment denied \n";
			
			$body .= "IP adress ".$this->user_ip. "\n";
			$body .= "low threshold ".$this->lower_limit. "\n";
			$body .= "upper threshold ".$this->upper_limit. "\n";
			
			$body .= "number of simular comments for field ($field) : ".$count. " times\n\n";
			foreach($commentdata as $key => $val)
			{
				$body .= "$key : $val \n";
			}
			$admin_email = get_option('admin_email');
			$log_email = get_option('plugin_antispamfaf_emaillog');
			$blogname = get_option('blogname');
			if(isset($log_email) && !empty($log_email))
			{
				wp_mail($log_email,'['.$blogname.'][antispam] rejected comment '.date('r'),$body);
			}
			else
			{
				wp_mail($admin_email,'['.$blogname.'][antispam] rejected comment '.date('r'),$body);
			}
			Header('HTTP/1.1 403 Forbidden');
			die('spam');
		}
		else
		{
			if($count > $this->lower_limit)
			{
				$body = "Details are below: \n";
				$body .= "action: exceeded lower threshold, comment marked as spam \n";
				
				$body .= "IP adress ".$this->user_ip. "\n";
				$body .= "low threshold ".$this->lower_limit. "\n";
				$body .= "upper threshold ".$this->upper_limit. "\n";
				
				$body .= "number of simular comments for field ($field) : ".$count. " times\n\n";
				foreach($commentdata as $key => $val)
				{
					$body .= "$key : $val \n";
				}
				$admin_email = get_option('admin_email');
				$log_email = get_option('plugin_antispamfaf_emaillog');
				$blogname = get_option('blogname');
				if(isset($log_email) && !empty($log_email))
				{
					wp_mail($log_email,'['.$blogname.'][antispam] spammed comment '.date('r'),$body);
				}
				else
				{
					wp_mail($admin_email,'['.$blogname.'][antispam] spammed comment '.date('r'),$body);
				}
							
				return 'spam';
			}			
		}
		return false;
	}
	
	private function check_count($field,$value)
	{
		global $wpdb;
		
		$sql = 'SELECT COUNT(`'.$field.'`) AS numberofrows FROM '.$wpdb->comments. ' WHERE `'.$field.'` = %s AND comment_approved =%s';
		$preparedsql = $wpdb->prepare($sql,$value,$this->wpdb_spam_status);
		$results = $wpdb->get_results($preparedsql,ARRAY_A);
		return $results[0]['numberofrows'];
	}
}
?>