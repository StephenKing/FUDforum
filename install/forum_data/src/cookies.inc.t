<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cookies.inc.t,v 1.38 2003/10/02 17:50:57 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function ses_make_sysid()
{
	if ($GLOBALS['FUD_OPT_1'] & 128) {
		return md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ''));
	}
	return;
}

function ses_get($id=0)
{
	if (!$id) {
		if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
			$q_opt = "s.ses_id='".addslashes($_COOKIE[$GLOBALS['COOKIE_NAME']])."'";
		} else if ((isset($_GET['S']) || isset($_POST['S'])) && $GLOBALS['FUD_OPT_1'] & 128) {
			$q_opt = "s.ses_id='".addslashes((isset($_GET['S']) ? $_GET['S'] : $_POST['S']))."' AND sys_id='".ses_make_sysid()."'";
		} else {
			return;
		}
	} else {
		$q_opt = "s.id='".$id."'";
	}

	return db_sab('SELECT
		s.id AS sid, s.ses_id, s.data, s.returnto,
		t.id AS theme_id, t.lang, t.name AS theme_name, t.locale, t.theme, t.pspell_lang,
		u.alias, u.posts_ppg, u.time_zone, u.sig, u.last_visit, u.last_read, u.cat_collapse_status, u.users_opt,
		u.ignore_list, u.ignore_list, u.buddy_list, u.id, u.group_leader_list, u.email, u.login
	FROM {SQL_TABLE_PREFIX}ses s
		INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=(CASE WHEN s.user_id>2000000000 THEN 1 ELSE s.user_id END)
		INNER JOIN {SQL_TABLE_PREFIX}themes t ON t.id=u.theme
	WHERE '.$q_opt);
}

function ses_anon_make()
{
	do {
		$uid = 2000000000 + mt_rand(1, 147483647);
		$ses_id = md5($uid . __request_timestamp__ . getmypid());
	} while (!($id = db_li("INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id, time_sec, sys_id, user_id) VALUES ('".$ses_id."', ".__request_timestamp__.", '".ses_make_sysid()."', ".$uid.")", $ef, 1)));

	/* when we have an anon user, we set a special cookie allowing us to see who referred this user */
	if (isset($_GET['rid']) && !isset($_COOKIE['frm_referer_id']) && $GLOBALS['FUD_OPT_2'] & 8192) {
		setcookie($GLOBALS['COOKIE_NAME'].'_referer_id', $_GET['rid'], __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}
	setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return ses_get($id);
}

function ses_update_status($ses_id, $str=null, $forum_id=0, $ret='')
{
	q('UPDATE {SQL_TABLE_PREFIX}ses SET forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action='.($str ? "'".addslashes($str)."'" : 'NULL').', returnto='.(!is_int($ret) ? strnull(addslashes($_SERVER['QUERY_STRING'])) : 'returnto').' WHERE id='.$ses_id);
}

function ses_putvar($ses_id, $data)
{
	$cond = is_int($ses_id) ? 'id='.$ses_id : "ses_id='".$ses_id."'";

	if (empty($data)) {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=NULL WHERE '.$cond);
	} else {
		q("UPDATE {SQL_TABLE_PREFIX}ses SET data='".addslashes(serialize($data))."' WHERE ".$cond);
	}
}

function ses_delete($ses_id)
{
	if (!($GLOBALS['FUD_OPT_2'] & 256)) {
		q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='.$ses_id);
	}
	setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return 1;
}
?>