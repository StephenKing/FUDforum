<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admimport.php,v 1.69 2009/05/08 06:11:16 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	error_reporting(E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);
	@ini_set('display_errors', '1');
	@ini_set("memory_limit", "100M");
	@set_time_limit(18000);

	/* Uncomment the line below if you wish to import data without authentication
	 * This is useful if the previous import had failed resulting in the loss of old SQL data
	**/
	// define('recovery_mode', 1);

	require('./GLOBALS.php');
	fud_use('glob.inc', true);

	if (defined('recovery_mode')) {
		fud_use('db.inc');
	} else {
		fud_use('adm.inc', true);
	}

function resolve_dest_path($path)
{
	$path = str_replace(array('WWW_ROOT_DISK','DATA_DIR'), 
				array($GLOBALS['WWW_ROOT_DISK'], $GLOBALS['DATA_DIR']), $path);
	$dir = dirname($path);
	if (!@is_dir($dir)) {
		while ($dir && $dir != "/" && !@is_dir($dir)) {
			$dirs[] = $dir;
			$dir = dirname($dir);
		}
		$dirs = array_reverse($dirs);
		foreach ($dirs as $d) {
			if (!mkdir($d, 0755)) {
				exit("Failed to create {$d} directory, check file permissions<br />\n");
			}
		}
	}

	return $path;
}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($_POST['path'])) {
		if (!@is_readable($_POST['path'])) {
			if (!@file_exists($path)) {
				$path_error = '<font color="#ff0000"><b>'.$_POST['path'].'</b> file does not exist.</font><br />';
			} else {
				$path_error = '<font color="#ff0000">the webserver has no permission to open <b>'.$_POST['path'].'</b> for reading</font><br />';
			}
		} else if (($gz_file = preg_match('!\.gz$!', $_POST['path'])) && !extension_loaded('zlib')) {
			$path_error = '<font color="#ff0000">The file <b>'.$_POST['path'].'</b> is compressed using gzip & your PHP does not have gzip extension install. Please decompress the file yourself and try again.</font><br />';
		} else {
			if (!$gz_file) {
				$fp = fopen($_POST['path'], 'rb');
				$getf = 'fgets';
				$readf = 'fread';
				$closef = 'fclose';
				$feoff = 'feof';
			} else {
				$fp = gzopen($_POST['path'], 'rb');
				$getf = 'gzgets';
				$readf = 'gzread';
				$closef = 'gzclose';
				$feoff = 'gzeof';
			}
			/* skip to the start of data files */
			while ($getf($fp, 1024) != "----FILES_START----\n" && !$feoff($fp));

			/* handle data files */
			echo "Restoring data files...<br />\n";
			@ob_flush(); flush();
			while (($line = $getf($fp, 1000000)) && $line != "----FILES_END----\n") {
				/* each file is preceeded by a header ||path||size|| */
				if (strncmp($line, '||', 2)) {
					continue;
				}
				list(,$path,$size,) = explode("||", $line);

				if ($path == 'WWW_ROOT_DISK/adm/admimport.php' ) {
					// Skip admimport.php, don't overwrite the running script
					continue;
				}

				$path = resolve_dest_path($path);
				if (!($fd = fopen($path, 'wb'))) {
					echo "WARNING: couldn't create '".$path."'<br />\n";
					if ($readf == 'gzread') {
						gzseek($fp, (gztell($fp) + $size));
					} else {
						fseek($fp, $size, SEEK_CUR);
					}
				} else {
					if ($size < 1) { /* empty file */
						continue;
					}
					if ($size < 2000000) {
						fwrite($fd, $readf($fp, $size));
					} else {
						$n_r = floor($size / 2000000);
						$rem = $size - 2000000 * $n_r;
						for ($i = 0; $i < $n_r; $i++) {
							fwrite($fd, $readf($fp, 2000000));
						}
						if ($rem) {
							fwrite($fd, $readf($fp, $rem));
						}
					}
					fclose($fd);
				}
			}

			/* skip to the start of the SQL code */
			while ($getf($fp, 1024) != "----SQL_START----\n" && !$feoff($fp));

			echo "Drop database tables...<br />\n";
			@ob_flush(); flush();
			/* clear SQL data */
			foreach(get_fud_table_list() as $v) {
				q('DROP TABLE '.$v);
			}

			/* if we are dealing with pgSQL drop all sequences too */
			if (__dbtype__ == 'pgsql') {
				$c = q("SELECT relname from pg_class where relkind='S' AND relname ~ '^".str_replace("_", '\\\\_', $DBHOST_TBL_PREFIX)."'");
				while($r = db_rowarr($c)) {
					q('drop sequence '.$r[0]);
				}
				unset($c);
			}

			/* check if MySQL version > 4.1.2 */
			if (__dbtype__ == 'mysql') {
				$my412 = version_compare(q_singleval("SELECT VERSION()"), '4.1.2', '>=');
			} else {
				$my412 = 0;
			}

			$idx = array();

			/* create table structure */
			echo "Create database tables...<br />\n";
			@ob_flush(); flush();
			while (($line = $getf($fp, 1000000)) && !$feoff($fp)) {
				if (($line = trim($line))) {
					if (!strncmp($line, 'DROP', 4) || !strncmp($line, 'ALTER', 5)) {
						continue; // no need to drop tables, already gone
					}

					if (strncmp($line, 'CREATE', 6)) {
						break;
					}

					// speed up inserts, create indexes later
					if (strpos($line, ' INDEX ') !== false) {
						$idx[] = $line;
						continue;
					}

					if (__dbtype__ != 'mysql') {
						$line = strtr($line, array('BINARY'=>'', 'INT NOT NULL AUTO_INCREMENT'=>'SERIAL'));
					} else if ($my412 && !strncmp($line, 'CREATE TABLE', strlen('CREATE TABLE'))) {
						/* for MySQL 4.1.2+ we need to specify a default charset */
						$line .= " DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci";
					}

					q(str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $line));
				}
			}
			echo "Loading database tables...<br />\n";
			@ob_flush(); flush();
			$r = $i = 0; $tmp = $pfx = ''; $m = __dbtype__ == 'mysql'; $p = __dbtype__ == 'pgsql';
			do {
				if (($line = trim($line))) {
					if ($line{0} != '(') {
						if ($tmp) {
							q($pfx.substr($tmp, 0, -1));
							$tmp = '';
						}
						$pfx = 'INSERT INTO '.$DBHOST_TBL_PREFIX.$line.' VALUES ';
						$r = $line != 'mime';
						continue;
					}
					if (!$m) {
						if ($r && $p) {
							$line = str_replace("''", 'NULL', $line);
						}
						q($pfx.$line);
					} else {
						$tmp .= $line;
						if ($i && !($i % 1000)) {
							q($pfx.$tmp);
							$tmp = '';
						} else {
							$tmp .= ',';
						}
					}

					if ($i && !($i % 10000)) {
						echo 'Processed '.$i.' queries<br />';
						@ob_flush(); flush();
					}
					++$i;
				}
			} while (($line = $getf($fp, 1000000)) && $line != "----SQL_END----\n");

			if ($tmp) {
				q($pfx.substr($tmp, 0, -1));
				unset($tmp);
			}

			echo "Creating indexes...<br />\n";
			@ob_flush(); flush();
			foreach ($idx as $v) {
				q(str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $v));
			}

			if (__dbtype__ == 'pgsql') {
				/* we need to restore sequence numbers for postgreSQL */
				foreach(db_all("SELECT relname FROM pg_class WHERE relkind='S' AND relname LIKE '".addcslashes($DBHOST_TBL_PREFIX, '_')."%\_id\_seq'") as $v) {
					if (!($m = q_singleval('SELECT MAX(id) FROM '.basename($v, '_id_seq')))) {
						$m = 1;
					}
					q("SELECT setval('{$v}', {$m})");
				}
			}

			/* handle importing of GLOBAL options */
			echo "Import GLOBAL settings...<br />\n";
			@ob_flush(); flush();			
			eval(trim($readf($fp, 100000))); // should be enough to read all options in one shot
			change_global_settings($global_vals);

			/* Try to restore the current admin's account by seeing if he exists in the imported database */
			if (($uid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".$usr->login."' AND users_opt>=1048576 AND (users_opt & 1048576) > 0"))) {
				q('INSERT INTO '.$DBHOST_TBL_PREFIX.'ses (ses_id, user_id, time_sec) VALUES(\''.$usr->ses_id.'\', '.$uid.', '.__request_timestamp__.')');
			} else {
				echo '<font color="#ff0000">Your current login ('.htmlspecialchars($usr->login).') is not found in the imported database.<br />Therefor you\'ll need to re-login once the import process is complete<br /></font>';
			}

			/* we now need to correct cached paths for file attachments and avatars */
			echo "Correcting Avatar Paths...<br />\n";
			@ob_flush(); flush();			
			if (($old_path = q_singleval('SELECT location FROM '.$DBHOST_TBL_PREFIX.'attach LIMIT 1'))) {
				preg_match('!(.*)/!', $old_path, $m);
				q('UPDATE '.$DBHOST_TBL_PREFIX.'attach SET location=REPLACE(location, '._esc($m[1]).', '._esc($GLOBALS['FILE_STORE']).')');
			}

			echo "Correcting Attachment Paths...<br />\n";
			@ob_flush(); flush();			
			if (($old_path = q_singleval('SELECT avatar_loc FROM '.$DBHOST_TBL_PREFIX.'users WHERE users_opt>=8388608 AND (users_opt & (8388608|16777216)) > 0 LIMIT 1'))) {
				preg_match('!http://(.*)/images/!', $old_path, $m);
				preg_match('!//(.*)/!', $GLOBALS['WWW_ROOT'], $m2);

				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET avatar_loc=REPLACE(avatar_loc, '._esc($m[1]).', '._esc($m2[1]).') WHERE users_opt>=8388608 AND (users_opt & (8388608|16777216)) > 0');
			}

			echo "Recompiling Templates...<br />\n";
			@ob_flush(); flush();

			fud_use('compiler.inc', true);
			$c = uq('SELECT theme, lang, name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE theme_opt>=1 AND (theme_opt & 1) > 0');
			while ($r = db_rowarr($c)) {
				compile_all($r[0], $r[1], $r[2]);
			}
			unset($c);

			echo '<b>Import process is now complete</b><br /><br />';
			echo '<div class="tutor">To finalize the import process you should now run the <nbsp>>> <b><a href="consist.php?'.__adm_rsid.'">consistency checker</a></b> <<</nbsp>.</div>';
			require($WWW_ROOT_DISK . 'adm/admclose.html');
			exit;
		}
	}
?>
<h2>Import forum data</h2>
<div class="alert">Note that the import process will REMOVE ALL current forum data (all tables with <?php echo $DBHOST_TBL_PREFIX; ?> prefix) and replace it with the one from the file you enter.<br /><br />Please <a href="admdump.php?<?php echo __adm_rsid; ?>">BACKUP</a> your data before imporing!</div>

<?php
$datadumps = (glob("$TMP*.fud*"));
if ($datadumps) { 
?>
	<table class="datatable solidtable">
	<tr><td class="fieldtopic">Available datadumps:</td></tr>
	<?php foreach ($datadumps as $datadump) { ?>
		<tr class="field admin_fixed"><td><?php echo $datadump; ?> [ <a href="javascript://" onclick="document.admimport.path.value='<?php echo $datadump; ?>';">use</a> ]</td></tr>
	<?php } ?>
	<tr class="resultrow2 tiny"><td>[ <a href="admbrowse.php?down=1&cur=<?php echo urlencode(dirname($datadump)); ?>&<?php echo __adm_rsid; ?>">Manage files</a> ]</td></tr>
	</table><br />
<?php } ?>

<form method="post" action="admimport.php" id="admimport" name="admimport">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Import Data Path:<br /><font size="-1">Location on the drive, where the file your wish to import FUDforum data from is located.</font></td>
	<td><?php if (isset($path_error)) { echo $path_error; $path = $_POST['path']; } else { $path = ''; } ?><input type="text" value="<?php echo $path; ?>" name="path" size="40" /></td>
</tr>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Import Data" /></td></tr>
</table>
</form>

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
