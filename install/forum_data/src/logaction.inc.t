<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: logaction.inc.t,v 1.12 2005/03/09 21:16:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function logaction($user_id, $res, $res_id=0, $action=null)
{
	q('INSERT INTO {SQL_TABLE_PREFIX}action_log (logtime, logaction, user_id, a_res, a_res_id)
		VALUES('.__request_timestamp__.', '.strnull($action).', '.$user_id.', '.strnull($res).', '.(int)$res_id.')');
}
?>