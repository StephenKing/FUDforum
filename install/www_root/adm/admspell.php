<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admspell.php,v 1.9 2004/11/24 19:53:43 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	if (!($FUD_OPT_1 & 2097152)) {
		exit("Cannot use this control panel, your forum's spell checker is disabled.");
	}

	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$status = 0;
	if (!empty($_POST['words'])) {
		$wl = explode("\n", trim($_POST['words']));
		if (count($wl)) {
			$pspell_config = pspell_config_create($usr->pspell_lang);
			pspell_config_personal($pspell_config, $FORUM_SETTINGS_PATH."forum.pws");
			$pspell_link = pspell_new_config($pspell_config);

			foreach ($wl as $w) {
				if (($w = trim($w))) {
					pspell_add_to_personal($pspell_link, $w);
					pspell_save_wordlist($pspell_link);
					++$status;
				}
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Custom Dictionary Spell Checker</h2>
<form method="post" name="spell" action="admspell.php">
<?php
	echo _hs;
	if ($status) {
		echo '<div style="text-align: center; color: green; font-size: 125%">'.$status.' word(s) were added successfully.</div>';
	}
?>
<table class="datatable solidtable">
<tr class="tutor">
	<td>Enter custom words you want added to your personal dictionary that will<br />
	be used in addition to the native pspell/aspell dictionaries. <font size="-1">(1 word per line.)</font></td>
</tr>
<tr class="field">
	<td><textarea tabindex="1" rows=7 cols=30 name="words"></textarea></td>
</tr>
<tr class="fieldaction">
	<td align="right"><input type="submit" name="submit" value="Add Words" tabindex="2"></td>
</tr>
</table>
</form>
<script>
<!--
document.spell.words.focus();
//-->
</script>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
