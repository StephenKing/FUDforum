<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mmd.php.t,v 1.1 2005/02/23 05:09:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (!empty($_POST['NO']) || empty($_POST['_sel']) || (empty($_POST['mov_sel_all']) && empty($_POST['del_sel_all']))) {
		check_return($usr->returnto);
	}

	$list = array();
	foreach ($_POST['_sel'] as $v) {
		if ($v = (int) $v) {
			$list[$v] = $v;
		}
	}

	if (!$list) {
		check_return($usr->returnto);
	}

	/* permission check, based on last thread since all threads are supposed to be from the same forum */
	if (!($perms = db_saq('SELECT t.forum_id, (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco, mm.id AS md
				FROM {SQL_TABLE_PREFIX}thread t
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647': '0').' AND g1.resource_id=t.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id
				WHERE t.id='.end($list)))) {
		check_return($usr->returnto);		
	}
	if (!$is_a && !$perms[2] && 
		!(!empty($_POST['mov_sel_all']) && !($perms[1] & 8192)) &&
		!(!empty($_POST['del_sel_all']) && !($perms[1] & 32))) {
		std_error('access');	
	}

	$final_del = !empty($_POST['del_sel_all']) && !empty($_POST['del_conf']);
	$final_mv = !empty($_POST['mov_sel_all']) && !empty($_POST['forum_id']);

	/* ensure that all threads are from the same forum and that they exist */
	$c = uq("SELECT m.subject, t.id, t.root_msg_id, t.replies, t.root_msg_id, t.last_post_date, t.last_post_id
			FROM {SQL_TABLE_PREFIX}thread t 
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
			WHERE t.id IN(".implode(',', $list).") AND t.forum_id=".$perms[0]);
	$ext = $list = array();
	while ($r = db_rowarr($c)) {
		$list[$r[1]] = $r[0];
		if ($final_del) {
			$ext[$r[1]] = array($r[2], $r[3]);
		} else if ($final_mv) {
			$ext[$r[1]] = array($r[4], $r[5], $r[6]);
		}
	}
	if (!$list) {
		invl_inp_err();
	}

	if ($final_del) { /* remove threads, one by one */
		foreach ($ext as $k => $v) {
			logaction(_uid, 'DELTHR', 0, '"'.addslashes($list[$k]).'" w/'.$v[1].' replies');
			fud_msg_edit::delete(true, $v[0], 1);
		}
		check_return($usr->returnto);
	} else if ($final_mv) { /* move threads one by one */
		/* validate permissions for destination forum */
		if (!($_POST['forum_id'] = (int) $_POST['forum_id'])) {
			invl_inp_err();
		}
		if (!($mv_perms = db_saq('SELECT (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco, mm.id AS md
				FROM {SQL_TABLE_PREFIX}forum f
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647': '0').' AND g1.resource_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
				WHERE f.id='.$_POST['forum_id']))) {
			invl_inp_err();
		}
		if (!$is_a && !$mv_perms[1] && !($mv_perms[0] & 8192)) {
			std_error('access');	
		}

		foreach ($list as $k => $v) {
			logaction(_uid, 'THRMOVE', $k);
			th_move($k, $_POST['forum_id'], $ext[$k][0], $perms[0], $ext[$k][1], $ext[$k][2]);
		}
		
		/* update last post ids in source & destination forums */
		foreach (array($perms[0], $_POST['forum_id']) as $v) {
			$mid = (int) q_singleval('SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.forum_id='.$v.' AND t.moved_to=0 AND m.apr=1');
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$mid.' WHERE id='.$v);
		}

		check_return($usr->returnto);
	}
	
	$mmd_topic_ents = '';
	foreach ($list as $k => $v) {
		$mmd_topic_ents .= '{TEMPLATE: mmd_topic_ent}';
	}
	
	if (!empty($_POST['mov_sel_all'])) {
		$table_data = $oldc = '';
	
		$c = uq('SELECT f.name, f.id, c.id, m.user_id, (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco
				FROM {SQL_TABLE_PREFIX}forum f
				INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
				INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
				LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id=f.id
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
				WHERE c.id!=0 AND f.id!='.$perms[0].($is_a ? '' : ' AND (CASE WHEN m.user_id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 1) > 0 THEN 1 ELSE 0 END)=1').'
				ORDER BY v.id');

		require $GLOBALS['FORUM_SETTINGS_PATH'].'cat_cache.inc';
		while ($r = db_rowarr($c)) {
			if ($oldc != $r[2]) {
				while (list($k, $i) = each($cat_cache)) {
					$table_data .= '{TEMPLATE: cat_entry}';
					if ($k == $r[2]) {
						break;
					}
				}
				$oldc = $r[2];
			}

			if ($r[3] || $is_a || $r[4] & 8192) {
				$table_data .= '{TEMPLATE: forum_entry}';
			}
		}
	}
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MMD}