<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: tabs.inc.t,v 1.20 2004/11/24 19:53:36 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

$tabs = '';
if (_uid) {
	$tablist = array(
'{TEMPLATE: tags_user_cp}'=>'uc',
'{TEMPLATE: tabs_register}'=>'register',
'{TEMPLATE: tabs_subscriptions}'=>'subscribed',
'{TEMPLATE: tabs_referrals}'=>'referals',
'{TEMPLATE: tabs_buddy_list}'=>'buddy_list',
'{TEMPLATE: tabs_ignore_list}'=>'ignore_list');

	if (!($FUD_OPT_2 & 8192)) {
		unset($tablist['{TEMPLATE: tabs_referrals}']);
	}

	if (isset($_POST['mod_id'])) {
		$mod_id_chk = $_POST['mod_id'];
	} else if (isset($_GET['mod_id'])) {
		$mod_id_chk = $_GET['mod_id'];
	} else {
		$mod_id_chk = null;
	}

	if (!$mod_id_chk) {
		if ($FUD_OPT_1 & 1024) {
			$tablist['{TEMPLATE: tabs_private_messaging}'] = 'pmsg';
		}
		$pg = ($_GET['t'] == 'pmsg_view' || $_GET['t'] == 'ppost') ? 'pmsg' : $_GET['t'];

		foreach($tablist as $tab_name => $tab) {
			$tab_url = '{ROOT}?t='.$tab.'&amp;S='.s;
			if ($tab == 'referals') {
				if (!($FUD_OPT_2 & 8192)) {
					continue;
				}
				$tab_url .= '&amp;id='._uid;
			}
			$tabs .= $pg == $tab ? '{TEMPLATE: active_tab}' : '{TEMPLATE: inactive_tab}';
		}

		$tabs = '{TEMPLATE: tablist}';
	}
}
?>