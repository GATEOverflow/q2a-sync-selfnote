<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

class qa_sync_admin {

    function option_default($option) {
        if ($option == 'misc_tweaks_ask_reorder')
            return 0;
    }
	
	// initialize db-table if it does not exist yet
	public function init_queries($tableslc) 
	{	
		$queries = array();
		$table1 = qa_db_add_table_prefix('synced_questions');
		$table2 = qa_db_add_table_prefix('usernote');

		if (!in_array($table1, $tableslc)) {
			$queries[] = "
				CREATE TABLE `$table1` (
                    postid INT NOT NULL,
                    related_postid INT NOT NULL,
                    related_postid_prefix VARCHAR(20) NOT NULL,
                    PRIMARY KEY (postid, related_postid, related_postid_prefix)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
			";
		}
		if (!in_array($table2, $tableslc)) {
			$queries[] = "
				CREATE TABLE `$table2` (
                    postid INT NOT NULL,
                    userid INT NOT NULL,
                    note VARCHAR(20000) NOT NULL,
                    PRIMARY KEY (postid, userid)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
			";
		}
		return empty($queries) ? null : $queries;
	}

	function admin_form(&$qa_content) {

		require QA_INCLUDE_DIR . 'qa-app-users.php';
		$userlevels = array(
			QA_USER_LEVEL_BASIC => qa_lang_html('users/registered_user'),
			QA_USER_LEVEL_APPROVED => qa_lang_html('users/approved_user'),
			QA_USER_LEVEL_EXPERT => qa_lang_html('users/level_expert'),
			QA_USER_LEVEL_EDITOR => qa_lang_html('users/level_editor'),
			QA_USER_LEVEL_MODERATOR => qa_lang_html('users/level_moderator'),
			QA_USER_LEVEL_ADMIN => qa_lang_html('users/level_admin'),
			QA_USER_LEVEL_SUPER => qa_lang_html('users/level_super'),
		);
		
        $saved = false;
        if (qa_clicked('sync_save')) {
			//qa_opt('add_list_link', (int)qa_post_text('add_list_link'));
			qa_opt('sync_modlevel', qa_post_text('sync_modlevel'));
            $saved = true;
        }

        return array(
            'ok' => $saved ? 'Settings saved' : null,

            'fields' => array(
                array(
                    'type' => 'select',
					'label' => qa_lang('qa_sync_lang/moderator_level'),
					'tags' => 'name="sync_modlevel" id="sync_modlevel"',
					'options' => $userlevels,
					'value' => @$userlevels[qa_opt('sync_modlevel')],
                ),
            ),

            'buttons' => array(
                array(
                    'label' => 'Save',
                    'tags' => 'name="sync_save"',
                ),
            ),
        );
    }
}
