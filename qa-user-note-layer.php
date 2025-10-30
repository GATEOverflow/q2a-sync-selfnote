<?php
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

class qa_html_theme_layer extends qa_html_theme_base
{
    protected string $note_block_html = '';

    public function __construct($template, $content, $rooturl, $request)
    {
        parent::__construct($template, $content, $rooturl, $request);
    }

    /* ============================================
       Helper Methods
    ============================================ */

    protected function fetch_note(int $postid, int $userid): string
    {
        $note = qa_db_read_one_value(qa_db_query_sub(
            'SELECT note FROM ^usernote WHERE postid=# AND userid=#',
            $postid, $userid
        ), true);

        return $note !== null ? $note : '';
    }

    protected function save_note(int $postid, int $userid, string $note_text): void
    {
        qa_db_query_sub(
            "REPLACE INTO ^usernote (postid, userid, note) VALUES (#, #, $)",
            $postid, $userid, $note_text
        );
    }

    protected function delete_note(int $postid, int $userid): void
    {
        qa_db_query_sub(
            'DELETE FROM ^usernote WHERE postid=# AND userid=#',
            $postid, $userid
        );
    }

    protected function render_note_block(string $note): string
    {
        $note_html = '<b>' . qa_lang_html('qa_sync_lang/note_comment_heading') . '</b><br>' . nl2br(qa_html($note));

        return '
            <div class="qa-user-note-wrapper">
                <hr class="qa-user-note-divider">
                <div class="qa-user-note-block">
                    <div class="qa-user-note-content collapsible-note">
                        ' . $note_html . '
                    </div>
                    <button type="button" class="toggle-note-btn" onclick="toggleNoteText(this)">Show more</button>
                </div>
            </div>';
    }

    /* ============================================
       Change template type
    ============================================ */
	public function doctype()
	{
		if (qa_request() === 'user-notes') {
			$this->template = 'user-notes';
		}
		parent::doctype();
	}

    /* ============================================
       Inject note block after question content
    ============================================ */
    public function q_view($q_view)
    {
        if ($this->template === 'question' && qa_is_logged_in()) {
            $postid = (int)$q_view['raw']['postid'];
            $userid = qa_get_logged_in_userid();

            if ($postid && $userid) {
                $note = $this->fetch_note($postid, $userid);
                if (!empty($note)) {
                    $this->note_block_html = $this->render_note_block($note);
                }
            }
        }

        parent::q_view($q_view);
    }

    public function q_view_content($q_view)
    {
        parent::q_view_content($q_view);

        if (!empty($this->note_block_html)) {
            echo $this->note_block_html;
        }
    }

    /* ============================================
       Add Note Button in question buttons
    ============================================ */
    public function q_view_buttons($q_view)
    {
        if (
            qa_is_logged_in() &&
            $this->template === 'question' &&
            isset($q_view['form']['buttons'])
        ) {
            $postid = (int)$q_view['raw']['postid'];

            $note_button = array(
                'addnote' => array(
                    'label' => qa_lang('qa_sync_lang/note_label'),
                    'tags'  => 'name="add-note" type="button" class="qa-form-light-button qa-note-toggle" data-postid="' . $postid . '"',
                    'popup' => qa_lang('qa_sync_lang/note_popup_label'),
                ),
            );

            qa_array_insert($q_view['form']['buttons'], null, $note_button);
        }

        parent::q_view_buttons($q_view);
    }

    /* ============================================
       Popup and hidden markup
    ============================================ */
    public function body_hidden()
    {
        if ($this->template === 'question' && qa_is_logged_in()) {
            $postid = isset($this->content["q_view"]["raw"]["postid"]) ? (int)$this->content["q_view"]["raw"]["postid"] : 0;
            $userid = qa_get_logged_in_userid();
            $note = $this->fetch_note($postid, $userid);

            $form_code_usernote = qa_get_form_security_code('add_note');

            $note_css = '
                <style>
                #qa-note-popup { background: rgba(0,0,0,.6); height:100%; width:100%; position:fixed; top:0; left:0; display:none; z-index:5119; }
                #qa-note-center { margin:5% auto; width:auto; text-align:center; }
                .qa-note-wrap { display:inline-block; width:40%; max-width:700px; min-width:300px; background:#fff; border:1px solid #ddd; padding:15px; border-radius:6px; box-shadow:0 6px 24px rgba(0,0,0,.2); text-align:left; position:relative; }
                .qa-note-wrap textarea { width:100%; height:160px; box-sizing:border-box; padding:8px; resize:vertical; }
                .qa-note-close { position:absolute; top:8px; right:8px; font-size:18px; color:#333; cursor:pointer; background:#f5f5f5; border-radius:3px; width:26px; height:26px; line-height:26px; text-align:center; }
                .qa-note-actions { margin-top:10px; text-align:right; }
                .qa-note-error { color:red; display:none; margin-bottom:10px; }
                .qa-user-note .pdeleted-delete { display:none !important; }
                .qa-user-note-wrapper { margin-top:20px; }
                .qa-user-note-divider { border:none; border-top:1px solid #ddd; margin:10px 0; }
                .qa-user-note-block { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:12px 15px; box-shadow:0 1px 2px rgba(0,0,0,0.05); text-align: left; }
                .qa-user-note-content { color:#555; line-height:1.5; }
                @media (max-width:768px){ .qa-note-wrap { width:90%; } }
                .collapsible-note { max-height:100px; overflow:hidden; position:relative; transition:max-height 0.3s ease; word-wrap:break-word; }
                .collapsible-note.expanded { max-height:none; }
                .toggle-note-btn { margin-top:5px; background:none; border:none; color:#0077cc; cursor:pointer; padding:0; font-size:0.9em; }
                .toggle-note-btn:hover { text-decoration:underline; }
                </style>
            ';

            $this->output_raw($note_css);

            $this->output('
                <div id="qa-note-popup">
                    <div id="qa-note-center">
                        <div class="qa-note-wrap">
                            <div class="qa-note-close">×</div>
                            <h3>'.qa_lang('qa_sync_lang/note_popup_title').'</h3>
                            <div id="qa-note-error" class="qa-note-error"></div>
                            <form method="post" action="">
                                <input type="hidden" name="usernote_code" value="'.$form_code_usernote.'">
                                <input type="hidden" name="usernote_postid" id="usernote_postid" value="'.$postid.'">
                                <textarea name="usernote_text" id="usernote_text" placeholder="'.qa_lang('qa_sync_lang/note_popup_placeholder').'">'.qa_html($note).'</textarea>
                                <div class="qa-note-actions">
                                    <input type="submit" name="dosave_note" class="qa-form-tall-button qa-form-tall-button-submit" value="Save">
                                    <input type="submit" name="dodelete_note" class="qa-form-tall-button" value="Delete" style="margin-left:8px;">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                function toggleNoteText(button) {
                    const note = button.previousElementSibling;
                    const expanded = note.classList.toggle("expanded");
                    button.textContent = expanded ? "Show less" : "Show more";
                }

                document.addEventListener("DOMContentLoaded", function () {
                    var popup = document.getElementById("qa-note-popup");
                    var closeBtn = document.querySelector(".qa-note-close");

                    if (closeBtn) {
                        closeBtn.addEventListener("click", function(){ popup.style.display="none"; });
                    }

                    popup.addEventListener("click", function(e){ if(e.target===popup) popup.style.display="none"; });

                    document.querySelectorAll(".qa-note-toggle").forEach(function(btn){
                        btn.addEventListener("click", function(e){
                            e.preventDefault();
                            var pid = this.getAttribute("data-postid") || document.getElementById("usernote_postid").value;
                            document.getElementById("usernote_postid").value = pid;
                            popup.style.display="block";
                        });
                    });

                    // Hide "Show more" buttons for short notes
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
            ');
        }

        parent::body_hidden();
    }

    /* ============================================
       Handle POST requests securely
    ============================================ */
    public function main()
    {
        if (qa_is_logged_in()) {
            $postid = (int)qa_post_text('usernote_postid', 0);
            $userid = qa_get_logged_in_userid();

            if ($postid && $userid && qa_check_form_security_code('add_note', qa_post_text('usernote_code'))) {

                if (qa_post_text('dosave_note')) {
                    $note_text = trim(qa_post_text('usernote_text'));
                    $this->save_note($postid, $userid, $note_text);
                    qa_redirect(qa_q_request($postid, $this->content['q_view']['raw']['title']));
                }

                if (qa_post_text('dodelete_note')) {
                    $this->delete_note($postid, $userid);
                    qa_redirect(qa_q_request($postid, $this->content['q_view']['raw']['title']));
                }
            }
        }

        parent::main();
    }
   /* ============================================
       For Navigation in the user account
    ============================================ */	
	public function nav($navtype, $level = null)
	{
		// Only modify when the user profile sub navigation exists
		if (isset($this->content['navigation']['sub']['profile'])) {

			$guest_handle = qa_get_logged_in_handle();
			$user_handle = qa_request_part(1) ?qa_request_part(1): $guest_handle;

			// Access control: show for own profile or admin
			$isMy = ($user_handle === $guest_handle);
			$isAuthorized = (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN);

			if ($isMy || $isAuthorized) {
				// Build User Notes sub-navigation item
				$usernotes_sub_nav = [
					'user_notes' => [
						'label' => qa_lang_html('qa_sync_lang/All_notes'),
						'url'   => qa_path_html('user-notes/' . $user_handle, null, qa_opt('site_url')),
						'selected' => (
							qa_request_part(0) === 'user-notes'
						),
					],
				];

				// Insert into sub-navigation after existing items
				qa_array_insert($this->content['navigation']['sub'], null, $usernotes_sub_nav);
			}
		}

		// Continue rendering default navigation
		qa_html_theme_base::nav($navtype, $level);
	}

}
