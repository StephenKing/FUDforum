<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: forumsel.inc.t,v 1.30 2004/12/21 16:15:31 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function tmpl_create_forum_select($frm_id, $mod)
{
	if (!isset($_GET['t']) || ($_GET['t'] != 'thread' && $_GET['t'] != 'threadt')) {
		$dest = t_thread_view;
	} else {
		$dest = $_GET['t'];
	}

	if ($mod) { /* admin optimization */
		$c = uq('SELECT f.id, f.name, c.id FROM {SQL_TABLE_PREFIX}fc_view v INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id ORDER BY v.id');
	} else {
		$c = uq('SELECT f.id, f.name, c.id
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id ' .
			(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id WHERE mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 1) > 0 '  : ' WHERE (g1.group_cache_opt & 1) > 0 ').
			'ORDER BY v.id');
	}
	$f = array($frm_id => 1);

	$oldc = $selection_options = '';
	while ($r = db_rowarr($c)) {
		if ($oldc != $r[2]) {
			while (list($k, $i) = each($GLOBALS['cat_cache'])) {
				$selection_options .= '{TEMPLATE: category_option}';
				if ($k == $r[2]) {
					break;
				}
			}
			$oldc = $r[2];
		}
		$selection_options .= '{TEMPLATE: forum_option}';
	}
	
	return '{TEMPLATE: forum_select}';
}
?>