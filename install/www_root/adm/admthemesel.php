<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admthemesel.php,v 1.25 2004/11/24 19:53:43 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (isset($_POST['tname'], $_POST['tlang'], $_POST['ret'])) {
		header('Location: '.$_POST['ret'].'.php?tname='.$_POST['tname'].'&tlang='.$_POST['tlang'].'&'.__adm_rsidl);
		exit;
	}

	$ret = isset($_GET['ret']) ? $_GET['ret'] : 'tmpllist';

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	list($def_thm, $def_tmpl) = db_saq('SELECT name, lang FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme_opt=3');
?>
<h3>Template Set Selection</h3>
<form method="post" action="admthemesel.php">
<input type="hidden" name="ret" value="<?php echo $ret; ?>"><?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
<td>Template Set:</td><td><select name="tname">
<?php
	if (!defined('GLOB_ONLYDIR')) { /* pre PHP 4.3.3 hack for FreeBSD and Windows */
		define('GLOB_ONLYDIR', 0);
	}

	$files = glob($GLOBALS['DATA_DIR'].'/thm/*', GLOB_ONLYDIR|GLOB_NOSORT);
	foreach ($files as $file) {
		if (!file_exists($file . '/tmpl')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_thm ? ' selected' : '').'>'.$n.'</option>';
	}
?>
</select></td>
</tr>
<tr class="field">
<td>Language:</td><td><select name="tlang">
<?php
	$files = glob(dirname($files[0]) . '/default/i18n/*', GLOB_ONLYDIR|GLOB_NOSORT);
	foreach ($files as $file) {
		if (!file_exists($file . '/msg')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_tmpl ? ' selected' : '').'>'.$n.'</option>';
	}
?>
</select></td></tr>
<?php
	echo '<tr class="fieldaction" align=right><td colspan=2><input type="submit" name="btn_submit" value="Edit"></td></td>';
?>
</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
