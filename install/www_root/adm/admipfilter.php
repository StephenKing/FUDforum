<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admipfilter.php,v 1.21 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('ipfilter.inc', true);

	/* validate the address */
	$bits = null;
	if (isset($_POST['ipaddr'])) {
		$bits = explode('.', trim($_POST['ipaddr']));
		foreach ($bits as $k => $v) {
			$bits[$k] = ($v == '..' || $v == '*' || (!$v && $v !== '0')) ? 256 : (int) $v;
		}
		for ($i=count($bits); $i < 4; $i++) {
			$bits[$i] = 256;
		}
	}
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['edit'], $_POST['btn_update']) && isset($bits)) {
		q('UPDATE '.$tbl.'ip_block SET ca='.$bits[0].', cb='.$bits[1].', cc='.$bits[2].', cd='.$bits[3].' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && isset($bits)) {
		q('INSERT INTO '.$tbl.'ip_block (ca, cb, cc, cd) VALUES ('.$bits[0].', '.$bits[1].', '.$bits[2].', '.$bits[3].')');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['del']);
	} else {
		$nada = 1;
	}
	if (!isset($nada) && db_affected()) {
		ip_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		if (__dbtype__ == 'mysql') {
			$ipaddr = q_singleval('SELECT CONCAT(ca, \'.\', cb, \'.\', cc, \'.\', cd) FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['edit']);
		} else {
			$ipaddr = q_singleval('SELECT ca || \'.\' || cb || \'.\' || cc || \'.\' || cd FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['edit']);
		}
		$ipaddr = str_replace('256', '*', $ipaddr);
		$edit = $_GET['edit'];
	} else {
		$ipaddr = $edit = '';
	}

	include($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>IP Filter System</h2>
<form name="ipf" method="post" action="admipfilter.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>IP Address</td>
		<td><input tabindex="1" type="text" name="ipaddr" value="<?php echo $ipaddr; ?>" size="15" maxLength="15"></td>
	</tr>
	<tr class="fieldaction">
		<td colspan=2 align=right>
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="btn_update" value="Update" tabindex="2">';
			} else {
				echo '<input tabindex="2" type="submit" name="btn_submit" value="Add mask">';
			}
		?>
		</td>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<script>
<!--
document.ipf.ipaddr.focus();
//-->
</script>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>IP Mask</td>
	<td>Action</td>
</tr>
<?php
	if (__dbtype__ == 'mysql') {
		$c = uq('SELECT id, CONCAT(ca, \'.\', cb, \'.\', cc, \'.\', cd) FROM '.$tbl.'ip_block');
	} else {
		$c = uq('SELECT id, ca || \'.\' || cb || \'.\' || cc || \'.\' || cd FROM '.$tbl.'ip_block');
	}
	$i = 1;
	while ($r = db_rowarr($c)) {
		$r[1] = str_replace('256', '*', $r[1]);
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr '.$bgcolor.'><td>'.$r[1].'</td><td>[<a href="admipfilter.php?edit='.$r[0].'&'.__adm_rsidl.'">Edit</a>] [<a href="admipfilter.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
