<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: logaction.inc.t,v 1.17 2007/01/01 18:23:46 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function logaction($user_id, $res, $res_id=0, $action=null)
{
	q('INSERT INTO {SQL_TABLE_PREFIX}action_log (logtime, logaction, user_id, a_res, a_res_id)
		VALUES('.__request_timestamp__.', '.ssn($action).', '.$user_id.', '.ssn($res).', '.(int)$res_id.')');
}
?>