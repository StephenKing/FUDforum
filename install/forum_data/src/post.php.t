<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post.php.t,v 1.26 2003/04/07 14:23:14 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('msg_edit', 1); define("_imsg_edit_inc_", 1);
/*{PRE_HTML_PHP}*/
	
	$pl_id = $old_subject = $attach_control_error = '';

	/* redirect user where need be in moderated forums after they've seen
	 * the moderation message.
	 */
	if(isset($_POST['moderated_redr'])) {
		check_return($usr->returnto);
	}

	/* we do this because we don't want to take a chance that data is passed via cookies */
	if (isset($_GET['reply_to']) || isset($_POST['reply_to'])) {
		$reply_to = (int) $_REQUEST['reply_to'];
	} else {
		$reply_to = 0;
	}
	if (isset($_GET['msg_id']) || isset($_POST['msg_id'])) {
		$msg_id = (int) $_REQUEST['msg_id'];
	} else {
		$msg_id = 0;
	}
	if (isset($_GET['th_id']) || isset($_POST['th_id'])) {
		$th_id = (int) $_REQUEST['th_id'];
	} else {
		$th_id = 0;
	}
	if (isset($_GET['frm_id']) || isset($_POST['frm_id'])) {
		$frm_id = (int) $_REQUEST['frm_id'];
	} else {
		$frm_id = 0;
	}

	/* replying or editing a message */
	if ($reply_to || $msg_id) {
		$msg = new fud_msg_edit;
		$msg->get_by_id(($reply_to ? $reply_to : $msg_id));
	 	$th_id = $msg->thread_id;
	}

	$frm = new fud_forum;
	if ($th_id) {
		$thr = new fud_thread;
		$thr->get_by_id($th_id);	
		$frm->get($thr->forum_id);
	} else if ($frm_id) {
		$frm->get($frm_id);
		$th_id = NULL;
	} else {
		std_error('systemerr');
		exit;
	}
	
	$MAX_F_SIZE = $frm->max_attach_size;
	
	/* More Security */
	if (isset($thr) && $usr->is_mod != 'A' && $thr->locked=='Y') {
		error_dialog('{TEMPLATE: post_err_lockedthread_title}', '{TEMPLATE: post_err_lockedthread_msg}', '');
	}

	/* fetch permissions & moderation status */
	$perms = init_single_user_perms($frm->id, $usr->is_mod, $MOD);
	
	if (_uid) {
		/* all sorts of user blocking filters */
		is_allowed_user($usr);
		
		/* if not moderator, validate user permissions */
		if (!$MOD) {
			if (!$reply_to && !$msg_id && $perms['p_post'] != 'Y') {
				error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
			} else if (($th_id || $reply_to) && $perms['p_reply'] != 'Y') {
				error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
			} else if ($msg_id && $msg->poster_id != $usr->id && $perms['p_edit'] != 'Y') {
				error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
			} else if ($msg_id && $EDIT_TIME_LIMIT && ($msg->post_stamp + $EDIT_TIME_LIMIT * 60 <__request_timestamp__)) {
				error_dialog('{TEMPLATE: post_err_edttimelimit_title}', '{TEMPLATE: post_err_edttimelimit_msg}', ''); 
			}
		}
	} else {
		if (!$th_id && $perms['p_post'] != 'Y') {
			error_dialog('{TEMPLATE: post_err_noannontopics_title}', '{TEMPLATE: post_err_noannontopics_msg}', ''); 
		} else if ($perms['p_reply'] != 'Y') {
			error_dialog('{TEMPLATE: post_err_noannonposts_title}', '{TEMPLATE: post_err_noannonposts_msg}', ''); 
		}
	}

	/* Retrieve Message */
	if (!isset($_POST['prev_loaded'])) { 
		if (_uid) {
			$msg_show_sig = $usr->append_sig;
			$msg_poster_notif = $usr->notify;
		}
		
		if ($msg_id) {
			$msg->export_vars('msg_');
			
			$msg_body = post_to_smiley($msg_body);
	 		
	 		switch ($frm->tag_style) {
	 			case 'ML':
	 				$msg_body = html_to_tags($msg_body);
	 				break;
	 			case 'HTML':
	 				break;
	 			default:
	 				reverse_FMT($msg_body);
	 				reverse_nl2br($msg_body);
	 		}
	 		
	 		$msg_body = apply_reverse_replace($msg_body);
	 		
	 		reverse_FMT($msg_subject);
			$msg_subject = apply_reverse_replace($msg_subject);
	 		
	 		$msg_poster_notif = is_notified($usr->id, $msg->thread_id) ? 'Y' : 'N';
	 			
	 		if ($msg->attach_cnt) {
	 			$r = q("SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$msg->id." AND private='N'");
	 			while ($fa_id = db_rowarr($r)) {
	 				$attach_list[$fa_id[0]] = $fa_id[0];
	 			}
	 			qf($r);
	 			$attach_count = count($attach_list);
		 	}
		 	$pl_id = $msg->poll_id;	
		} else if ($reply_to || $th_id) {
			$subj = $reply_to ? $msg->subject : $thr->subject;
			reverse_FMT($subj);
			$subj = apply_reverse_replace($subj);
		
			$reply_prefix = preg_quote(strtolower('{TEMPLATE: reply_prefix}'));
			$msg_subject = ( !preg_match('/^{TEMPLATE: reply_prefix}/i', $subj) ) ? '{TEMPLATE: reply_prefix}'.$subj : $subj;
			$old_subject = $msg_subject;

			if (isset($_GET['quote'])) {
				$msg_body = apply_reverse_replace($msg->body);
				$msg_body = post_to_smiley(str_replace("\r", '', $msg_body));
				
				if (!strlen($msg->login)) {
					$msg->login = $GLOBALS['ANON_NICK'];
				}
				reverse_FMT($msg->login);
				
				switch ($frm->tag_style) {
					case 'ML':
						$msg_body = html_to_tags($msg_body);
						reverse_FMT($msg_body);
				 		$msg_body = '{TEMPLATE: fud_quote}';
				 		break;
					case 'HTML':
						$msg_body = '{TEMPLATE: html_quote}';
						break;
					default:
						reverse_FMT($msg_body);
						reverse_nl2br($msg_body);
						$msg_body = str_replace('<br>', "\n", '{TEMPLATE: plain_quote}');
				}
				$msg_body .= "\n";
			}
		}
	} else { /* $_POST['prev_loaded'] */
		if ($FLOOD_CHECK_TIME && !$MOD && !$msg_id && ($tm = flood_check())) {
			error_dialog('{TEMPLATE: post_err_floodtrig_title}', '{TEMPLATE: post_err_floodtrig_msg}', '');
		}
		
		if ($perms['p_file'] == 'Y' || $MOD) {
			$attach_count = 0;
			
			/* restore the attachment array */
			if (!empty($_POST['file_array']) ) {
				$attach_list = @unserialize(base64_decode($_POST['file_array']));
			}
			
			/* remove file attachment */
			if (!empty($_POST['file_del_opt']) && isset($attach_list[$_POST['file_del_opt']])) {
				$attach_list[$_POST['file_del_opt']] = 0;
				/* Remove any reference to the image from the body to prevent broken images */
				if (strpos($msg_body, '[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]')) {
					$msg_body = str_replace('[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]', '', $msg_body);
				}
					
				$attach_count--;
			}	
			
			/* newly uploaded files */
			if (isset($_FILES['attach_control']) && $_FILES['attach_control']['size']) {
				if ($_FILES['attach_control']['size'] > $MAX_F_SIZE * 1024) {
					$attach_control_error = '{TEMPLATE: post_err_attach_size}';
				} else {
					if (filter_ext($_FILES['attach_control']['name'])) {
						$attach_control_error = '{TEMPLATE: post_err_attach_ext}';
					} else {
						if (($attach_count+1) <= $frm->max_file_attachments) {
							$val = attach_add($_FILES['attach_control'], _uid);
							$attach_list[$val] = $val;
							$attach_count++;
						} else {
							$attach_control_error = '{TEMPLATE: post_err_attach_filelimit}';
						}	
					}	
				}	
			}
			$attach_cnt = $attach_count;
		}
		
		/* removal of a poll */
		if (isset($_POST['pl_del'], $_POST['pl_id']) && ($MOD || $perms['p_poll'] == 'Y')) {
			poll_delete((int)$_POST['pl_id']);
			unset($_POST['pl_id']);
		}
		
		if ($reply_to && $old_subject == $msg_subject) {
			$no_spell_subject = 1;
		}
				
		if (isset($_POST['btn_spell'])) {
			$GLOBALS['MINIMSG_OPT']['DISABLED'] = 1;
			$text = apply_custom_replace($_POST['msg_body']);
			$text_s = apply_custom_replace($_POST['msg_subject']);
		
			switch ($frm->tag_style) {
				case 'ML':
					$text = tags_to_html($text, $perms['p_img']);
					break;
				case 'HTML':
					break;
				default:
					$text = htmlspecialchars($text);
			}

			if ($perms['p_sml'] == 'Y' && !isset($_POST['msg_smiley_disabled'])) {
				$text = smiley_to_post($text);
			}

	 		if (strlen($text)) {	
				$wa = tokenize_string($text);
				$msg_body = spell_replace($wa, 'body');
				
				if ($perms['p_sml'] == 'Y' && !isset($_POST['msg_smiley_disabled'])) {
					$msg_body = post_to_smiley($msg_body);
				}
				if ($frm->tag_style == 'ML' ) {
					$msg_body = html_to_tags($msg_body);
				} else if ($frm->tag_style != 'HTML') {
					reverse_FMT($msg_body);
				}
				
				$msg_body = apply_reverse_replace($msg_body);
			}	
			$wa = '';
			
			if (strlen($_POST['msg_subject']) && empty($no_spell_subject)) {
				$text_s = htmlspecialchars($text_s);
				$wa = tokenize_string($text_s);
				$text_s = spell_replace($wa, 'subject');
				reverse_FMT($text_s);
				$msg_subject = apply_reverse_replace($text_s);
			}
		}
		
		if (isset($_POST['submitted']) && !isset($_POST['spell']) && !isset($_POST['preview'])) {
			$_POST['btn_submit'] = 1;
		}
		
		if ($usr->is_mod != 'A' && isset($_POST['btn_submit']) && $frm->passwd_posting == 'Y' && $frm->post_passwd != $_POST['frm_passwd']) {
			set_err('password', '{TEMPLATE: post_err_passwd}');
		}
		
		/* submit processing */
		if (isset($_POST['btn_submit']) && !check_post_form()) {
			$msg_post = new fud_msg_edit;
			
			/* Process Message Data */
			$msg_post->poster_id = _uid;
			$msg_post->poll_id = $_POST['pl_id'];
			$msg_post->fetch_vars($_POST, 'msg_');
		 	$msg_post->smiley_disabled = isset($_POST['msg_smiley_disabled']) ? 'Y' : 'N';
		 	$msg_post->attach_cnt = (int) $attach_cnt;
			$msg_post->body = apply_custom_replace($msg_post->body);
			
			switch ($frm->tag_style) {
				case 'ML':
					$msg_post->body = tags_to_html($msg_post->body, $perms['p_img']);
					break;
				case 'HTML':
					break;
				default:
					$msg_post->body = nl2br(htmlspecialchars($msg_post->body));
			}
			
	 		if ($perms['p_sml'] == 'Y' && $msg_post->smiley_disabled != 'Y') {
	 			$msg_post->body = smiley_to_post($msg_post->body);
	 		}
	 			
			fud_wordwrap($msg_post->body);
			
			$msg_post->subject = apply_custom_replace($msg_post->subject);
			$msg_post->subject = htmlspecialchars($msg_post->subject);
			$msg_post->subject = addslashes($msg_post->subject);
		
		 	/* chose to create thread OR add message OR update message */
		 	
		 	if (!$th_id) {
		 		$create_thread = 1;
		 		$msg_post->add($frm->id, $frm->message_threshold, $frm->moderated, FALSE);
		 		$thr = new fud_thread;
		 		$thr->get_by_id($msg_post->thread_id);
		 	} else if ($th_id && !$msg_id) {
				$msg_post->thread_id = $th_id;
		 		$msg_post->add_reply($reply_to, $th_id, FALSE);
			} else if ($msg_id) {
				$msg_post->id = $msg_id;
				$msg_post->thread_id = $th_id;
				$msg_post->post_stamp = $msg->post_stamp;
				$msg_post->sync(_uid, $frm->id, $frm->message_threshold);
				/* log moderator edit */
			 	if (_uid && _uid != $msg->poster_id) {
			 		logaction($usr->id, 'MSGEDIT', $msg_post->id);
			 	}
			} else {
				std_error('systemerr');
				exit();
			}

			/* write file attachments */
			if ($perms['p_file'] == 'Y' && isset($attach_list)) {
				fud_attach::finalize($attach_list, $msg_post->id);
			}	
			
			if (!$msg_id && ($frm->moderated == 'N' || $MOD)) {
				$msg_post->approve(NULL, TRUE);
			}	
	
			/* deal with notifications */
			if (_uid) {
	 			if ($_POST['msg_poster_notif'] == 'Y') {
	 				thread_notify_add(_uid, $msg_post->thread_id);
	 			} else if ($msg_id) {
	 				thread_notify_del(_uid, $msg_post->thread_id);
	 			}
			}
			
			/* register a view, so the forum marked as read */
			if (isset($frm) && _uid) {
				register_forum_view($frm->id);
			}
			
			/* where to redirect, to the treeview or the flat view 
			 * and consider what to do for a moderated forum
			 */
			if ($frm->moderated == 'Y' && !$MOD) {
				$data = file_get_contents($GLOBALS['INCLUDE'].'theme/'.$usr->theme_name.'/usercp.inc');
				$s = strpos($data, '<?php') + 5;
				eval(substr($data, $s, (strrpos($data, '?>') - $s)));
				?>
				{TEMPLATE: moderated_forum_post}
				<?php
				exit;
			} else {
				if ($usr->returnto) {
					parse_url($usr->returnto, $tmp);
					$t = $tmp['t'];
				} else {
					$t = d_thread_view;
				}
				if ($t == 'selmsg') { /* send the user to previous page */
					check_return($usr->returnto);
				} else { /* redirect user to their message */
					header('Location: {ROOT}?t='.$t.'&goto='.$msg_post->id.'&'._rsidl);
					exit;
				}
			}
		} /* Form submitted and user redirected to own message */
	} /* $prevloaded is SET, this form has been submitted */
	
	if ($reply_to || $th_id && !$msg_id) {
		ses_update_status($usr->sid, '{TEMPLATE: post_reply_update}', $frm->id, 0);
	} else if ($msg_id) {
		ses_update_status($usr->sid, '{TEMPLATE: post_reply_update}', $frm->id, 0);
	} else  {
		ses_update_status($usr->sid, '{TEMPLATE: post_topic_update}', $frm->id, 0);
	}

	if (isset($_POST['spell'])) {
		$GLOBALS['MINIMSG_OPT']['DISABLED'] = TRUE;
	}

/*{POST_HTML_PHP}*/

	if (!$th_id) {
		$label = '{TEMPLATE: create_thread}';
	} else if ($msg_id) {
		$label = '{TEMPLATE: edit_message}';
	} else {
		$label = '{TEMPLATE: submit_reply}';
	}	
	
	if (isset($_POST['preview']) || isset($_POST['spell'])) {
		$text = apply_custom_replace($_POST['msg_body']);
		$text_s = apply_custom_replace($_POST['msg_subject']);

		switch ($frm->tag_style) {
			case 'ML':
				$text = tags_to_html($text, $perms['p_img']);
				break;
			case 'HTML':
				break;
			default:
				$text = nl2br(htmlspecialchars($text));
		}
			
		if ($perms['p_sml'] == 'Y' && !isset($_POST['msg_smiley_disabled'])) {
			$text = smiley_to_post($text);
		}
	
		$text_s = htmlspecialchars($text_s);
		
		if (function_exists('pspell_config_create') && $usr->pspell_lang && $text) {
			$text = check_data_spell($text, 'body');
		}
		fud_wordwrap($text);

		$sig = $subj = '';
		if ($text_s) {
			if (function_exists('pspell_config_create') && $usr->pspell_lang && empty($no_spell_subject) && strlen($text_s)) {
				$subj .= check_data_spell($text_s,'subject');
			} else {
				$subj .= $text_s;
			}
		}
		if ($GLOBALS['ALLOW_SIGS'] == 'Y' && $msg_show_sig == 'Y') {
			if ($msg_id && $msg->poster_id && $msg->poster_id != _uid && !reply_to) {
				$sig = q_singleval('SELECT sig FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg->poster_id);
			} else {
				$sig = $usr->sig;
			}
		
			$signature = $sig ? '{TEMPLATE: signature}' : '';
		}

		$apply_spell_changes = isset($_POST['spell']) ? '{TEMPLATE: apply_spell_changes}' : '';

		$preview_message = '{TEMPLATE: preview_message}';
	} else {
		$preview_message = '';
	}

	$post_error = is_post_error() ? '{TEMPLATE: post_error}' : '';
	$loged_in_user = _uid ? '{TEMPLATE: loged_in_user}' : '';

	/* handle password protected forums */
	if ($frm->passwd_posting == 'Y' && $usr->is_mod != 'A') {
		$pass_err = get_err('password');
		$post_password = '{TEMPLATE: post_password}';
	} else {
		$post_password = '';
	}
	
	$msg_subect_err = get_err('msg_subject');
	if (!isset($msg_subject)) {
		$msg_subject = '';
	}
	
	/* handle polls */
	$poll = '';
	if ($MOD || $perms['p_poll'] == 'Y') {
		if (!isset($_POST['pl_id'])) {
			$poll = '{TEMPLATE: create_poll}';
		} else if (($poll = db_saq('SELECT id,name FROM {SQL_TABLE_PREFIX}poll WHERE id='.(int)$_POST['pl_id']))) {
			$poll = '{TEMPLATE: edit_poll}';
		}
	}
	
	$admin_options = $mod_post_opts = '';
	/* sticky/announcment controls */
	if ($MOD || $perms['p_sticky'] == 'Y') {
		if (!isset($thr) || ($thr->root_msg_id == $msg->id && !$reply_to)) {
			if (!isset($_POST['prev_loaded'])) {
				$thr_ordertype = $thr->ordertype;
				$thr_orderexpiry = $thr->orderexpiry;
			}

			$thread_type_select = tmpl_draw_select_opt("NONE\nSTICKY\nANNOUNCE", "{TEMPLATE: post_normal}\n{TEMPLATE: post_sticky}\n{TEMPLATE: post_annoncement}", $thr_ordertype, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
			$thread_expiry_select = tmpl_draw_select_opt("1000000000\n3600\n7200\n14400\n28800\n57600\n86400\n172800\n345600\n604800\n1209600\n2635200\n5270400\n10540800\n938131200", "{TEMPLATE: th_expr_never}\n{TEMPLATE: th_expr_one_hr}\n{TEMPLATE: th_expr_three_hr}\n{TEMPLATE: th_expr_four_hr}\n{TEMPLATE: th_expr_eight_hr}\n{TEMPLATE: th_expr_sixteen_hr}\n{TEMPLATE: th_expr_one_day}\n{TEMPLATE: th_expr_two_day}\n{TEMPLATE: th_expr_four_day}\n{TEMPLATE: th_expr_one_week}\n{TEMPLATE: th_expr_two_week}\n{TEMPLATE: th_expr_one_month}\n{TEMPLATE: th_expr_two_month}\n{TEMPLATE: th_expr_four_month}\n{TEMPLATE: th_expr_one_year}", $thr_orderexpiry, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
		
			$admin_options = '{TEMPLATE: admin_options}';
		}
	}	

	/* thread locking controls */
	if ($MOD || $perms['p_lock'] == 'Y') {
		if (!isset($_POST['prev_loaded']) && isset($thr)) {
			$thr_locked_checked = $thr->locked == 'Y' ? ' checked' : '';
		} else if (isset($_POST['prev_loaded'])) {
			$thr_locked_checked = isset($_POST['thr_locked']) ? ' checked' : '';
		}
		$mod_post_opts = '{TEMPLATE: mod_post_opts}';
	}
	
	/* message icon selection */
	$post_icons = draw_post_icons((isset($_POST['msg_icon']) ? $_POST['msg_icon'] : ''));
	
	/* tool bar icons */
	$fud_code_icons = $frm->tag_style == 'ML' ? '{TEMPLATE: fud_code_icons}' : '';
	
	$post_options = tmpl_post_options($frm);
	$message_err = get_err('msg_body', 1);
	if (isset($msg_body)) {
		$msg_body = str_replace("\r", "", $msg_body);
	} else {
		$msg_body = '';
	}
	
	/* handle file attachments */
	if ($perms['p_file'] == 'Y') {
		$file_attachments = draw_post_attachments((isset($attach_list) ? $attach_list : ''), $frm->max_attach_size, $frm->max_file_attachments);
	} else {
		$file_attachments = '';
	}

	if (_uid) {
		$msg_poster_notif_check = $msg_poster_notif == 'Y' ? ' checked' : '';
		$msg_show_sig_check = $msg_show_sig == 'Y' ? ' checked' : '';
		$reg_user_options = '{TEMPLATE: reg_user_options}';
	} else {
		$reg_user_options = '';
	}
	
	/* handle smilies */
	if ($perms['p_sml'] == 'Y') {
		$msg_smiley_disabled_check = (isset($_POST['msg_smiley_disabled']) ? ' checked' : '');
		$disable_smileys = '{TEMPLATE: disable_smileys}';
		$post_smilies = draw_post_smiley_cntrl();
	} else {
		$post_smilies = $disable_smileys = '';
	}
	
	if ($GLOBALS['SPELL_CHECK_ENABLED']=='Y' && function_exists('pspell_config_create') && $usr->pspell_lang) {
		$spell_check_button = '{TEMPLATE: spell_check_button}';
	} else {
		$spell_check_button = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POST_PAGE}