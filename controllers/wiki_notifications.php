<?php
class WikiNotifications {
	function __construct() {
		$this->WikiNotifications();
	}
	
	function WikiNotifications() {
		if (!wp_next_scheduled('cron_email_hook'))
	    	wp_schedule_event( time(), 'weekly', 'cron_email_hook' );
	}

	/**
	 * wiki_page_edit_notification 
	 * @global <type> $wpdb
	 * @param <type> $pageID
	 * @return NULL
	 */
	function page_edit_notification ($pageID) {
	    global $wpdb;
	    
	    // First, make sure this *is* a Wiki page -CWJ
	    $p = get_post($pageID);
	    if ($p->post_type=='wiki') :
			$revisions = get_children(array(
				'post_type' => 'revision',
				'post_parent' => $pageID,
				'orderby' => 'post_modified_gmt',
				'order' => 'DESC',
			));

			$wpw_options = get_option('wpw_options');
			if($wpw_options['email_admins'] == 1){
		  
				$emails = $this->getAllAdmins();
			
				$pageTitle = $p->post_title;
				$pagelink = get_permalink($pageID);

				$subject = "[".get_bloginfo('title')."] Wiki Change: ".$pageTitle;
				
				$message = '<p>'.sprintf(__("A Wiki Page has been modified on %s."),get_option('home'),$pageTitle).'</p>';
				$message .= "\n\r";
				$message .= '<p>'.sprintf(__("The page title is %s"), $pageTitle).'</p>'; 
				$message .= "\n\r";
				$message .= '<p>'.__('To visit this page, ').'<a href="'.$pagelink.'">'.__('click here').'</a></p>';
				
				$left_revision = reset($revisions);
				$right_revision = $p;
				
				ob_start();
				?>
				<style type="text/css">
				table.diff .diff-deletedline {
					background-color: #FFDDDD;
				}
				table.diff .diff-deletedline del {
					background-color: #FF9999;
				}
				table.diff .diff-addedline {
					background-color: #DDFFDD;
				}
				table.diff diff.addedline ins {
					background-color: #99FF99;
				}
				</style>
				
				<table>
				<?php
				$identical = true;
				foreach ( _wp_post_revision_fields() as $field => $field_title ) :
					$left_content = apply_filters(
						"_wp_post_revision_field_$field",
						$left_revision->$field,
						$field
					);
					$right_content = apply_filters(
						"_wp_post_revision_field_$field",
						$right_revision->$field,
						$field
					);

					if ( !$content = wp_text_diff( $left_content, $right_content ) )
						continue; // There is no diff between left and right
					$identical = false;
					?>

					<tr id="revision-field-<?php echo $field; ?>">
					<th scope="row"><?php echo esc_html( $field_title ); ?></th>
					<td><div class="pre"><?php echo $content; ?></div></td>
					</tr>
					<?php
				endforeach;

				if ( $identical ) :
				?>
				<tr><td colspan="2"><div class="updated"><p><?php _e('These revisions are identical.'); ?></p></div></td></tr>
				<?php
				endif;
				?>
				</table>
				<?php
				$message .= ob_get_clean();

				foreach($emails as $email){
					add_filter('wp_mail_content_type', array($this, 'allow_html_mail'));
					wp_mail($email, $subject, $message);
					remove_filter('wp_mail_content_type', array($this, 'allow_html_mail'));
				} 
			}
		endif;
	} /* function page_edit_notification () */
	
	function allow_html_mail ( $type ) {
		return 'text/html';
	}
	
	/**
	 * getAllAdmins 
	 * @global <type> $wpdb
	 * @param <type> NULL
	 * @return email addresses for all administrators
	 */
	function getAllAdmins(){
		global $wpdb;
		$sql = "SELECT ID from $wpdb->users";
		$IDS = $wpdb->get_col($sql);
	
		foreach($IDS as $id){
			$user_info = get_userdata($id);
			if($user_info->user_level == 10){
				$emails[] = $user_info->user_email;
			
			}
		}
		return $emails;
	}
		
	function more_reccurences() {
	    return array(
	        'weekly' => array('interval' => 604800, 'display' => 'Once Weekly'),
	        'fortnightly' => array('interval' => 1209600, 'display' => 'Once Fortnightly'),
	    );
	}
	
	function cron_email() {
		$wpw_options = get_option('wpw_options');
	    
	    if ($wpw_options['cron_email'] == 1) {
	        $last_email = $wpw_options['cron_last_email_date'];
	        
			$emails = getAllAdmins();
			$sql = "SELECT post_title, guid FROM ".$wpdb->prefix."posts WHERE post_modified > ".$last_email;
	        
			$subject = "Wiki Change";
			$results = $wpdb->get_results($sql);
		
	        $message = " The following Wiki Pages has been modified on '".get_option('home')."' \n\r ";
	        if ($results) {
	            foreach ($results as $result) {
	                $pageTitle = $result->post_title;
	                $pagelink = $result->guid;
	                $message .= "Page title is ".$pageTitle.". \n\r To visit this page <a href='".$pagelink."'> click here</a>.\n\r\n\r";
	                //exit(print_r($emails, true));
	                foreach($emails as $email){
	                    wp_mail($email, $subject, $message);
	                }
	            }
	        }
	        $wpw_options['cron_last_email_date'] = date('Y-m-d G:i:s');
	        update_option('wpw_options', serialize($wpw_options));
	    }
	}

}
?>