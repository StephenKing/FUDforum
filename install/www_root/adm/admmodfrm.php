<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmodfrm.php,v 1.22 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_GET['usr_id'])) {
		$usr_id = (int)$_GET['usr_id'];
	} else if (isset($_POST['usr_id'])) {
		$usr_id = (int)$_POST['usr_id'];
	} else {
		$usr_id = '';
	}
	if (!$usr_id || !($login = q_singleval('SELECT alias FROM '.$tbl.'users WHERE id='.$usr_id))) {
		exit('<html><script language="JavaScript">window.close();</script></html>');
	}

	if (isset($_POST['mod_submit'])) {
		q('DELETE FROM '.$tbl.'mod WHERE user_id='.$usr_id);
		if (isset($_POST['mod_allow'])) {
			foreach ($_POST['mod_allow'] as $m) {
				q('INSERT INTO '.$tbl.'mod (forum_id, user_id) VALUES('.(int)$m.', '.$usr_id.')');
			}
		}

		/* mod rebuild */
		fud_use('users_reg.inc');
		rebuildmodlist();
?>
<html>
<script language="JavaScript">
	window.opener.location = 'admuser.php?act=1&usr_id=<?php echo $usr_id; ?>&<?php echo __adm_rsidl; ?>';
	window.close();
</script>
</html>
<?php
		exit;
	}
?>
<html>
<head>
<link rel="StyleSheet" href="adm.css" type="text/css">
<body class="popup">
<h3>Allowing <?php echo $login; ?> to moderate:</h3>
<form name="frm_mod" action="admmodfrm.php" method="post">
<?php echo _hs; ?>
<table class="datatable fulltable">
<?php
	$c = uq('SELECT CASE WHEN c.name IS NULL THEN \'DELETED FORUMS\' ELSE c.name END, f.name, f.id, mm.id FROM '.$tbl.'forum f LEFT JOIN '.$tbl.'cat c ON c.id=f.cat_id LEFT JOIN '.$tbl.'mod mm ON mm.forum_id=f.id AND mm.user_id='.$usr_id.' ORDER BY c.view_order, f.view_order');
	$pc = '';
	while ($r = db_rowarr($c)) {
		if ($pc != $r[0]) {
			echo '<tr class="fieldtopic"><td colspan=2>'.$r[0].'</td></tr>';
			$pc = $r[0];
		}
		echo '<tr class="field"><td><input type="checkbox" name="mod_allow[]" value="'.$r[2].'"'.($r[3] ? ' checked': '').'>'.$r[1].'</td></tr>';
	}
?>
<tr class="fieldaction">
	<td colspan=2 align=right><input type="submit" name="mod_submit" value="Apply"></td>
</tr>
</table>
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
</form>
</html>
