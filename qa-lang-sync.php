<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

return array(
    'sync_button_label'       => 'Add Sync Questions',
    'sync_button_popup'       => 'Add synced questions for this post',
    'sync_popup_title'        => 'Add Synced Question',
    'sync_popup_desc'         => 'Enter the URL of a question you want to sync with this post:',
    'sync_placeholder'        => 'Enter question URL',
    'sync_submit'             => 'Sync',
    'related_questions_title' => 'Related Questions :',
	'remove_selected'         => 'Remove selected',
	'sync_not_found' => 'No such question found for this URL',
	'moderator_level' => 'Minimum level of user to add sync questions:',
	'same_question' => 'You are trying to add this question as the related question which is not allowed.',
	'already_added_question' => 'You are trying to add the question which is already a related question',
	'no_question_found' => 'There is no question found with the given URL',
	'not_question_post' => 'The URL doesn\'t seems the question post',
	'not_belongs_to_this_site' => 'The URL neither matches with this site nor network sites',
	'note_label' => 'Add Note',
	'note_popup_label' => 'Add/Edit personal note to this question',
	'note_popup_title' => 'Add / Edit Note for this question',
	'note_popup_placeholder' => 'Write your private note here...',
	'note_comment_heading' => 'Self note added by me:',
	'user_notes_title' => 'User Notes',
	'user_notes_page_title' => 'Notes of ^',
	'note_label' => 'Note',
	'update_note' => 'Update Note',
	'delete_note' => 'Delete Note',
	'no_notes' => 'No notes found.',
	'All_notes' => 'All Notes',
);

