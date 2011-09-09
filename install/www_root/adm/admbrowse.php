<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function bit_test($val, $mask)
{
	return (($val & $mask) == $mask) ? $mask : 0;
}

function mode_string($mode, $de)
{
	/* Determine string mode 01234567890. */
	$mode_str = 'drwxrwxrwxt';

	if (!is_dir($de)) {	/* directory */
		$mode_str[0] = '-';
	}
	if (!bit_test($mode, 00400)) {	/* owner read */
		$mode_str[1] = '-';
	}
	if (!bit_test($mode, 00200)) {	/* owner write */
		$mode_str[2] = '-';
	}
	if (!bit_test($mode, 00100)) {	/* owner exec */
		$mode_str[3] = '-';
	}
	if (bit_test($mode, 0004000)) {	/* setuid */
		$mode_str[3] = 's';
	}
	if (!bit_test($mode, 00040)) {	/* group read */
		$mode_str[4] = '-';
	}
	if (!bit_test($mode, 00020)) {	/* group write */
		$mode_str[5] = '-';
	}
	if (!bit_test($mode, 00010)) {	/* group exec */
		$mode_str[6] = '-';
	}
	if (bit_test($mode, 0002000)) {	/* setgid */
		$mode_str[6] = 's';
	}
	if (!bit_test($mode, 00004)) {	/* world read */
		$mode_str[7] = '-';
	}
	if (!bit_test($mode, 00002)) {	/* world write */
		$mode_str[8] = '-';
	}
	if (!bit_test($mode, 00001)) {	/* world exec */
		$mode_str[9] = '-';
	}
	if (!bit_test($mode, 0001000)) {	/* sticky (warning: NOT POSIX) */
		$mode_str[10] = '-';
	}

	return $mode_str;
}

if (!extension_loaded('posix')) {
	function posix_getpwuid($id)
	{
		return array('name' => $id);
	}

	function posix_getgrgid($id)
	{
		return array('name' => $id);
	}
}

/* main */
	if (isset($_POST['btn_mini_cancel']) || isset($_GET['btn_mini_cancel'])) {
		exit('<html><script>window.close();</script></html>');
	}

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('tar.inc', true);
	fud_use('file_adm.inc', true);
	fud_use('logaction.inc');

	/* Figure out the ROOT paths based on the location of web browseable dir & data dir. */
	$ROOT_PATH[0] = realpath($GLOBALS['WWW_ROOT_DISK']);
	$ROOT_PATH[1] = realpath($GLOBALS['DATA_DIR']);

	$cur_dir = realpath(isset($_POST['cur']) ? $_POST['cur'] : (isset($_GET['cur']) ? $_GET['cur'] : $ROOT_PATH[0]));
	$dest = isset($_POST['dest']) ? basename($_POST['dest']) : (isset($_GET['dest']) ? basename($_GET['dest']) : '');

	/* Ensure the specified path is within the forum directories (security check). */
	if (strpos($cur_dir, $ROOT_PATH[1]) !== 0 && strpos($cur_dir, $ROOT_PATH[0]) !== 0) {
		$cur = $cur_dir = $ROOT_PATH[0];
		$dest = '';
	}

	/* Download file code. */
	if (isset($_GET['down']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		if (is_file($cur_dir .'/'. $dest)) {
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename='. $dest);
			header('Content-Length: '. filesize($cur_dir .'/'. $dest));
			header('Connection: close');
			fud_readfile($cur_dir .'/'. $dest);
		} else {
			header('Content-type: application/x-tar');
			header('Content-Disposition: attachment; filename='. $dest .'.tar');
			header('Connection: close');
			echo make_tar($cur_dir .'/'. $dest);
		}
		exit;
	}

	/* Delete file/directory code. */
	if (isset($_GET['del']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		if ($dest == '.' || $dest == '..') {
			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
			echo '<h2>File/Directory Deletion</h2>';
			echo errorify('ERROR: You cannot delete . or ..');
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
		if (isset($_GET['del_confirmed'])) {
			if (@is_dir($cur_dir .'/'. $dest) && !fud_rmdir($cur_dir .'/'. $dest, true)) {
				define('popup', 1);
				echo '<h2>File/Directory Deletion</h2>';
				require($WWW_ROOT_DISK .'adm/header.php');
				echo errorify('ERROR: failed to remove directory '. $cur_dir .'/'. $dest);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
			} else if (@is_file($cur_dir .'/'. $dest) && !unlink($cur_dir .'/'. $dest)) {
				define('popup', 1);
				require($WWW_ROOT_DISK .'adm/header.php');
				echo '<h2>File/Directory Deletion</h2>';
				echo errorify('ERROR: failed to remove file '. $cur_dir .'/'. $dest);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
			} else {
				logaction(_uid, 'Deleted file/directory', 0, $cur_dir .'/'. $dest);
				exit('<html><script>window.opener.location = \'admbrowse.php?'. __adm_rsidl .'&cur='. urlencode($cur_dir) .'\'; window.close();</script></html>');
			}
		} else {
			$file = $cur_dir .'/'. $dest;
			$type = @is_dir($file) ? 'directory' : 'file';

			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
		?>
			<h2>File/Directory Deletion</h2>
			<p>Are you sure you want to delete <?php echo $type.' <span style="color:red"><b>'.$file.'</b></span>'; ?></p>
			<form method="get" action="admbrowse.php">
			<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" />
			<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
			<input type="hidden" name="del" value="1" />
			<?php echo _hs; ?>
			<div align="center">
				<input type="submit" name="btn_mini_cancel" value="No" /> 
				<input type="submit" name="del_confirmed" value="Yes" />
			</div>
			</form>
		<?php
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
	}

	/* Rename file. */
	if (isset($_GET['rename']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		if ($dest == '.' || $dest == '..') {
			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
			echo '<h2>Rename File</h2>';
			echo errorify('ERROR: You cannot rename . or ..');
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
		if (isset($_GET['rename_confirmed'], $_GET['new_dest'])) {
			$new_dest = $_GET['new_dest'];
			if (@is_writeable($cur_dir .'/'. $dest) && !rename($cur_dir .'/'. $dest, $cur_dir .'/'. $new_dest)) {
				define('popup', 1);
				require($WWW_ROOT_DISK .'adm/header.php');
				echo '<h2>Rename File</h2>';
				echo errorify('ERROR: failed to rename file '. $cur_dir .'/'. $dest);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
			} else {
				logaction(_uid, 'Renamed file/directory to '. $new_dest, 0, $dest);
				exit('<html><script>window.opener.location = \'admbrowse.php?'. __adm_rsidl .'&cur='. urlencode($cur_dir) .'\'; window.close();</script></html>');
			}
		} else {
			$file = $cur_dir .'/'. $dest;
			$type = @is_dir($file) ? 'directory' : 'file';

			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
		?>
			<h2>Rename File</h2>
			<p>Rename <?php echo $type .' <span style="color:red"><b>'. $file .'</b></span>'; ?>:</p>
			<form method="get" action="admbrowse.php">
			<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" />
			<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
			<input type="hidden" name="rename" value="1" />
			New name: <input type="text" name="new_dest" value="<?php echo $dest; ?>" />
			<?php echo _hs; ?>
			<div align="center"><input type="submit" name="btn_mini_cancel" value="No" /> <input type="submit" name="rename_confirmed" value="Yes" /></div>
			</form>
		<?php
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
	}

	/* Change file/directory mode. */
	if (isset($_GET['chmod'])) {
		$file = $cur_dir .'/'. $dest;
		$st = stat($file);
		if (!isset($st[2])) {
			$st[2] = $st['mode'];
		}
		$mode_o = sprintf('%o', 0x0FFF & $st[2]);

		define('popup', 1);
		require($WWW_ROOT_DISK .'adm/header.php');
?>
		<h2>Change File Permissions</h2>
		<?php echo $file .' is currenly <b>'. mode_string($st[2], $file) .' ('. $mode_o .')</b>'; ?><br />
		change it to:<br />
		<form method="post" action="admbrowse.php">
		<?php echo _hs; ?>
		<input type="hidden" name="chmod" value="1" />
		<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" />
		<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
		<table border="0">
		<tr><td>Group:</td><td>Read</td><td>Write</td><td>Execute</td></tr>
		<tr><td>Owner:</td>
			<td><?php draw_checkbox('oread',  0400, bit_test($st[2], 0400)); ?></td>
			<td><?php draw_checkbox('owrite', 0200, bit_test($st[2], 0200)); ?></td>
			<td><?php draw_checkbox('oexec',  0100, bit_test($st[2], 0100)); ?></td></tr>
		<tr><td>Group:</td>
			<td><?php draw_checkbox('gread',  0040, bit_test($st[2], 0040)); ?></td>
			<td><?php draw_checkbox('gwrite', 0020, bit_test($st[2], 0020)); ?></td>
			<td><?php draw_checkbox('gexec',  0010, bit_test($st[2], 0010)); ?></td></tr>
		<tr><td>World:</td>
			<td><?php draw_checkbox('wread',  0004, bit_test($st[2], 0004)); ?></td>
			<td><?php draw_checkbox('wwrite', 0002, bit_test($st[2], 0002)); ?></td>
			<td><?php draw_checkbox('wexec',  0001, bit_test($st[2], 0001)); ?></td></tr>
		<tr><td colspan="4"><?php draw_checkbox('setuid', 0004000, bit_test($st[2], 0004000)); ?> setuid</td></tr>
		<tr><td colspan="4"><?php draw_checkbox('setgid', 0002000, bit_test($st[2], 0002000)); ?> setgid</td></tr>
		<tr><td colspan="4"><?php draw_checkbox('sticky', 0001000, bit_test($st[2], 0001000)); ?> sticky</td></tr>
		<tr><td colspan="4" align="right"><input type="submit" name="btn_submit" value="Apply" /> <input type="submit" name="btn_mini_cancel" value="Cancel" /></td></tr>
		</table>
		</form>
<?php
		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}
	if (isset($_POST['chmod']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		$file = $cur_dir .'/'. $dest;
		$perm_bits = array('oread', 'owrite', 'oexec', 'gread', 'gwrite', 'gexec', 'wread', 'wwrite', 'wexec', 'setuid', 'setgid', 'sticky');
		$new_mode = 0;
		foreach ($perm_bits as $v) {
			if (isset($_POST[$v])) {
				$new_mode |= $_POST[$v] + 0;
			}
		}
		if (!@chmod($file, $new_mode)) {
			exit('<html>Unable to chmod <b>'. $file .'</b><br /><a href="#" onclick="window.close();">close</a></html>');
		} else {
			logaction(_uid, 'Changed file mode to '. $new_mode, 0, $file);
			exit('<html><script>window.opener.location = \'admbrowse.php?'. __adm_rsidl .'&cur='. urlencode($cur_dir) .'\'; window.close();</script></html>');
		}
	}

	/* Print header & menu. No code for popup windows beyond this point. */
	require($WWW_ROOT_DISK .'adm/header.php');

	/* Directory creation code. */
	if (isset($_GET['btn_mkdir']) && !empty($_GET['mkdir'])) {
		$u = umask(0);
		if (!@mkdir($cur_dir .'/'. basename($_GET['mkdir']), ($FUD_OPT_2 & 8388608 ? 0700 : 0777))) {
			echo errorify('ERROR: failed to create '. $cur_dir .'/'. basename($_GET['mkdir']) .'.');
		} else {
			logaction(_uid, 'Created directory', 0, $cur_dir .'/'. basename($_GET['mkdir']));
			echo successify('Directory '. $cur_dir .'/'. basename($_GET['mkdir']) .' successfully created.');
		}
		umask($u);
	}

	/* File upload code. */
	if (isset($_FILES['fname'])) {
		if ($_FILES['fname']['error'] == UPLOAD_ERR_OK) {
			$fdest = !empty($_POST['d_name']) ? $_POST['d_name'] : $_FILES['fname']['name'];
			$fdest = $cur_dir .'/'. basename($fdest);
			if (move_uploaded_file($_FILES['fname']['tmp_name'], $fdest)) {
				@chmod($fdest, ($FUD_OPT_2 & 8388608 ? 0600 : 0666));
				logaction(_uid, 'Uploaded file', 0, $fdest);
				echo successify('File <i>'. basename($fdest) .'</i> ('. number_format($_FILES['fname']['size'] / 1024, 2) .'KB) was successfully uploaded.');
				if (preg_match('/src|thm/', $fdest)) {
					echo successify('Rebuild your themes from the <a href="admthemes.php?'.__adm_rsid .'">Theme Manager</a> to see the changes.');
				} else if (preg_match('/theme|cache/', basename($fdest))) {
					echo errorify('WARNING: This is a cache directory. Your upload will be removed/overwritten if you rebuild or apply theme changes.');
				}
			} else {
				echo errorify('Unable to move file.');
			}
		} else {
			switch ($_FILES['fname']['error']) {
				case UPLOAD_ERR_INI_SIZE:
					echo errorify('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
					continue;
				case UPLOAD_ERR_FORM_SIZE:
					echo errorify('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
					continue;
				case UPLOAD_ERR_PARTIAL:
					echo errorify('The uploaded file was only partially uploaded.');
					continue;
				case UPLOAD_ERR_NO_FILE:
					echo errorify('No file was uploaded.');
					continue;
				case UPLOAD_ERR_NO_TMP_DIR:
					echo errorify('Missing a temporary folder.');
					continue;
				case UPLOAD_ERR_CANT_WRITE:
					echo errorify('Failed to write file to disk.');
					continue;
				case UPLOAD_ERR_EXTENSION:
					echo errorify('File upload stopped by extension.');
					continue;
				default:
					echo errorify('Unknown upload error. Please try again.');
			}
		}
	}

	/* View file code. */
	if (isset($_GET['view']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		$file = str_replace('\\', '/', $cur_dir .'/'. $dest);
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if ($dest == 'fudforum_archive' || in_array($ext, array('atch', 'gz', 'zip', 'tar', 'db'))) {
			echo errorify('Cannot view binary file. Do you want to <a href="admbrowse.php?down=1&amp;cur='. $cur_dir .'&amp;dest='. $dest .'&amp;'. __adm_rsid .'">download</a> it?');
		} elseif (in_array($ext, array('gif', 'jpg', 'jpeg', 'png')) && strpos($file, $WWW_ROOT_DISK) !== FALSE) {
			echo '<h2>View image: '. $dest .'</h2>';
			echo '<table border="1" cellpadding="25"><tr><td>';
			echo '<img src="'. $WWW_ROOT . substr($file, strlen($WWW_ROOT_DISK)) .'">';
			echo '</td></tr></table>';
			echo '<p><a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($cur_dir) .'">&laquo; Back to file manager</a></p>';
			exit;
		} elseif (is_file($file) && is_readable($file)) {
			$raw = (isset($_GET['raw']) && $_GET['raw'] == 1) ? 1 : 0;

			echo '<h2>View file: '. $dest .'</h2>';
			echo '<div style="font-size: small;">';
			echo '[ <a href="admbrowse.php?edit=1&amp;dest='. urlencode($dest) .'&amp;cur='. urlencode($cur_dir) .'&smp;'. __adm_rsid .'">Edit</a> ] ';
			echo '[ <a href="admbrowse.php?down=1&amp;dest='. urlencode($dest) .'&amp;cur='. urlencode($cur_dir) .'&smp;'. __adm_rsid .'">Download</a> ] ';
			if (function_exists('highlight_file')) {	// May be disabled.
				echo '[ <a href="admbrowse.php?view=1&amp;raw='. ($raw ? 0 : 1) .'&amp;dest='. urlencode($dest) .'&amp;cur='. urlencode($cur_dir) .'&smp;'. __adm_rsid .'">Toggle syntax highlighting</a> ]';
			}
			echo '</div>';
			echo '<code><pre>';
			if ($raw) {
				highlight_file($file);
			} else {	
				echo '<br />'. htmlentities(file_get_contents($file)) .'<br />&nbsp;';
			}
			echo '</pre></code>';
			echo '<p><a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($cur_dir) .'">&laquo; Back to file manager</a></p>';
			exit;
		} else {
			echo errorify('File not found: '. $file);
		}
	}

	/* Save file after edit. */
	if (isset($_POST['edit_save'], $_POST['edit_content'])) {
		$file = str_replace('\\', '/', $cur_dir .'/'. $dest);
		if (file_put_contents($file, $_POST['edit_content'], LOCK_EX) === false) {
			pf(errorify('Unable to save file!'));
		} else {
			pf(successify('File saved.'));
			logaction(_uid, 'Edited file', 0, $file);	
		}
		$_GET['edit'] = 1; // Return to the edit screen.
	}

	/* Edit file. */
	if (isset($_GET['edit']) && $dest && @file_exists($cur_dir .'/'. $dest)) {
		$file = str_replace('\\', '/', $cur_dir .'/'. $dest);
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if ($dest == 'fudforum_archive' || in_array($ext, array('gif', 'jpg', 'png', 'atch', 'gz', 'zip', 'tar', 'db'))) {
			echo errorify('Cannot edit binary file. Do you want to <a href="admbrowse.php?down=1&amp;cur='. $cur_dir .'&amp;dest='. $dest .'&amp;'. __adm_rsid .'">download</a> it?');
		} elseif (is_file($file) && is_writeable($file)) {
			echo '<h2>Edit file: '. $dest .'</h2>';
			echo '<div style="font-size: small;">';
			echo '[ <a href="admbrowse.php?down=1&amp;dest='. urlencode($dest) .'&amp;cur='. urlencode($cur_dir) .'&smp;'. __adm_rsid .'">Download</a> ] ';
			echo '[ <a href="admbrowse.php?view=1&amp;dest='. urlencode($dest) .'&amp;cur='. urlencode($cur_dir) .'&smp;'. __adm_rsid .'">View file</a> ]';
			?>
			</div>
			<form method="post" action="admbrowse.php">
			<?php echo _hs; ?>
			<input type="hidden" name="edit" value="1" />
			<input type="hidden" name="cur"  value="<?php echo $cur_dir; ?>" />
			<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
			<textarea style="text-align: left; padding: 0px; overflow: auto;" name="edit_content" cols="120" rows="20" wrap="OFF"><?php echo file_get_contents($file); ?></textarea>
			<input type="submit" name="edit_save" value="Save">
			</form>
			<?php
			echo '<p><a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($cur_dir) .'">&laquo; Back to file manager</a></p>';
			exit;
		} else {
			echo errorify('File is not writeable: '. $file);
		}
	}
?>
<h2>File Administration System</h2>
<?php
	if (!@is_dir($cur_dir)) {
		$cur_dir = $ROOT_PATH[0];
	}

	echo 'Currently browsing: <b>'. htmlspecialchars($cur_dir) ."</b><br />\n";
	if ($ROOT_PATH[0] == $ROOT_PATH[1]) {
		if ($ROOT_PATH[0] != $cur_dir) {
			echo 'Go to: ';
			echo '[ <a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($ROOT_PATH[0]) .'" title="Navigate to '. htmlentities($ROOT_PATH[0]) .'">Forum Root Directory</a> ]<br />';
		}
	} else {
		echo 'Go to: ';
		echo '[ <a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($ROOT_PATH[0]) .'" title="Navigate to '. htmlentities($ROOT_PATH[0]) .'">Web Directory</a> ] ';
		echo '[ <a href="admbrowse.php?'. __adm_rsid .'&amp;cur='. urlencode($ROOT_PATH[1]) .'" title="Navigate to '. htmlentities($ROOT_PATH[1]) .'">Data Directory</a> ]<br />';
	}

	clearstatcache();
	if (!is_readable($cur_dir)) {
		echo errorify('PERMISSION DENINED ACCSESING '. $cur_dir);
		$cur_dir = $ROOT_PATH[0];
	}
?>
<br />

<form method="get" action="admbrowse.php"><input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" /><?php echo _hs; ?>
<fieldset>
        <legend>Create directory</legend>
<table class="datatable">
	<tr class="tiny">
		<td>New directory name:</td>
		<td><input type="text" name="mkdir" value="" /></td>
		<td align="right" colspan="2"><input type="submit" name="btn_mkdir" value="Create Directory" /></td>
	</tr>
</table>
</fieldset>
</form>

<form method="post" action="admbrowse.php" enctype="multipart/form-data">
<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" /><?php echo _hs; ?>
<fieldset>
        <legend>Upload a file</legend>
<table cellspacing="2" cellpadding="2" border="0">
	<tr class="tiny">
		<td>File to upload:</td>
		<td><input type="file" name="fname" /><input type="hidden" name="tmp_f_val" value="1" /></td>
	</tr>
	<tr class="tiny">
		<td>New file name:<br />(leave blank if want the uploaded filename to remain unchanged)</td>
		<td><input type="text" name="d_name" value="" /></td>
	</tr>
	<tr class="tiny">
		<td colspan="2" align="right"><input type="submit" name="file_upload" value="Upload File" /></td>
	</tr>
</table>
</fieldset>
</form>
<br />

<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Name</th><?php if (!preg_match('/WIN/', PHP_OS)) echo '<th>Owner</th><th>Group</th>'; ?><th>Size</th><th>Date/Time</th><th>Mode</th><th align="center">Action</th>	
</tr></thead>
<?php
	$dir_list = $file_list = array();

	// List files and directories (include '.' and '..').
	if (($files = glob(realpath($cur_dir) .'/{,.}*', GLOB_BRACE+GLOB_NOSORT))) {
		foreach ($files as $file) {
			$n = basename($file);
			if (is_dir($file)) {
				 $dir_list[] = $n;
			} else {
				 $file_list[] = $n;
			}
		}
	}

	sort($dir_list);
	sort($file_list);
	$dir_data = array_merge($dir_list, $file_list);

	$cur_enc = urlencode($cur_dir);

	foreach($dir_data as $de) {
		$fpath = realpath($cur_dir .'/'. $de);

		// Skip '..' if in a ROOT.
		if ($de == '..' && ($cur_dir == $ROOT_PATH[0] || $cur_dir == $ROOT_PATH[1])) {
			continue;
		}

		if (@is_file($fpath)) {
			$name = '<a href="admbrowse.php?view=1&dest='. htmlspecialchars($de) .'&cur='. urlencode($cur_dir) .'&amp;'. __adm_rsid .'" title="View file">'. htmlspecialchars($de) .'</a>';
			$st = stat($fpath);
		} else if (@is_dir($fpath)) {
			$name = '<a href="admbrowse.php?cur='. urlencode($fpath) .'&amp;'. __adm_rsid .'" title="Change directory">'. htmlspecialchars($de) .'</a>';
			$st = stat($fpath);
		}

		$mode = isset($st[2]) ? $st[2] : $st['mode'];
		$mode_str = mode_string($mode, $de);
		$de_enc = urlencode($de);

		$passwdent = posix_getpwuid((isset($st[4])?$st[4]:$st['uid']));
		$owner     = $passwdent['name'];
		$groupsent = posix_getgrgid((isset($st[5])?$st[5]:$st['gid']));
		$group     = $groupsent['name'];

		$time_str = fdate((isset($st[9]) ? $st[9] : $st['mtime']), 'd M Y H:i');
		$mode_o   = sprintf('%o', 0x0FFF&$mode);

		$size = round((isset($st[7])?$st[7]:$st['size'])/1024);

		if (preg_match('/(install.php|upgrade.php|unprotect.php|fudforum_archive)$/i', $fpath)) {
			echo '<tr class="field admin_fixed" style="color:red;" title="Please delete this file!">';
			echo '<td nowrap="nowrap">';
			echo '<a name="flagged"></a>';
		} else {
			echo '<tr class="field admin_fixed">';
			echo '<td nowrap="nowrap">';
		}

		if (@is_dir($fpath)) echo '<img src="style/folder.png" style="float:left;" />';
		echo $name .'</td>';

		if (!preg_match('/WIN/', PHP_OS)) {	// No onwer & group on Windows.
			echo '<td>'. $owner .'</td><td>'. $group .'</td>';
		}
		echo '<td nowrap="nowrap">'. $size .' KB</td><td>'. $time_str .'</td>';
		echo '<td>'. $mode_str.' ('. $mode_o .')</td>';

		echo '<td>';
		if (@is_readable($fpath)) {
			if (@is_writeable($fpath) && !preg_match('/WIN/', PHP_OS)) {
				echo ' [<a href="#" onclick="window.open(\'admbrowse.php?chmod=1&amp;cur='. $cur_enc .'&amp;dest='. $de_enc .'&amp;'. __adm_rsid .'\', \'chmod_window\', \'width=500,height=450,menubar=no\');" title="Change mode">chmod</a>]';
			}

			echo ' [<a href="admbrowse.php?down=1&amp;cur='. $cur_enc .'&amp;dest='. $de_enc .'&amp;'. __adm_rsid .'" title="Download">d/l</a>]';

			if (@is_file($fpath)) {
			      echo ' [<a href="admbrowse.php?edit=1&amp;cur='. $cur_enc .'&amp;dest='. $de_enc .'&amp;'. __adm_rsid .'" title="Edit">edt</a>]';
			}

			if (@is_writeable($fpath) && $de != '.' && $de != '..' && $de != '.htaccess') {
				echo ' [<a href="#" onclick="window.open(\'admbrowse.php?rename=1&amp;cur='. $cur_enc .'&amp;dest='. $de_enc .'&amp;'. __adm_rsid .'\', \'rename_window\', \'width=500,height=350,menubar=no\');" title="Rename">ren</a>]';
				echo ' [<a href="#" onclick="window.open(\'admbrowse.php?del=1&amp;cur='. $cur_enc .'&amp;dest='. $de_enc .'&amp;'. __adm_rsid .'\', \'delete_window\', \'width=500,height=350,menubar=no\');" title="Delete">del</a>]';
			}
		}
		echo '</tr>';
	}
?>
</table>

<?php
	// Print README file.
	$readme_file = $cur_dir .'/README';
	if (is_file($readme_file)) {
		echo '<br /><div class="dismiss" style="overflow:auto; border:1px dashed blue; padding: 5px; white-space:pre-wrap;"><b>README:</b> '. htmlentities(file_get_contents($readme_file)) .'</div>';
	}
?>

<?php
require($WWW_ROOT_DISK .'adm/footer.php');
?>
