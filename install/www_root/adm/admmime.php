<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmime.php,v 1.19 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'mime WHERE id='.(int)$_GET['del']);
	}

	if (isset($_GET['edit'])) {
		list($mime_descr, $mime_mime_hdr, $mime_fl_ext, $mime_icon) = db_saq('SELECT descr, mime_hdr, fl_ext, icon FROM '.$tbl.'mime WHERE id='.(int)$_GET['edit']);
		$edit = (int)$_GET['edit'];
	} else {
		$mime_icon = $edit = $mime_descr = $mime_mime_hdr = $mime_fl_ext = '';
	}

	if (isset($_FILES['icoul']) && $_FILES['icoul']['size'] && preg_match('!\.(jpg|jpeg|gif|png)$!i', $_FILES['icoul']['name'])) {
		move_uploaded_file($_FILES['icoul']['tmp_name'], $GLOBALS['WWW_ROOT_DISK'] . 'images/mime/' . $_FILES['icoul']['name']);
		if (empty($_POST['mime_icon'])) {
			$_POST['mime_icon'] = $_FILES['icoul']['name'];
		}
	}

	if (isset($_POST['btn_update'], $_POST['edit'])) {
		q('UPDATE '.$tbl.'mime SET descr='.strnull(addslashes($_POST['mime_descr'])).', mime_hdr='.strnull(addslashes($_POST['mime_mime_hdr'])).', fl_ext='.strnull(addslashes($_POST['mime_fl_ext'])).', icon='.strnull(addslashes($_POST['mime_icon'])).' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit'])) {
		q('INSERT INTO '.$tbl.'mime (descr, mime_hdr, fl_ext, icon) VALUES ('.strnull(addslashes($_POST['mime_descr'])).', '.strnull(addslashes($_POST['mime_mime_hdr'])).', '.strnull(addslashes($_POST['mime_fl_ext'])).', '.strnull(addslashes($_POST['mime_icon'])).')');
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>MIME Management System</h2>
<table class="datatable solidtable">
<form action="admmime.php" name="frm_mime" method="post" enctype="multipart/form-data">
<?php
	echo _hs;
	if (@is_writeable($GLOBALS['WWW_ROOT_DISK'] . 'images/mime/')) {
?>
<tr class="fieldtopic">
	<td colspan=2><b>MIME Icon Upload (upload mime icons into the system)</td>
</tr>
<tr class="field">
	<td>MIME Icon Upload:<br><font size="-1">Only (.gif, *.jpg, *.png) files are supported</font></td>
	<td><input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload"></td>
	<input type="hidden" name="tmp_f_val" value="1">
</tr>
<?php } else { ?>
<tr class="fieldtopic">
	<td colspan=2><font color="#ff0000">Web server does not have write permissions to <b>'<?php echo $GLOBALS['WWW_ROOT_DISK']; ?>images/mime/'</b>, mime icon upload disabled</font></td>
</tr>
<?php } ?>
<tr><td colspan=2>&nbsp;</td></tr>

<tr class="fieldtopic">
	<td colspan=2><a name="img"><b>MIME Management</b></a></td>
</tr>

<tr class="field">
	<td>MIME Description:</td>
	<td><input type="text" name="mime_descr" value="<?php echo htmlspecialchars($mime_descr); ?>"></td>
</tr>

<tr class="field">
	<td>MIME Header:</td>
	<td><input type="text" name="mime_mime_hdr" value="<?php echo htmlspecialchars($mime_mime_hdr); ?>"></td>
</tr>

<tr class="field">
	<td>File Extension:<br><font size="-1">Files with this extension (case-insensitive) will be attributed to this MIME.</font></td>
	<td><input type="text" name="mime_fl_ext" value="<?php echo htmlspecialchars($mime_fl_ext); ?>"></td>
</tr>

<tr class="field">
	<td valign="top"><a name="mime_sel">MIME Icon:</a></td>
	<td nowrap><input type="text" name="mime_icon" value="<?php echo htmlspecialchars($mime_icon); ?>" onChange="javascript:
				if (document.frm_sml.mime_icon.value.length) {
					document.prev_icon.src='<?php echo $GLOBALS['WWW_ROOT']; ?>images/mime/' + document.frm_sml.mime_icon.value;
				} else {
					document.prev_icon.src='../blank.gif';
				}"> [<a href="#mime_sel" onClick="javascript:window.open('admmimesel.php?<?php echo __adm_rsidl; ?>', 'admmimesel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">select MIME icon</a>]</td>
</tr>

<tr class="field">
	<td valign="top">Preview Image:</td>
	<td>
		<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
		<tr><td align=center valign=center><img src="<?php echo ($mime_icon ? $GLOBALS['WWW_ROOT'] . 'images/mime/' . $mime_icon : '../blank.gif'); ?>" name="prev_icon" border=0></td></tr>
		</table>
	</td>
</tr>

<tr class="fieldaction">
	<td colspan=2 align=right><input type="submit" name="btn_cancel" value="Reset">
<?php
	if (!$edit) {
		echo '<input type="submit" name="btn_submit" value="Add MIME">';
	} else {
		echo '<input type="submit" name="btn_update" value="Update"></td>';
	}
?>
	</td>
</tr>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
</table>
<p>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Icon</td>
	<td>MIME Header</td>
	<td>Description</td>
	<td>Extension</td>
	<td align="center">Action</td>
</tr>
<?php
	$c = uq('SELECT id, icon, mime_hdr, fl_ext, descr FROM '.$tbl.'mime');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2""' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.' valign="top"><td><img src="'.$GLOBALS['WWW_ROOT'].'images/mime/'.$r[1].'" border=0></td><td>'.$r[2].'</td><td>'.$r[4].'</td><td>'.$r[3].'</td><td nowrap>[<a href="admmime.php?edit='.$r[0].'&'.__adm_rsidl.'#img">Edit</a>] [<a href="admmime.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
