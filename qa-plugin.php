<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

// register admin page
qa_register_plugin_module('module', 'qa-sync-admin.php', 'qa_sync_admin', 'Sync plugin admin Settings');

// register lang
qa_register_plugin_phrases('qa-lang-sync.php', 'qa_sync_lang');

//register sync function layer
qa_register_plugin_layer('qa-synced-questions-layer.php', 'Question view page Layer');

//register self note layer
qa_register_plugin_layer('qa-user-note-layer.php', 'Adding note to the question Layer');

// creating page for showing all the self-notes
qa_register_plugin_module('page', 'user-notes.php', 'user_notes_page', 'Page for listing all user Notes ');



//Event process
qa_register_plugin_module('event', 'qa-sync-merge.php', 'my_sync_merge_event', 'my_sync_merge_event');

//---- DB Functions for merging the questions supports network sites -----

//1. Listing all the network sites that are stored in the current site options including current site.
function qa_network_sites_list_sync_plugin()
{
	$sites = [];
	$i = 0;

	while (true) {
		$prefix = qa_opt('network_site_' . $i . '_prefix');
		$url    = qa_opt('network_site_' . $i . '_url');
		$title  = qa_opt('network_site_' . $i . '_title');

		if (empty($prefix) && empty($url) && empty($title)) {
			break; // stop when no more sites
		}
		if (empty($prefix) || empty($url) || empty($title)) { 
			continue; // skip incomplete site
		}

		$sites[] = [
			'prefix' => $prefix,
			'url'    => rtrim($url, '/'),
			'title'  => $title,
		];

		$i++;
	}

	// Always add the current site as the first option
	array_unshift($sites, [
		'prefix' => QA_MYSQL_TABLE_PREFIX,
		'url'    =>  rtrim(qa_opt('site_url'),'/'),
		'title'  => qa_opt('site_title'),
	]);

	return $sites;
}

//2. Get the URL of the site based on the prefix or prefix of the database based on the URL
function qa_network_site_url_sync_plugin($prefix = null, $url = null) {
    static $sitesList = null;

    // Load network sites once
    if ($sitesList === null) {
        $sitesList = qa_network_sites_list_sync_plugin(); // returns array of ['prefix' => ..., 'url' => ...]
    }

    // If prefix is provided, return URL
    if ($prefix !== null) {
        $sites = array_column($sitesList, 'url', 'prefix'); // ['prefix' => 'url']
        return isset($sites[$prefix]) ? $sites[$prefix] : null;
    }

    // If URL is provided, return prefix
    if ($url !== null) {
        $url = rtrim($url, '/');
        $sites = array_column($sitesList, 'prefix', 'url'); // ['url' => 'prefix']
        return isset($sites[$url]) ? $sites[$url] : null;
    }

    return null; // if both are null
}


/**
 * Add a sync question pair
 */
function add_sync_question($postid, $related_postid, $related_postid_prefix)
{
	if ($postid == $related_postid && QA_MYSQL_TABLE_PREFIX == $related_postid_prefix) return;

	$from_table = QA_MYSQL_TABLE_PREFIX . "synced_questions";
	$all_related_to_postid = qa_db_read_all_assoc(qa_db_query_sub(
		'SELECT related_postid, related_postid_prefix from ' . $from_table . ' WHERE postid=#',
		$postid
	));
	$from_table = $related_postid_prefix . "synced_questions";
	$all_related_to_related_postid = qa_db_read_all_assoc(qa_db_query_sub(
		'SELECT related_postid, related_postid_prefix from ' . $from_table . ' WHERE postid=#',
		$related_postid
	));
	$first=true;
	insert_pair($postid, QA_MYSQL_TABLE_PREFIX, $related_postid, $related_postid_prefix);
	insert_pair($related_postid, $related_postid_prefix, $postid, QA_MYSQL_TABLE_PREFIX);

	foreach ($all_related_to_postid as $otherid_1) {
		insert_pair($otherid_1['related_postid'],$otherid_1['related_postid_prefix'], $related_postid, $related_postid_prefix);
		insert_pair($related_postid, $related_postid_prefix, $otherid_1['related_postid'], $otherid_1['related_postid_prefix']);
		foreach ($all_related_to_related_postid as $othersid_2) {
			insert_pair($othersid_2['related_postid'],$othersid_2['related_postid_prefix'], $otherid_1['related_postid'], $otherid_1['related_postid_prefix']);
			insert_pair($otherid_1['related_postid'], $otherid_1['related_postid_prefix'], $othersid_2['related_postid'], $othersid_2['related_postid_prefix']);
			if($first){
				insert_pair($othersid_2['related_postid'],$othersid_2['related_postid_prefix'], $postid, QA_MYSQL_TABLE_PREFIX);
				insert_pair($postid, QA_MYSQL_TABLE_PREFIX, $othersid_2['related_postid'], $othersid_2['related_postid_prefix']);							
			}
		}
		$first=false;
	}	
}

//Insert the row in the corresponding table
function insert_pair($postid, $postid_prefix, $related_postid, $related_postid_prefix)
{
	//error_log("postid:".$postid." postid_prefix:".$postid_prefix." related_postid:".$related_postid." related_postid_prefix:".$related_postid_prefix);
	$from_table = $postid_prefix . "synced_questions";

	if (table_exists($from_table)) {
		qa_db_query_sub(
			'INSERT IGNORE INTO ' . $from_table . ' (postid, related_postid, related_postid_prefix)
			 VALUES (#,#,$)',
			$postid, $related_postid, $related_postid_prefix
		);
	} else {
		error_log("Table $from_table does not exist");
	}
}

function table_exists($tableName)
{
	// $tableName already contains the full table name (with prefix)
	$result = qa_db_read_one_value(
		qa_db_query_sub(
			"SELECT COUNT(*) FROM information_schema.tables 
			 WHERE table_schema = DATABASE() 
			   AND table_name = $",
			$tableName
		),
		true
	);
	return ($result > 0);
}