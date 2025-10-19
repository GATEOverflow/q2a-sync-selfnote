<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;   
}               


class my_sync_merge_event {
	private $exist1;
	private $exist2;
	
    function process_event($event, $userid, $handle, $cookieid, $params) {
		if ($event === 'in_q_merge') {
			//in the event of merge of a question, we need to update the same in the 'synced_questions' table
			$newpostid    = (int)$params['postid'];
			$oldpostid    = (int)$params['oldpostid'];
			$from_prefix  = $params['from_site'] ?? QA_MYSQL_TABLE_PREFIX;
			$to_prefix    = $params['to_site'] ?? QA_MYSQL_TABLE_PREFIX;
			$is_from_blog = (int)($params['is_from_blog'] ?? 0);
			$is_to_blog   = (int)($params['is_to_blog'] ?? 0);
			$table1 = $from_prefix . 'synced_questions';
			$table2 = $to_prefix . 'synced_questions';
			
			
			// Check whether an inverse mapping already exists to avoid unnecessary work.
			// Use COUNT(*) to get a boolean-like answer.
			$exists1 = false;
			$exists2 = false;

			if (table_exists($table1)) {
				$exists1 = (int) qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT COUNT(*) FROM ' . $table1 . ' WHERE postid = # AND related_postid = # AND related_postid_prefix = $',
						$oldpostid, $newpostid, $to_prefix
					),
					true
				) > 0;
			}

			if (table_exists($table2)) {
				$exists2 = (int) qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT COUNT(*) FROM ' . $table2 . ' WHERE postid = # AND related_postid = # AND related_postid_prefix = $',
						$newpostid, $oldpostid, $from_prefix
					),
					true
				) > 0;
			}
			//error_log("newpostid : ".$newpostid." "."oldpostid : ".$oldpostid." "."from_prefix : ".$from_prefix." "."to_prefix : ".$to_prefix." "."is_from_blog : ".$is_from_blog." "."is_to_blog : ".$is_to_blog." "."table1 : ".$table1." "."table2 : ".$table2." "."exists1 : ".$exists1." "."exists2 : ".$exists2);
			
			//Update the list when merging/only redirecting happened between Q->Q
			if (!$is_from_blog && !$is_to_blog && !$exists1 && !$exists2) {
				foreach (qa_network_sites_list_sync_plugin() as $site) {
					$table = $site['prefix'] . 'synced_questions';
					if (table_exists($table)) {
						// Always update related_postid pointing to old
						qa_db_query_sub(
							'UPDATE ' . $table . '
							 SET related_postid = #, related_postid_prefix = $
							 WHERE related_postid = # AND related_postid_prefix = $',
							$newpostid, $to_prefix,
							$oldpostid, $from_prefix
						);

						// Same-site merge: just update postid
						if ($from_prefix === $to_prefix && $site['prefix'] === $from_prefix) {
							qa_db_query_sub(
								'UPDATE ' . $table . '
								 SET postid = #
								 WHERE postid = #',
								$newpostid, $oldpostid
							);
						}
						

						// Cross-site merge: copy rows
						else if ($from_prefix !== $to_prefix && $site['prefix'] === $from_prefix) {
							// 1. Grab all rows belonging to old postid in from_site
							$rows = qa_db_read_all_assoc(qa_db_query_sub(
								'SELECT * FROM ' . $table . ' WHERE postid = #', $oldpostid
							));

							// 2. Insert them into to_site table with newpostid
							$to_table = $to_prefix . 'synced_questions';
							if (table_exists($to_table)) {
								foreach ($rows as $r) {
									qa_db_query_sub(
										'INSERT IGNORE INTO ' . $to_table . ' (postid, related_postid, related_postid_prefix)
										 VALUES (#, #, $)',
										$newpostid, $r['related_postid'], $r['related_postid_prefix']
									);
								}
							}								
						}
					}
				}
			}
		}

		if ($event === 'q_delete') {
			$postid = (int)$params['postid'];
 			
/* As the delete event processed first then merge event, the following block is commented. Otherwise, rows will be delete before we update.

			// 1. Delete rows in the local site where the post itself is the primary post

			$local_table = QA_MYSQL_TABLE_PREFIX . 'synced_questions';
			if (table_exists($local_table)) {
				qa_db_query_sub(
					'DELETE FROM ' . $local_table . ' WHERE postid = #',
					$postid
				);
			}

			// 2. Delete rows in all network sites where this post is referenced as related
			foreach (qa_network_sites_list_sync_plugin() as $site) {
				$table = $site['prefix'] . 'synced_questions';

				if (table_exists($table)) {
					qa_db_query_sub(
						'DELETE FROM ' . $table . ' WHERE related_postid = # AND related_postid_prefix = $',
						$postid,
						QA_MYSQL_TABLE_PREFIX
					);
				}
			} */
		}
    }
}