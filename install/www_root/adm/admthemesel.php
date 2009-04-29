<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admthemesel.php,v 1.36 2009/04/29 20:06:35 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (isset($_POST['tname'], $_POST['tlang'], $_POST['ret'])) {
		header('Location: '.$_POST['ret'].'.php?tname='.$_POST['tname'].'&tlang='.$_POST['tlang'].'&'.__adm_rsidl);
		exit;
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$ret = isset($_GET['ret']) ? $_GET['ret'] : 'tmpllist';
	if ( $ret == 'tmpllist' ) {
		echo '<h2>Template Editor</h2>';
	} else if ( $ret == 'msglist' ) {
                echo '<h2>Message Editor</h2>';
	}

	list($def_thm, $def_tmpl) = db_saq('SELECT name, lang FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme_opt=3');
?>
<p>Select a template set and language to edit:</p>
<form method="post" action="admthemesel.php">
<input type="hidden" name="ret" value="<?php echo $ret; ?>" /><?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
<td>Template Set:</td><td><select name="tname">
<?php
	if (!defined('GLOB_ONLYDIR')) { /* pre PHP 4.3.3 hack for FreeBSD and Windows */
		define('GLOB_ONLYDIR', 0);
	}

	foreach (glob($GLOBALS['DATA_DIR'].'/thm/*', GLOB_ONLYDIR|GLOB_NOSORT) as $file) {
		if (!file_exists($file . '/tmpl')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_thm ? ' selected="selected"' : '').'>'.$n.'</option>';
	}
?>
</select></td>
</tr>
<tr class="field">
<td>Language:</td><td><select name="tlang">
<?php
	foreach (glob($GLOBALS['DATA_DIR'] . 'thm/default/i18n/*', GLOB_ONLYDIR|GLOB_NOSORT) as $file) {
		if (!file_exists($file . '/msg')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_tmpl ? ' selected="selected"' : '').'>'.$n.'</option>';
	}
?>
</select></td></tr>
<tr class="fieldaction" align="right"><td colspan="2"><input type="submit" name="btn_submit" value="Edit" /></td></tr></table></form>

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
