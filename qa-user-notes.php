<?php
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

class user_notes_page
{
    private $userid;
    private $handle;

    
public function suggest_requests()
{
    $guest_handle = qa_get_logged_in_handle();

    return [
        [
            'title' => qa_lang_sub('qa_sync_lang/All_notes'),
            'request' => 'user-notes/' . $guest_handle . '/',
            'nav' => 'M',
        ],
    ];
}

    public function match_request($request)
    {
        $requestparts = qa_request_parts();
        $guest_handle = qa_get_logged_in_handle();
        $user_handle = isset($requestparts[1]) ? $requestparts[1] : $guest_handle;

        $isMy = ($user_handle === $guest_handle);
        $isAuthorized = qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN;

        if ($requestparts[0] === 'user-notes' && ($isMy || $isAuthorized)) {
            $this->userid = qa_handle_to_userid($user_handle);
            $this->handle = $user_handle;
            return true;
        }
        return false;
    }


 public function process_request($request)
{
    // Ensure user is logged in
    $userid = $this->userid;
    if (!$userid) {
        qa_redirect('login', array('to' => qa_path(qa_request())));
    }

    // Load helpers
    require_once QA_INCLUDE_DIR . 'db/selects.php';
    require_once QA_INCLUDE_DIR . 'app/format.php';
    require_once QA_INCLUDE_DIR . 'app/q-list.php';

    // Pagination and sorting
    $categoryslugs = qa_request_parts(2);
    $countslugs = count($categoryslugs);
    $sort = ($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('sort');
    $start = qa_get_start();
	
	// Get total count of marked posts for this user
        $query = "SELECT postid FROM ^usernote WHERE userid = #";
        $result = qa_db_query_sub($query, $userid);
        $postids = qa_db_read_all_values($result);
        $tcount = ($postids ? count($postids) : 0);

    // Determine sort
    $selectsort = match($sort) {
        'hot' => 'hotness',
        'votes' => 'netvotes',
        'answers' => 'acount',
        'views' => 'views',
        default => 'created',
    };

    // Build selectspec for user's notes
    $selectspec = $this->qa_db_qs_mod_selectspec(
        $userid, $selectsort, $start, $categoryslugs, null, false, false, qa_opt_if_loaded('page_size_qs')
    );

    // Fetch questions with categories
    list($questions, $categories, $categoryid) = qa_db_select_with_pending(
        $selectspec,
        qa_db_category_nav_selectspec($categoryslugs, false, false, true),
        $countslugs ? qa_db_slugs_to_category_id_selectspec($categoryslugs) : null
    );

    

    $nonetitle = $countslugs
        ? qa_lang_html_sub('main/no_questions_in_x', qa_html($categories[$categoryid]['title']))
        : qa_lang_html('main/no_questions_found');

    // Build basic question list content
    $qa_content = qa_q_list_page_content(
        $questions,
        qa_opt('page_size_qs'),
        $start,
        $tcount,
        qa_lang_sub('qa_sync_lang/user_notes_page_title', $this->handle),
        $nonetitle,
        $categories,
        $categoryid ?? null,
        false,
        'user-notes/'.$this->handle.'/', // category prefix
        null,
        null,
        ['sort' => $sort],
        ['sort' => $sort]
    );

    // Security code for forms
    $formcode = qa_get_form_security_code('user_note');

    // Handle Add/Update Note
    if (qa_post_text('save-note') && qa_check_form_security_code('user_note', qa_post_text('formcode'))) {
        $postid = (int)qa_post_text('postid');
        $note = trim(qa_post_text('note'));

        $exists = qa_db_read_one_value(
            qa_db_query_sub("SELECT COUNT(*) FROM ^usernote WHERE postid=# AND userid=#", $postid, $userid)
        );

        if ($exists) {
            qa_db_query_sub("UPDATE ^usernote SET note=$ WHERE postid=# AND userid=#", $note, $postid, $userid);
        } else {
            qa_db_query_sub("INSERT INTO ^usernote (postid, userid, note) VALUES (#, #, $)", $postid, $userid, $note);
        }

        qa_redirect(qa_request(), ['updated' => 1]);
    }

    // Handle Delete Note
    if (qa_post_text('delete-note') && qa_check_form_security_code('user_note', qa_post_text('formcode'))) {
        $postid = (int)qa_post_text('postid');
        qa_db_query_sub("DELETE FROM ^usernote WHERE postid=# AND userid=#", $postid, $userid);
        qa_redirect(qa_request(), ['deleted' => 1]);
    }

    // Add notes to questions safely
    foreach ($qa_content['q_list']['qs'] as $key => $q) {
        $postid = $q['raw']['postid'];

        // Fetch note
        $note = qa_db_read_one_value(
            qa_db_query_sub("SELECT note FROM ^usernote WHERE postid=# AND userid=#", $postid, $userid),
            true
        );

        // Ensure content key exists
        if (!isset($qa_content['q_list']['qs'][$key]['content'])) {
            $qa_content['q_list']['qs'][$key]['content'] = '';
        }
		if ($note) {
			$qa_content['q_list']['qs'][$key]['content'] .= '
				<div class="user-note">
					<strong>' . qa_lang_html('qa_sync_lang/note_label') . ':</strong>
					<div class="note-text collapsible-note" id="note-text-' . $postid . '">' . qa_html($note) . '</div>
					<button type="button" class="toggle-note-btn" onclick="toggleNoteText(this)">Show more</button>
					<div class="note-actions">
						<form method="post" style="display:inline;">
							<input type="hidden" name="formcode" value="' . qa_html($formcode) . '">
							<input type="hidden" name="postid" value="' . (int)$postid . '">
							<input type="submit" name="delete-note" value="' . qa_lang_html('qa_sync_lang/delete_note') . '" class="qa-form-tall-button" onclick="return confirm(\'Are you sure?\')">
						</form>
						<button type="button" class="qa-form-tall-button" onclick="openNoteModal(' . $postid . ')">' . qa_lang_html('qa_sync_lang/update_note') . '</button>
					</div>
				</div>';
		} else {
			$qa_content['q_list']['qs'][$key]['content'] .= '
				<button type="button" class="qa-form-tall-button" onclick="openNoteModal(' . $postid . ')">' . qa_lang_html('qa_sync_lang/add_note') . '</button>';
		}

	}

    // Modal and JS for Add/Update Note
    $qa_content['custom'] = '
        <div id="noteModal" class="note-modal">
            <div class="note-modal-content">
                <span class="note-close" onclick="closeNoteModal()">&times;</span>
                <form method="post" action="' . qa_self_html() . '">
                    <input type="hidden" name="formcode" value="' . qa_html($formcode) . '">
                    <input type="hidden" name="postid" id="note-postid">
                    <label>' . qa_lang('qa_sync_lang/note_label') . ':</label>
                    <textarea name="note" id="note-input" rows="10" style="width:100%;"></textarea>
                    <div style="margin-top:10px;">
                        <input type="submit" name="save-note" value="Save" class="qa-form-tall-button">
                        <button type="button" class="qa-form-tall-button" onclick="closeNoteModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <style>
            .user-note { background:#f9f9f9; border-left:4px solid #ccc; padding:0.8em; border-radius:4px; margin-top:1em; }
            .note-modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
            .note-modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:40%; position:relative; }
            .note-close { position:absolute; right:10px; top:5px; font-size:24px; cursor:pointer; }
            .note-actions { margin-top:6px; }
            .collapsible-note { max-height:100px; overflow:hidden; transition:max-height 0.3s; white-space:pre-wrap; word-wrap:break-word; }
            .collapsible-note.expanded { max-height:none; }
            .toggle-note-btn { margin-top:5px; background:none; border:none; color:#0077cc; cursor:pointer; font-size:0.9em; padding:0; }
            .toggle-note-btn:hover { text-decoration:underline; }
        </style>

        <script>
            function openNoteModal(postid) {
                var noteEl = document.getElementById("note-text-" + postid);
                var noteText = noteEl ? noteEl.innerText.trim() : "";
                document.getElementById("note-postid").value = postid;
                document.getElementById("note-input").value = noteText;
                document.getElementById("noteModal").style.display = "block";
            }
            function closeNoteModal() { document.getElementById("noteModal").style.display = "none"; }
            window.onclick = function(e) { if(e.target == document.getElementById("noteModal")) closeNoteModal(); }
			function toggleNoteText(button) {
                    const note = button.previousElementSibling;
                    const expanded = note.classList.toggle("expanded");
                    button.textContent = expanded ? "Show less" : "Show more";
                }

                // Hide "Show more" button if note content fits within limit
                document.addEventListener("DOMContentLoaded", function () {
                    document.querySelectorAll(".collapsible-note").forEach(note => {
                        const toggleBtn = note.nextElementSibling;
                        if (toggleBtn && toggleBtn.classList.contains("toggle-note-btn")) {
                            if (note.scrollHeight <= note.clientHeight + 2) {
                                toggleBtn.style.display = "none";
                            }
                        }
                    });
                });
        </script>
    ';
        /* ---- Sub navigation ---- */
        $qa_content['navigation']['sub'] = qa_user_sub_navigation(
            $this->handle,
            'user-notes',
            ($this->userid == qa_get_logged_in_userid())
        );
    return $qa_content;
}

	
	private function qa_db_qs_mod_selectspec($voteuserid, $sort, $start, $categoryslugs = null, $createip = null, $specialtype = false, $full = false, $count = null)
	{
		if ($specialtype == 'Q' || $specialtype == 'Q_QUEUED') {
			$type = $specialtype;
		} else {
			$type = $specialtype ? 'Q_HIDDEN' : 'Q';
		}

		$count = isset($count) ? min($count, QA_DB_RETRIEVE_QS_AS) : QA_DB_RETRIEVE_QS_AS;

		switch ($sort) {
			case 'acount':
			case 'flagcount':
			case 'netvotes':
			case 'views':
				$sortsql = 'ORDER BY ^posts.' . $sort . ' DESC, ^posts.created DESC';
				break;

			case 'created':
			case 'hotness':
				$sortsql = 'ORDER BY ^posts.' . $sort . ' DESC';
				break;

			default:
				qa_fatal_error('qa_db_qs_selectspec() called with illegal sort value');
				break;
		}

		$selectspec = qa_db_posts_basic_selectspec($voteuserid, $full);

		// Get the user's postids from ^userreads and convert into CSV string
		$query = "SELECT postid FROM ^usernote WHERE userid=#";
		$result = qa_db_query_sub($query, $voteuserid);
		$postids = qa_db_read_all_values($result);
		if ($postids && is_array($postids) && count($postids)) {
			$questions = implode(',', array_map('intval', $postids));
		} else {
			// no posts: use an impossible value list to make IN() empty
			$questions = '0';
		}

		// Append a JOIN that restricts posts to those in the user's reads
		$selectspec['source'] .= " JOIN (SELECT postid FROM ^posts WHERE postid IN ($questions)) aby ON aby.postid=^posts.postid";

		// Append the category slug filter + ordering + limit (keeps same structure as original)
		$selectspec['source'] .=
			" JOIN (SELECT postid FROM ^posts WHERE " .
			qa_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
			(isset($createip) ? "createip=UNHEX($) AND " : "") .
			"type=$ ) y ON ^posts.postid=y.postid " . $sortsql . " LIMIT #,#";

		if (isset($createip)) {
			$selectspec['arguments'][] = bin2hex(@inet_pton($createip));
		}

		array_push($selectspec['arguments'], $type, $start, $count);
		$selectspec['sortdesc'] = $sort;

		return $selectspec;
	}
}
