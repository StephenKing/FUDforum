<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: referals.php.t,v 1.7 2003/04/02 17:10:58 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (!isset($_GET['id']) || !(int)$_GET['id']) {
		$_GET['id'] = $usr->id;
	}

	if (!$_GET['id'] || ($p_user = db_saq('SELECT id, alias FROM {SQL_TABLE_PREFIX}users WHERE id='.(int)$_GET['id']))) {
		$ses->update('{TEMPLATE: referals_update}');

		$c = uq('SELECT alias, id, join_date, posted_msg_count, home_page FROM {SQL_TABLE_PREFIX}users WHERE referer_id='.(int)$_GET['id']);
		if (($r = @db_rowarr($c))) {
			$refered_entry_data = '';
			do {
				$pm_link = (_uid && $PM_ENABLED == 'Y') ? '{TEMPLATE: pm_link}' : '';
				$homepage_link = !empty($r[4]) ? '{TEMPLATE: homepage_link}' : '';
				$email_link = $ALLOW_EMAIL == 'Y' ? '{TEMPLATE: email_link}' : '';

				$refered_entry_data .= '{TEMPLATE: refered_entry}';
			} while (($r = db_rowarr($c)));
		} else {
			$refered_entry_data = '{TEMPLATE: no_refered}';
		}
		qf($r);
	} else {
		invl_inp_err();
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REFERALS_PAGE}
