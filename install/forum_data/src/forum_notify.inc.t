<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: forum_notify.inc.t,v 1.10 2004/11/24 19:53:35 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function is_forum_notified($user_id, $forum_id)
{
	return q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id='.$forum_id.' AND user_id='.$user_id);
}

function forum_notify_add($user_id, $forum_id)
{
	db_li('INSERT INTO {SQL_TABLE_PREFIX}forum_notify (user_id, forum_id) VALUES ('.$user_id.', '.$forum_id.')', $ret);
}

function forum_notify_del($user_id, $forum_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id='.$forum_id.' AND user_id='.$user_id);
}
?>