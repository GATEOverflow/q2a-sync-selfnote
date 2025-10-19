<?php
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

class qa_html_theme_layer extends qa_html_theme_base
{
	private $allowed_user_level;
	private $sync_question;

    public function __construct($template, $content, $rooturl, $request)
    {
        parent::__construct($template, $content, $rooturl, $request);

        $this->allowed_user_level = qa_opt('sync_modlevel');
		$this->sync_question=0;
    }
	
    //Add the Sync button in the question buttons area
    public function q_view_buttons($q_view)
    {

        if (
            qa_get_logged_in_level() >= $this->allowed_user_level &&
            $this->template === 'question' &&
            isset($q_view['form']['buttons'])
        ) {
            $postid = (int)$q_view['raw']['postid'];

            $sync_button = array(
                'addsync' => array(
                    'label' => qa_lang('qa_sync_lang/sync_button_label'),
                    'tags'  => 'name="sync-button" type="button" class="qa-form-light-button qa-sync-toggle" data-postid="' . $postid . '"',
                    'popup' => qa_lang('qa_sync_lang/sync_button_popup'),
                ),
            );

            qa_array_insert($q_view['form']['buttons'], null, $sync_button);
        }

        parent::q_view_buttons($q_view);
    }


    /**
     * Output popup markup
     */
	public function body_hidden()
	{
		if ($this->template == 'question' && qa_get_logged_in_level() >= $this->allowed_user_level) {
				$sync_css = '
					<style>
						#sync-popup {
							background: rgba(0,0,0,.75);
							height: 100%;
							width: 100%;
							position: fixed;
							top: 0;
							left: 0;
							display: none;
							z-index: 5119;
						}
						#sync-center {
							margin: 5% auto;
							width: auto;
							text-align: center;
						}
						.sync-wrap {
							display: inline-block;
							width: 35%;
							position: relative;
							background: #fff;
							border: 1px solid #f00;
							padding: 15px;
							text-align: left;
							z-index: 3335;
							max-width: 70%;
						}
						.sync-wrap div { margin: 10px; }
						.sync-form-wrap input[type="text"] { width: 100%; box-sizing: border-box; }
						.sync-close-button {
							position: absolute;
							top: 5px;
							right: 7px;
							font-size: 20px;
							color: #333;
							cursor: pointer;
							background: #eaeaea;
							border-radius: 3px;
							width: 20px;
							height: 20px;
							line-height: 20px;
							text-align: center;
						}
						#sync-error {
							color:red;
							display:none;
						}
						
						.qa-sync-note-divider { border:none; border-top:1px solid #ddd; margin:10px 0; }

				
				.qa-sync-note-wrapper { margin-top:20px; }
                .qa-sync-note-divider { border:none; border-top:1px solid #ddd; margin:10px 0; }
                .qa-sync-note-block { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:12px 15px; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
                .qa-sync-note-content { color:#555; line-height:1.5; }
                @media (max-width:768px){ .qa-note-wrap { width:90%; } }
					</style>';

			$this->output_raw($sync_css);
			$postid = $this->content["q_view"]["raw"]["postid"];
			$form_code_add_sync = qa_get_form_security_code('add_sync_question');
			$form_code_update_sync = qa_get_form_security_code('update_sync_question');
			$this->output('
			<div id="sync-popup">
				<div id="sync-center">
					<div class="sync-wrap">
						<h4>' . qa_lang('qa_sync_lang/sync_popup_title') . '</h4>
						<div id="sync-error"></div>
						<div class="sync-form-wrap">
							<form method="post" action="">
								<input type="hidden" name="syncnote_code" value="'.$form_code_add_sync.'">
								<input type="text" name="sync_question_url" placeholder="' . qa_lang('qa_sync_lang/sync_placeholder') . '">
								<input type="hidden" name="sync_postid" id="sync_postid" value="' . $postid . '">
								<input type="submit" name="dosync" class="qa-form-tall-button qa-form-tall-button-submit" value="' . qa_lang('qa_sync_lang/sync_submit') . '">
							</form>
						</div>');

			// Get existing synced questions
			$relateds = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT related_postid, related_postid_prefix FROM ^synced_questions WHERE postid=#',
				$postid
			));
			
			if (!empty($relateds)) {
				$this->output('' . qa_lang('qa_sync_lang/related_questions_title') . '');
				$this->output('<form method="post" action=""><input type="hidden" name="syncnote_update_code" value="'.$form_code_update_sync.'">');
				$this->output('<input type="hidden" name="sync_postid" value="' . $postid . '">');
				$this->output('<ul>');
				foreach ($relateds as $r) {

					$table=$r['related_postid_prefix'].'posts';
					$title = qa_db_read_one_value(qa_db_query_sub(
						'SELECT title FROM '.$table.' WHERE postid=# and type=\'Q\'', $r['related_postid']
					), true);
					if ($title) {
						// combine prefix and id into a single value, separated by |
						$checkboxValue = $r['related_postid_prefix'] . '|' . $r['related_postid'];
						$this->output('<input type="checkbox" name="remove_syncs[]" value="' . qa_html($checkboxValue) . '"> <a href="' . qa_q_path_html($r['related_postid'], $title) . '">' . qa_html($title) . '</a><br />');
					}
				}
				$this->output('</ul>');
				$this->output('<input type="submit" name="update_syncs" class="qa-form-tall-button qa-form-tall-button-submit" value="' . qa_lang('qa_sync_lang/remove_selected') . '">');
				$this->output('</form>');
			}

			$this->output('<div class="sync-close-button">×</div></div></div></div>');

			// JS to toggle popup
			$this->output_raw('
			<script>
			document.addEventListener("DOMContentLoaded",function(){
				var popup=document.getElementById("sync-popup");
				var postIdInput=document.getElementById("sync_postid");

				document.querySelectorAll(".qa-sync-toggle").forEach(function(btn){
					btn.addEventListener("click",function(e){
						e.preventDefault();
						postIdInput.value=this.getAttribute("data-postid");
						popup.style.display="block";
					});
				});

				var closeBtn=document.querySelector(".sync-close-button");
				if(closeBtn){
					closeBtn.addEventListener("click",function(){
						popup.style.display="none";
					});
				}
			});
			</script>
			');
		}
		parent::body_hidden();
	}
	
	public function q_view_content($q_view)
	{
		parent::q_view_content($q_view);

		// Inject synced questions above comments
		$synced_html = $this->get_synced_questions_html($q_view['raw']['postid']);
		if ($synced_html) {
			echo '<div class="qa-synced-questions-block">' . $synced_html . '</div>';
		}
	}


	private function get_synced_questions_html($postid)
	{
		$relateds = qa_db_read_all_assoc(qa_db_query_sub(
			'SELECT related_postid, related_postid_prefix FROM ^synced_questions WHERE postid=#',
			$postid
		));

		if (empty($relateds)) return '';

		$html = '<div class="qa-sync-note-wrapper"><hr class="qa-sync-note-divider"> <div class="qa-sync-note-block">';
		$html .= qa_lang('qa_sync_lang/related_questions_title') . '<div class="qa-sync-content"><ul>';

		foreach ($relateds as $r) {
			$table=$r['related_postid_prefix'].'posts';
			$title = qa_db_read_one_value(qa_db_query_sub(
				'SELECT title FROM '.$table.' WHERE postid=# and type=\'Q\'', $r['related_postid']
			), true);

			if ($title) {
				$pre_url=qa_network_site_url_sync_plugin($r['related_postid_prefix']);

				// Prepend the base URL of the other site
				$html .= '<li><a href="' . $pre_url .'/'. $r['related_postid'] . '/'.$title.'">' . qa_html($title) . '</a></li>';
				
			}
		}

		$html .= '</ul></div></div></div>';
		return $html;
	}


    /**
     * Extract postid and site_prefix from URL
     */
    private function extract_postid_from_url($url, $original_question_id)
    {
		$postid=null;
		$post=null;
		$target_prefix=null;
		$url = $url ?? '';
		if ($url) {
			foreach (qa_network_sites_list_sync_plugin() as $site) {
				$siteUrl = $site['url'];
				if (strpos($url, $siteUrl.'/') === 0) {
					// Normal Q
					if (preg_match('#' . preg_quote($siteUrl, '#') . '/([0-9]+)/?#', $url, $matches)) {
						//Check that post is available or not before saying match found.-----
						$postid = (int)$matches[1];
						$target_prefix = $site['prefix'];
						if( ($target_prefix === QA_MYSQL_TABLE_PREFIX) && ($postid === $original_question_id) ){
								$this->sync_question=1; //Indicating the same question is adding as relative question
								break;
						}
						if( qa_db_read_one_value(qa_db_query_sub('SELECT * FROM ^synced_questions WHERE postid=# and related_postid=# and related_postid_prefix=$', $original_question_id, $postid, $target_prefix), true) ){
								$this->sync_question=2; //Indicating the new question is already added as relative question
								break;
						}
						
						$table = $target_prefix.'posts';
						//error_log("table".$table);
						$post = qa_db_read_one_value(qa_db_query_sub('SELECT * FROM '.$table.' WHERE postid=# and type = \'Q\'', $postid), true);
						$this->sync_question=3; // Indicating the new question may available in the table iff $post is not null. 
						break;
					}
					$this->sync_question=4; // Indicating the URL is not of a question post
					break;
				}
				$this->sync_question=5; // Indicating the URL is not belongs to this site.
			}
		}
		if ($post) {
			return [
				'postid' => $postid,
				'prefix' => $target_prefix,
			];
		}

        return null;
    }
	
	
	


    /**
     * Handle POST submission before content output
     */
    public function main()
    {
        // Add new sync
		if ( qa_get_logged_in_level() >= $this->allowed_user_level && qa_check_form_security_code('add_sync_question', qa_post_text('syncnote_code')) &&  qa_post_text('dosync')) {
            $postid = (int)qa_post_text('sync_postid');
            $url    = trim(qa_post_text('sync_question_url'));
            if ($postid && $url) {
                $row = $this->extract_postid_from_url($url,$postid);
				if (!$row) {
					if($this->sync_question==1)
						$error_text=qa_lang('qa_sync_lang/same_question');
					else if($this->sync_question==2)
						$error_text=qa_lang('qa_sync_lang/already_added_question');
					else if($this->sync_question==3)
						$error_text=qa_lang('qa_sync_lang/no_question_found');
					else if($this->sync_question==4)
						$error_text=qa_lang('qa_sync_lang/not_question_post');
					else
						$error_text=qa_lang('qa_sync_lang/not_belongs_to_this_site');

					$this->output_raw('<script>
					document.addEventListener("DOMContentLoaded",function(){
						var errBox=document.getElementById("sync-error");
						if(errBox){
							errBox.textContent="'.$error_text.'";
							errBox.style.display="block";
							// keep popup open
							document.getElementById("sync-popup").style.display="block";
						}
					});
					</script>');
				}
				else {
					$related_postid = $row['postid'];
					$related_postid_prefix = $row['prefix'];
					//error_log("url:".$url."----".$related_postid."----".$related_postid_prefix);
					add_sync_question($postid, $related_postid, $related_postid_prefix);
					$this->output_raw('<script>
					document.addEventListener("DOMContentLoaded",function(){
						var errBox=document.getElementById("sync-error");
						if(errBox){
							errBox.style.display="none";
						}
					});
					</script>');
				}
            }
        }

        // Remove selected syncs
        if (qa_get_logged_in_level() >= $this->allowed_user_level && qa_check_form_security_code('update_sync_question', qa_post_text('syncnote_update_code')) &&  qa_post_text('update_syncs')) {
            $postid = (int)qa_post_text('sync_postid');
			$toRemove = isset($_POST['remove_syncs']) ? $_POST['remove_syncs'] : array();	
            if ($postid && is_array($toRemove)) {
				$where1='';
				$where2='';
                foreach ($toRemove as $val) {
					list($prefix, $id) = explode('|', $val, 2);
                    $rid = (int)$id;
					//error_log("selected id:".$rid."Prefix : ".$prefix);
					$from_table = $prefix . "synced_questions";
                    $q1=('DELETE FROM '.$from_table.' WHERE postid='.$rid);
					//error_log("q1:".$q1);
					if (table_exists($from_table))
						qa_db_query_sub($q1);
					if($where1){
						$where1.='or (related_postid = '.$rid.' and related_postid_prefix = "'.$prefix.'")';
						$where2.=', ('.$rid.',"'.$prefix.'") ';
					}
					else{
						$where1.='(related_postid = '.$rid.' and related_postid_prefix = "'.$prefix.'") ';
						$where2.='('.$rid.',"'.$prefix.'") ';
					}
                }
				$q2=('DELETE FROM ^synced_questions WHERE postid='.$postid.' and ( '.$where1.' )');
				//error_log("q2:".$q2);
				qa_db_query_sub($q2);
				$q = 'SELECT related_postid, related_postid_prefix FROM ^synced_questions WHERE postid='.$postid.' and (related_postid, related_postid_prefix) NOT IN ( '.$where2.') ';
				//error_log("q:".$q);
				$all=qa_db_read_all_assoc(qa_db_query_sub($q));
				foreach ($all as $val) {
					$from_table = $val['related_postid_prefix']. "synced_questions";
					$q3=('DELETE FROM '.$from_table.' WHERE postid='.$val['related_postid'].' and ( '.$where1.' )');
					//error_log("q3:".$q3);
					if (table_exists($from_table))
						qa_db_query_sub($q3);
				}
				
            } 
        }

        parent::main();
    }

}
