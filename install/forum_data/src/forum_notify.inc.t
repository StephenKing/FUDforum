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

function is_forum_notified($user_id, $forum_id)
{
	return q_singleval('SELECT 1 FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id='. $user_id .' AND forum_id='. $forum_id);
}

function forum_notify_add($user_id, $forum_id)
{
	db_li('INSERT INTO {SQL_TABLE_PREFIX}forum_notify (user_id, forum_id) VALUES ('. $user_id .', '. $forum_id .')', $ret);
}

function forum_notify_del($user_id, $forum_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id='. $user_id .' AND forum_id='. $forum_id);
}
?>