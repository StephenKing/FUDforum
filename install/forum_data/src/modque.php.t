<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	/* Only admins & moderators have access to this control panel. */
	if (!_uid) {
		std_error('login');
	} else if (!($usr->users_opt & (1048576|524288))) {	// NOT is_admin OR is_mod.
		std_error('perms');
	}

	$appr = isset($_GET['appr']) ? (int) $_GET['appr'] : 0;
	$del  = isset($_GET['del'])  ? (int) $_GET['del']  : 0;

	/* We need to determine wether or not the message exists & if the user has access to approve/delete it. */
	if ($appr || $del) {
		if (!q_singleval('SELECT CASE WHEN ('. q_bitand($usr->users_opt, 1048576) .' = 0) THEN mm.id ELSE 1 END FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON t.forum_id=mm.forum_id AND mm.user_id='. _uid .' WHERE m.id='. ($appr ? $appr : $del))) {
			if (db_affected()) {
				std_error('perms');
			} else {
				$del = $appr = 0;
			}
		}

		if (sq_check(0, $usr->sq)) {
			if ($appr) {
				fud_msg_edit::approve($appr);
				logaction($usr->id, 'APPROVEMSG', $appr);
			} else if ($del) {
				fud_msg_edit::delete(false, $del);
// TODO: Create a new REJECTMSG action?
				logaction($usr->id, 'DELMSG', $del);
			}
		}
	}

	ses_update_status($usr->sid, '', 0);

	/*{POST_HTML_PHP}*/

	/* For sanity sake, we only select up to POSTS_PER_PAGE messages, simply because otherwise the form will
	 * become unmanageable.
	 */
	$r = q(q_limit('SELECT
		m.*, COALESCE(m.flag_cc, u.flag_cc) AS disp_flag_cc, COALESCE(m.flag_country, u.flag_country) AS disp_flag_country,
		t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold, f.name AS frm_name,
		c.name AS cat_name,
		u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.google, u.skype, u.twitter, u.last_visit AS time_sec, u.users_opt,
		l.name AS level_name, l.level_opt, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
		pot.id AS cant_vote
	FROM
		{SQL_TABLE_PREFIX}msg m
	INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
	INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
	INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
	'. ($is_a ? '' : ' INNER JOIN {SQL_TABLE_PREFIX}mod mm ON f.id=mm.forum_id AND mm.user_id='. _uid .' ') .'
	INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
	LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
	LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
	LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
	LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='. _uid .'
	WHERE f.forum_opt>=2 AND m.apr=0
	ORDER BY v.id, m.post_stamp DESC', $POSTS_PER_PAGE));

	$modque_message = '';
	$m_num          = 0;

	/* Quick cheat to give us full access to the messages ;) */
	$perms         = 2147483647;
	$_GET['start'] = 0;

	$usr->md = 1;
	while ($obj = db_rowobj($r)) {
		$modque_message .= '{TEMPLATE: modque_message}';
	}
	unset($r);

	if (!$modque_message) {
		$modque_message = '{TEMPLATE: no_modque_msg}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MODQUE_PAGE}
