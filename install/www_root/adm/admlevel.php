<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admlevel.php,v 1.23 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	if (isset($_POST['lev_submit'])) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name, img, level_opt, post_count) VALUES ('".addslashes($_POST['lev_name'])."', ".strnull(addslashes($_POST['lev_img'])).", ".(int)$_POST['lev_level_opt'].", ".(int)$_POST['lev_post_count'].")");
	} else if (isset($_POST['edit'], $_POST['lev_update'])) {
		q("UPDATE ".$DBHOST_TBL_PREFIX."level SET name='".addslashes($_POST['lev_name'])."', img=".strnull(addslashes($_POST['lev_img'])).", level_opt=".(int)$_POST['lev_level_opt'].", post_count=".(int)$_POST['lev_post_count']." WHERE id=".(int)$_POST['edit']);
	}

	if (isset($_GET['edit'])) {
		$edit = (int)$_GET['edit'];
		list($lev_name, $lev_img, $lev_level_opt, $lev_post_count) = db_saq('SELECT name, img, level_opt, post_count FROM '.$DBHOST_TBL_PREFIX.'level WHERE id='.(int)$_GET['edit']);
	} else {
		$edit = $lev_name = $lev_img = $lev_level_opt = $lev_post_count = '';
	}

	if (isset($_GET['del'])) {
		q('DELETE FROM '.$DBHOST_TBL_PREFIX.'level WHERE id='.(int)$_GET['del']);
	}

	if (isset($_GET['rebuild_levels'])) {
		$pl = 2000000000;
		$c = q('SELECT id, post_count FROM '.$DBHOST_TBL_PREFIX.'level ORDER BY post_count DESC');
		while ($r = db_rowarr($c)) {
			q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET level_id='.$r[0].' WHERE posted_msg_count<'.$pl.' AND posted_msg_count>='.$r[1]);
			$pl = $r[1];
		}
		unset($c);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Rank Manager</h2>
<div align="center"><font size="+1" color="#ff0000">If you've made any modification to the user ranks<br>YOU MUST RUN CACHE REBUILDER by &gt;&gt; <a href="admlevel.php?rebuild_levels=1&<?php echo __adm_rsidl; ?>">clicking here</a> &lt;&lt;</font></div>
<form method="post" name="lev_form" action="admlevel.php">
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Rank Name</td>
		<td><input type="text" name="lev_name" value="<?php echo htmlspecialchars($lev_name); ?>"></td>
	</tr>
	<tr class="field">
		<td>Rank Image<br><font size="-1">URL to the image<font></td>
		<td><input type="text" name="lev_img" value="<?php echo htmlspecialchars($lev_img); ?>"><br>
	</tr>

	<tr class="field">
		<td>Which Image to Show:</td>
		<td><?php draw_select("lev_level_opt", "Avatar & Rank Image\nAvatar Only\nRank Image Only", "0\n1\n2", $lev_level_opt); ?></td>
	</tr>

	<tr class="field">
		<td>Post Count</td>
		<td><input type="text" name="lev_post_count" value="<?php echo $lev_post_count; ?>" size=11 maxLength=10></td>
	</tr>

	<tr>
		<td colspan=2 class="fieldaction" align=right>
<?php
			if (!$edit) {
				echo '<input type="submit" name="lev_submit" value="Add Level">';
			} else {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="lev_update" value="Update">';
			}
?>
		</td>
	</tr>
</table>
</form>

<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Name</td>
	<td>Post Count</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT id, name, post_count FROM '.$DBHOST_TBL_PREFIX.'level ORDER BY post_count');
	$i = 1;
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.'><td>'.$r->name.'</td><td align=center>'.$r->post_count.'</td><td><a href="admlevel.php?edit='.$r->id.'&'.__adm_rsidl.'">Edit</a> | <a href="admlevel.php?del='.$r->id.'&'.__adm_rsidl.'">Delete</a></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
