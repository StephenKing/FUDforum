<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admforumicons.php,v 1.19 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	/*
	 * The presense of the which_dir variable tells us whether we are editing
	 * forum icons or message icons.
	 */
	if (!empty($_GET['which_dir']) || !empty($_POST['which_dir'])) {
		$which_dir = '1';
		$ICONS_DIR = 'images/message_icons';
		$form_descr = 'Message Icons';
	} else {
		$which_dir = '';
		$ICONS_DIR = 'images/forum_icons';
		$form_descr = 'Forum Icons';
	}

	if (isset($_FILES['iconfile']) && $_FILES['iconfile']['size'] && preg_match('!\.(gif|png|jpg|jpeg)$!i', $_FILES['iconfile']['name'])) {
		move_uploaded_file($_FILES['iconfile']['tmp_name'], $WWW_ROOT_DISK . $ICONS_DIR . '/' . $_FILES['iconfile']['name']);
		/* rebuild message icon cache */
		if ($which_dir) {
			fud_use('msg_icon_cache.inc', true);
			rebuild_icon_cache();
		}
	}
	if (isset($_GET['del'])) {
		@unlink($WWW_ROOT_DISK . $ICONS_DIR . '/' . basename($_GET['del']));
		/* rebuild message icon cache */
		if ($which_dir) {
			fud_use('msg_icon_cache.inc', true);
			rebuild_icon_cache();
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2><?php echo $form_descr; ?> Administration System</h2>
<?php
	if (@is_writeable($WWW_ROOT_DISK . $ICONS_DIR)) {
?>
<form method="post" enctype="multipart/form-data" action="admforumicons.php">
<input type="hidden" name="which_dir" value="<?php echo $which_dir; ?>">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Upload Icon:<br><font size="-1">Only (*.gif, *.jpg, *.png) files are supported</font></td>
		<td><input type="file" name="iconfile"></td>
		<input type="hidden" name="tmp_f_val" value="1">
	</tr>

	<tr class="fieldaction"><td align=right colspan=2><input type="submit" name="btn_upload" value="Add"></td></tr>
</table>
</form>
<?php
	} else {
?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr class="field">
		<td align=center><font color="red"><?php echo $WWW_ROOT_DISK . $ICONS_DIR; ?> is not writeable by the web server, file upload disabled.</td>
	</tr>
</table>
<?php
	}
?>
<table class="resulttable">
<tr class="resulttopic"><td>Icon</td><td>Action</td></tr>
<?php
	$i = 1;
	if (($files = glob($WWW_ROOT_DISK . $ICONS_DIR . '/{*.jpg,*.gif,*.png,*.jpeg}', GLOB_BRACE|GLOB_NOSORT))) {
		foreach ($files as $file) {
			$de = basename($file);
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
			echo '<tr'.$bgcolor.'><td><img src="'.$WWW_ROOT . $ICONS_DIR . '/' . $de.'"></td><td><a href="admforumicons.php?del='.urlencode($de).'&'.__adm_rsidl.'&which_dir='.$which_dir.'">Delete</a></td></tr>';
		}	
	} else if ($files === FALSE && !is_readable($WWW_ROOT_DISK . $ICONS_DIR)) {
		echo '<tr colspan="3"><td>Unable to open '.$WWW_ROOT_DISK . $ICONS_DIR.' for reading.</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
