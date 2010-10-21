<?php
/*
Plugin Name: Antispam for all fields
Plugin URI: http://www.mijnpress.nl
Description: Class and functions
Author: Ramon Fincken
Version: 0.2
Author URI: http://www.mijnpress.nl
*/

add_filter('pre_comment_approved', 'plugin_antispam_for_all_fields', 1);

function plugin_antispam_for_all_fields($status) {
   global $commentdata;
   $afaf = new antispam_for_all_fields();
   $afaf->init($status, $commentdata);
}

class antispam_for_all_fields {
   function init($status, $commentdata) {
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

                        $this->mail_details('rejected comment based on word',$body);

                        Header('HTTP/1.1 403 Forbidden');
                        echo $word;
                        die('spam');
                  }
               }
            }
         }
      }
      if (!empty ($comment_content)) {
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

               $this->mail_details('rejected comment based on word',$body);

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

   private function get_words() {
      $words = array (
         '*.ru*',
         // '*sex*', positive for sex and the city
         '*pharmac*',
         '*CIALIS*',
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
         '*anal*',
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


   private function mail_details($subject, $body)
   {
         $admin_email = get_option('admin_email');
         $log_email = get_option('plugin_antispamfaf_emaillog');
         $blogname = get_option('blogname');
         if (isset ($log_email) && !empty ($log_email)) {
            $email_to = $log_email;
         } else {
            $email_to = $admin_email;
         }
         $body.="\n\nAntispam for all fields by Ramon Fincken\nhttp://wordpress.org/extend/plugins/profile/ramon-fincken";
         wp_mail($email_to, '[' . $blogname . '][Antispam] '.$subject.' ' . date('r'), $body);
   }

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

         $this->mail_details('rejected comment',$body);

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

            $this->mail_details('spammed comment',$body);


            return 'spam';
         }
      }
      return false;
   }

   // ---------------- CHANGE STRING FUNCTIONS
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

   private function check_count($field, $value) {
      global $wpdb;

      $sql = 'SELECT COUNT(`' . $field . '`) AS numberofrows FROM ' . $wpdb->comments . ' WHERE `' . $field . '` = %s AND comment_approved =%s';
      $preparedsql = $wpdb->prepare($sql, $value, $this->wpdb_spam_status);
      $results = $wpdb->get_results($preparedsql, ARRAY_A);
      return $results[0]['numberofrows'];
   }
}
?>
