<?php
/* ���ظ ���� )�)����  	
 * First 20 bytes of linux 2.4.18, so various windows utils think
 * this is a binary file and don't apply CRLF logic
 */

/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: upgrade.php,v 1.179 2003/07/13 11:49:30 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$__UPGRADE_SCRIPT_VERSION = 1100;

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

function change_global_settings($list)
{
	$settings = file_get_contents($GLOBALS['DATA_DIR'] . 'include/GLOBALS.php');
	foreach ($list as $k => $v) {
		if (($p = strpos($settings, '$' . $k)) === FALSE) {
			$p = strpos($settings, '$ALLOW_REGISTRATION')-1;
			$settings = substr_replace($settings, "\t\$$k\t= \"$v\";\n", $p, 0);
		} else {
			$p = strpos($settings, '"', $p) + 1;
			if ($v == 'Y' || $v == 'N') {
				$settings[$p] = $v;
			} else {
				$e = strpos($settings, '";', $p);
				$settings = substr_replace($settings, $v, $p, ($e - $p));
			}
		}
	}
	$fp = fopen($GLOBALS['DATA_DIR'] . 'include/GLOBALS.php', 'w');
	fwrite($fp, $settings);
	fclose($fp);
}

function show_debug_message($msg)
{
	echo $msg . '<br>';
	flush();
}

function upgrade_error($msg)
{
	exit('<font color="red">'.$msg.'</font></body></html>');
}

function init_sql_func()
{
	if (__dbtype__ == 'mysql') {
		mysql_connect($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or upgrade_error('MySQL Error: #'.mysql_errno().' ('.mysql_error().')');
		mysql_select_db($GLOBALS['DBHOST_DBNAME']) or upgrade_error('MySQL Error: #'.mysql_errno().' ('.mysql_error().')');

		function q($query) 
		{
			$r = mysql_query($query) or upgrade_error('MySQL Error: #'.mysql_errno().' ('.mysql_error().'): '.htmlspecialchars($query));
			return $r;
		}
		
		function qf(&$r)
		{
			unset($r);
		}
		
		function db_rowobj($result)
		{
			return mysql_fetch_object($result);
		}

		function db_rowarr($result)
		{
			return mysql_fetch_row($result);
		}
		
		function q_singleval($query)
		{
			return @current(mysql_fetch_row(q($query)));
		}
		
		function get_fud_table_list()
		{
			$c = q("show tables LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
			while ($r = db_rowarr($c)) {
				$ret[] = $r[0];
			}	
			qf($c);

			return $ret;
		}
		
		function check_sql_perms()
		{
			mysql_query('DROP TABLE IF EXISTS fud_forum_upgrade_test_table');
			if (!mysql_query('CREATE TABLE fud_forum_upgrade_test_table (test_val INT)')) {
				upgrade_error('FATAL ERROR: your forum\'s MySQL account does not have permissions to create new MySQL tables.<br>Enable this functionality and restart the script.');
			}	
			if (!mysql_query('ALTER TABLE fud_forum_upgrade_test_table ADD test_val2 INT')) {
				upgrade_error('FATAL ERROR: your forum\'s MySQL account does not have permissions to run ALTER queries on existing MySQL tables<br>Enable this functionality and restart the script.');
			}	
			if (!mysql_query('DROP TABLE fud_forum_upgrade_test_table')) {
				upgrade_error('FATAL ERROR: your forum\'s MySQL account does not have permissions to run DROP TABLE queries on existing MySQL tables<br>Enable this functionality and restart the script.');
			}
		}
		
		function remove_dup_indexes($tbl_name)
		{
		        $idx_chk = $idx_chk2 = array();
        
			$r = q('SHOW INDEX FROM '.$tbl_name);
		        while ($obj = db_rowobj($r)) {
		        	if (!isset($idx_chk[$obj->Key_name.' '.$tbl_name])) {
		        		$idx_chk[$obj->Key_name.' '.$tbl_name] = '';
		        	}
		        	$idx_chk[$obj->Key_name.' '.$tbl_name] .= $obj->Column_name;
			}
		        qf($r);
                                                
		        foreach ($idx_chk as $k => $v) {
				if (isset($idx_chk2[$v])) {
					list($idx_n, $tbl_n) = explode(' ', $k);
					q('ALTER TABLE '.$tbl_n.' DROP INDEX '.$idx_n);
				} else {
	        	        	$idx_chk2[$v] = $k;
				}
			}
		}

		function match_mysql_index($table, $fields, $idx_type) 
		{
			$r = q('show index from '.$table);
			while ($obj = db_rowobj($r)) {
				if (isset($m[$obj->Key_name])) {
					$m[$obj->Key_name][0] .= ','.$obj->Column_name;
				} else {
					$m[$obj->Key_name] = array($obj->Column_name, ($obj->Non_unique ? 'INDEX' : 'UNIQUE'));
				}
			}
			qf($r);

			if (!isset($m)) {
				return;
			}

			foreach($m as $k => $index) {
				if ($index[1] == $idx_type && $index[0] == $fields) {
					return $k;
				}
			}
			return;
		}

		function mysql_row_exists($table, $row)
		{
			return q_singleval('show fields from '.$table.' LIKE \''.$row.'\'');
		}
	} else if (__dbtype__ == 'pgsql') {
		$connect_str = '';
		if (!empty($GLOBALS['DBHOST'])) {
			$connect_str .= 'host='.$GLOBALS['DBHOST'];
		}
		if (!empty($GLOBALS['DBHOST_USER'])) {
			$connect_str .= ' user='.$GLOBALS['DBHOST_USER'];
		}
		if (!empty($GLOBALS['DBHOST_PASSWORD'])) {
			$connect_str .= ' password='.$GLOBALS['DBHOST_PASSWORD'];
		}
		if (!empty($GLOBALS['DBHOST_DBNAME'])) {
			$connect_str .= ' dbname='.$GLOBALS['DBHOST_DBNAME'];
		}
		if (!($conn = pg_connect(ltrim($connect_str)))) {
			upgrade_error('Failed to establish database connection to '.$GLOBALS['DBHOST']);
		}
		define('__FUD_SQL_LNK__', $conn);
		
		function q($query)
		{
			$r = pg_query(__FUD_SQL_LNK__, $query) or upgrade_error('PostgreSQL Error: '.pg_last_error(__FUD_SQL_LNK__).'<br>Query: '.htmlspecialchars($query));
			return $r;
		}
		
		function qf(&$r)
		{
			unset($r);			
		}
		
		function db_rowobj($result)
		{
			return pg_fetch_object($result);
		}
		
		function db_rowarr($result)
		{
			return pg_fetch_array($result);
		}
		
		function q_singleval($query)
		{
			return @current(pg_fetch_row(q($query)));
		}
		
		function get_fud_table_list()
		{
			$c = q("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".str_replace('_', '\\\\_', $GLOBALS['DBHOST_TBL_PREFIX'])."%'");
			while ($r = db_rowarr($c)) {
				$ret[] = $r[0];
			}
			qf($c);

			return $ret;	
		}
		
		function check_sql_perms()
		{
			@pg_query(__FUD_SQL_LNK__, 'DROP TABLE fud_forum_upgrade_test_table');
			if (!pg_query(__FUD_SQL_LNK__, 'CREATE TABLE fud_forum_upgrade_test_table (test_val INT)')) {
				upgrade_error('FATAL ERROR: your forum\'s PostgreSQL account does not have permissions to create new PostgreSQL tables.<br>Enable this functionality and restart the script.');
			}	
			if (!pg_query(__FUD_SQL_LNK__, 'ALTER TABLE fud_forum_upgrade_test_table ADD test_val2 INT')) {
				upgrade_error('FATAL ERROR: your forum\'s PostgreSQL account does not have permissions to run ALTER queries on existing PostgreSQL tables<br>Enable this functionality and restart the script.');
			}	
			if (!pg_query(__FUD_SQL_LNK__, 'DROP TABLE fud_forum_upgrade_test_table')) {
				upgrade_error('FATAL ERROR: your forum\'s PostgreSQL account does not have permissions to run DROP TABLE queries on existing PostgreSQL tables<br>Enable this functionality and restart the script.');
			}
		}
		
		function get_field_list($tbl)
		{
			return q("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$tbl."' AND a.attnum > 0 AND a.attrelid = c.oid ORDER BY a.attnum");
		}

		function pgsql_drop_sequences($table)
		{
			$c = q("SELECT c.relname FROM pg_catalog.pg_class c JOIN pg_catalog.pg_index i ON i.indexrelid = c.oid JOIN pg_catalog.pg_class c2 ON i.indrelid = c2.oid WHERE c.relkind IN ('S','') AND pg_catalog.pg_table_is_visible(c.oid) AND c.relkind='i' AND c2.relname='".$table."'");
			while ($r = db_rowarr($c)) {
				q('DROP SEQUENCE '.$r[0]);
			}
			qf($c);
		}

		function pgsql_drop_indexes($table)
		{
			if (q_singleval('SELECT c.relname FROM pg_catalog.pg_class c WHERE c.relkind=\'i\' AND c.relname=\''.$table.'_pkey\'')) {
				q('ALTER TABLE '.$table.' DROP CONSTRAINT '.$table.'_pkey');
			}
			$c = q("SELECT c.relname FROM pg_catalog.pg_class c JOIN pg_catalog.pg_index i ON i.indexrelid = c.oid JOIN pg_catalog.pg_class c2 ON i.indrelid = c2.oid WHERE c.relkind IN ('i','') AND pg_catalog.pg_table_is_visible(c.oid) AND c.relkind='i' AND c2.relname='".$table."'");
			while ($r = db_rowarr($c)) {
				q('DROP INDEX '.$r[0]);
			}
			qf($c);
		}

		function pgsql_drop_if_exists($table)
		{
			if (q_singleval("SELECT c.relname FROM pg_class c LEFT JOIN pg_user u ON c.relowner = u.usesysid WHERE c.relkind IN ('r','') AND c.relname='".$table."'")) {
				q('DROP TABLE '.$table);
				return 1;
			}
		}

		function pgsql_make_field_lst($table)
		{
			$fl_o = get_field_list($table);
			$fl = '';
			while ($r = db_rowarr($fl_o)) {
				$fl .= $r[0].',';
			}
			qf($fl_o);
			return rtrim($fl, ',');
		}

		function pgsql_rebuild_table($table, $newc=NULL, $oldc=NULL)
		{
			if (isset($GLOBALS['REBUILT_TABLES'][$table])) {
				return;
			} else {
				$GLOBALS['REBUILT_TABLES'][$table] = 1;	
			}
			$tmp_prefix = $GLOBALS['DBHOST_TBL_PREFIX'] . 'tmp_';

			pgsql_drop_indexes($GLOBALS['DBHOST_TBL_PREFIX'] . $table);
			if (pgsql_drop_if_exists($tmp_prefix . $table)) {
				pgsql_drop_sequences($tmp_prefix . $table);
			}
			if (q_singleval("SELECT c.relname FROM pg_catalog.pg_class c WHERE c.relkind='S' AND c.relname='".$GLOBALS['DBHOST_TBL_PREFIX'] . $table."_id_seq'")) {
				q('ALTER TABLE '.$GLOBALS['DBHOST_TBL_PREFIX'] . $table.'_id_seq RENAME TO '.$tmp_prefix.$table.'_id_seq');
			}
			
			$tmp = get_table_defenition($table);
			if (count($tmp)) {
				foreach ($tmp as $ent) { 
					if (trim($ent)) {
						if (strpos($ent, 'CREATE TABLE') !== false) {
							q('ALTER TABLE '.$GLOBALS['DBHOST_TBL_PREFIX'].$table.' RENAME TO '.$tmp_prefix.$table);

							q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent)); 
					
							$fls = $fld = pgsql_make_field_lst($GLOBALS['DBHOST_TBL_PREFIX'] . $table);
							if (is_null($newc) && $oldc) {
								$fls = preg_replace('!^|,'.$newc.',|$!', ',', $fls);
								$fls = $fld = trim(str_replace(',,', ',', $fls), ',');
							} else if (!is_null($newc) && !is_null($oldc)) {
								$fls = preg_replace('!(^|,)('.$newc.')(,|$)!', '\\1'.$oldc.'\\3', $fls);
							}

							q('INSERT INTO ' . $GLOBALS['DBHOST_TBL_PREFIX'] . $table . ' (' . $fld . ') SELECT ' . $fls . ' FROM ' . $tmp_prefix . $table);
					
							if (strpos($ent, 'SERIAL PRIMARY KEY')) {
								if (!($m = q_singleval('SELECT MAX(id) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'] . $table))) {
									$m = 1;
								}
								q('SELECT setval(\''.$GLOBALS['DBHOST_TBL_PREFIX'].$table.'_id_seq\', '.$m.')');
							}
							q('DROP TABLE '.$tmp_prefix . $table);
							pgsql_drop_sequences($tmp_prefix . $table);
						} else {
							q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent));
						}	
					}
				}
			}
		}
		
		function pgsql_add_column($tbl, $name, $type, $vals, $is_null, $default, $auto_inc)
		{
			if ($auto_inc) {
				q('ALTER TABLE '.$tbl.' ADD COLUMN '.$name.' INT');
				q('CREATE SEQUENCE '.$tbl.'_'.$name.'_seq START 1');
				q('ALTER TABLE '.$tbl.' ALTER COLUMN '.$name.' SET DEFAULT nextval(\''.$tbl.'_'.$name.'_seq\'::text)');
				q('ALTER TABLE '.$tbl.' ALTER COLUMN '.$name.' SET NOT NULL');
				return;
			}
		
			if ($type != 'ENUM') {
				if ($vals == 'UNSIGNED') {
					$type = 'int8';
					$vals = '';
				}
				q('ALTER TABLE '.$tbl.' ADD COLUMN '.$name.' '.$type.' '.$vals);
			} else {
				$vals = explode(',', preg_replace('!\s+!', '', trim($vals, '()')));
				$max_l = 0;
				foreach ($vals as $v) {
					if ($max_l < strlen($v)) {
						$max_l = strlen($v);
					}
				}

				q('ALTER TABLE '.$tbl.' ADD COLUMN '.$name.' VARCHAR('.$max_l.')');

				$def = !is_null($default) ? $default : $vals[0];
				q('UPDATE '.$tbl.' SET '.$name.'='.$default.' WHERE '.$name.' NOT IN('.implode(',', $vals).') OR '.$name.' IS NULL');				

				$chk = $name . '=' . implode(' OR ' . $name . '=', $vals);

				/* check if we have an old constraint we need to delete */
				$tbl_oid = q_singleval("SELECT c.oid FROM pg_catalog.pg_class c WHERE pg_catalog.pg_table_is_visible(c.oid) AND c.relname='".$tbl."'");
				if (q_singleval("SELECT conname FROM pg_catalog.pg_constraint WHERE conrelid = ".$tbl_oid." AND contype = 'c' AND conname = '".$name."_cnt'")) {
					q('ALTER TABLE '.$tbl.' DROP CONSTRAINT '.$name.'_cnt');
				}

				q('ALTER TABLE '.$tbl.' ADD CONSTRAINT '.$name.'_cnt CHECK ('.$chk.')');
			}
			if (strlen($default)) {
				q('ALTER TABLE '.$tbl.' ALTER COLUMN '.$name.' SET DEFAULT '.$default);
				q('UPDATE '.$tbl.' SET '.$name.'='.$default);
			}
			if (!$is_null) {
				q('ALTER TABLE '.$tbl.' ALTER COLUMN '.$name.' SET NOT NULL');
			}
		}
	} else { 
		upgrade_error('NO VALID DATABASE TYPE SPECIFIED');
	}	
}

function fetch_cvs_id($data)
{
	if (($s = strpos($data, '$Id')) === false) {
		return;
	}
	if (($e = strpos($data, 'Exp $', $s)) === false) {
		return;
	}
	return substr($data, $s, ($e - $s));
}

function backupfile($source)
{
	copy($source, $GLOBALS['ERROR_PATH'] . '.backup/' . basename($source) . '_' . __time__);
}

function __mkdir($dir)
{
	if (@is_dir($dir)) {
		return 1;
	}
	$u = umask(($GLOBALS['FILE_LOCK'] == 'Y' ? 0077 : 0));
	$ret = (mkdir($dir) || mkdir(dirname($dir)));
	umask($u);

	return $ret;
}

function htaccess_handler($web_root, $ht_pass)
{
	if (!fud_ini_get('allow_url_fopen')) {
		unlink($ht_pass);
	}
	if (@fopen($web_root . 'index.php', 'r') === FALSE) {
		unlink($ht_pass);
	}
}

function upgrade_decompress_archive($data_root, $web_root)
{
	$data = file_get_contents("fudforum_archive");

	$pos = 0;
	$u = umask(($GLOBALS['FILE_LOCK'] == 'Y' ? 0177 : 0111));

	do  {
		$end = strpos($data, "\n", $pos+1);
		$meta_data = explode('//',  substr($data, $pos, ($end-$pos)));
		$pos = $end;

		if ($meta_data[1] == 'GLOBALS.php') {
			continue;
		}

		if (!strncmp($meta_data[3], 'install/forum_data', 18)) {
			$path = $data_root . substr($meta_data[3], 18);
		} else if (!strncmp($meta_data[3], 'install/www_root', 16)) {
			$path = $web_root . substr($meta_data[3], 16);
		} else {
			continue;
		}
		$path .= '/' . $meta_data[1];

		$path = str_replace('//', '/', $path);

		if (isset($meta_data[5])) {
			$file = substr($data, ($pos + 1), $meta_data[5]);
			if (md5($file) != $meta_data[4]) {
				upgrade_error('ERROR: file '.$meta_data[1].' was not read properly from archive');
			}
			if (@file_exists($path)) {
				if (md5_file($path) == $meta_data[4]) {
					// file did not change
					continue;
				}
				// Compare CVS Id to ensure we do not pointlessly replace files modified by the user
				if (($cvsid = fetch_cvs_id($file)) && $cvsid == fetch_cvs_id(file_get_contents($path))) {
					continue;
				}

				backupfile($path);
			}
		
			if (!($fp = @fopen($path, 'wb'))) {
				upgrade_error('Couldn\'t open "'.$path.'" for write');
			}	
			fwrite($fp, $file);
			fclose($fp);
		} else {
			if (!__mkdir(preg_replace('!/+$!', '', $path))) {
				upgrade_error('failed creating "'.$path.'" directory');
			}	
		}
	} while (($pos = strpos($data, "\n//", $pos)) !== false);
	umask($u);
}

function get_table_defenition($table)
{
	if (!($table_data = file_get_contents($GLOBALS['DATA_DIR'] . 'sql/' . __dbtype__ . '/fud_' . $table . '.tbl'))) {
		upgrade_error('Failed to read table defenition for "'.$table.'" at: '.$GLOBALS['DATA_DIR'] . 'sql/' . __dbtype__ . '/fud_' . $table . '.tbl');
	}

	$table_data = explode(';', strstr($table_data, 'CREATE TABLE'));

	return preg_replace('!\s+!', ' ', preg_replace("!#(.*?)\n!", '', $table_data));
}

function parse_todo_entry($line)
{
	if (!($line = trim($line))) {
		return;
	}
	if (!isset($GLOBALS['table_list'])) {
		$tmp = get_fud_table_list();
		foreach ($tmp as $tmp_val) {
			$GLOBALS['table_list'][$tmp_val] = $tmp_val;
		}
	}
	$table_list =& $GLOBALS['table_list'];

	$tmp = explode('::', $line);
	if (($c = count($tmp)) < 2) {
		echo 'Bad SQL change line "'.htmlspecialchars($line).'"<br>';
		flush();
		return;
	}
	
	$table_name = $GLOBALS['DBHOST_TBL_PREFIX'] . $tmp[0];
	$action = $tmp[1];

	switch ($action) {
		case 'ADD_TABLE_DB':
			if (isset($table_list[$table_name])) {
				break;
			}

			$tmp = get_table_defenition($tmp[0]);
			if (count($tmp)) {
				foreach ($tmp as $ent) { 
					if (trim($ent)) {
						q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent));
					}
				}
			} else {
				echo 'bad table defenition for '.$table_name.'<br>';
				flush();
			}
			break;

		case 'DROP_TABLE':
			if (!isset($table_list[$table_name])) {
				break;
			}
			
			q('DROP TABLE '.$table_name);
			if (__dbtype__ == 'pgsql') {
				pgsql_drop_sequences($table_name);
			}
			unset($table_list[$table_name]);
			break;

		case 'ADD_COLUMN': 
			 #	$tmp[2] -> column_name
			 #	$tmp[3] -> column_type
			 #	$tmp[4] -> column_value
			 #	$tmp[5] -> is_null
			 #	$tmp[6] -> default_value
			 #	$tmp[7] -> auto_increment
			 #	$tmp[8] -> trigger queries, separated by ;

			if (__dbtype__ == 'mysql') {
				if (mysql_row_exists($table_name, $tmp[2])) {
					break;
				}
				$query = 'ALTER TABLE '.$table_name.' ADD '.$tmp[2].' '.$tmp[3].' '.$tmp[4];
				if (empty($tmp[5])) {
					$query .= ' NOT NULL';
				}
				if (isset($tmp[6]) && strlen($tmp[6])) {
					$query .= ' DEFAULT '.$tmp[6];
				}
				if (isset($tmp[7]) && strlen($tmp[7])) {
					$query .= ' AUTO_INCREMENT';
				}

				q($query);
			} else if (__dbtype__ == 'pgsql') {
				if (q_singleval("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$table_name."' AND a.attnum>0 AND a.attrelid=c.oid AND a.attname=lower('".$tmp[2]."')")) {
					break;
				}
				@pgsql_add_column($GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0], $tmp[2], $tmp[3], $tmp[4], !empty($tmp[5]), $tmp[6], isset($tmp[7]));
			}
			
			if (isset($tmp[8])) {
				$tmp = explode(';', $tmp[8]);
				foreach($tmp as $qy) {
					if (trim($qy)) {
						q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $qy));
					}
				}
			}
			break;

		case 'DROP_COLUMN': // $tmp[2] -> column_name
			if (__dbtype__ == 'mysql' && !mysql_row_exists($table_name, $tmp[2])) {
				break;
			}
			if (__dbtype__ == 'pgsql' && !q_singleval("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$table_name."' AND a.attnum>0 AND a.attrelid=c.oid AND a.attname='".$tmp[2]."'")) {
				break;
			}
			
			q('ALTER TABLE '.$table_name.' DROP '.$tmp[2]);	
			break;

		case 'ALTER_COLUMN':
			 #	$tmp[2] -> old_column_name
			 #	$tmp[3] -> column_name 
			 #	$tmp[4] -> column_type
			 #	$tmp[5] -> column_value
			 #	$tmp[6] -> is_null
			 #	$tmp[7] -> default_value
			 #	$tmp[8] -> auto_increment
			 #	$tmp[9] -> remove all data from table

			if (__dbtype__ == 'mysql') {
				if ($tmp[2] != $tmp[3] && !mysql_row_exists($table_name, $tmp[2])) {
					break;
				}
				$query = 'ALTER TABLE '.$table_name.' CHANGE '.$tmp[2].' '.$tmp[3].' '.$tmp[4].' '.$tmp[5];
				if (empty($tmp[6])) {
					$query .= ' NOT NULL';
				}
				if (isset($tmp[7]) && strlen($tmp[7])) {
					$query .= ' DEFAULT '.$tmp[7];
				}
				if (isset($tmp[8]) && strlen($tmp[8])) {
					$query .= ' AUTO_INCREMENT';
				}
				q($query);
			} else if (__dbtype__ == 'pgsql') {
				if ($tmp[2] != $tmp[3] && q_singleval("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$table_name."' AND a.attnum > 0 AND a.attrelid = c.oid AND a.attname=lower('".$tmp[3]."')")) {
					break;				
				}
				if (isset($tmp[8]) && q_singleval("SELECT c.relname FROM pg_catalog.pg_class c WHERE c.relkind='S' AND c.relname='".$GLOBALS['DBHOST_TBL_PREFIX'] . $tmp[0]."_".$tmp[3]."_seq'")) {
					break;
				}

				if ($tmp[2] == $tmp[3]) {
					q('ALTER TABLE '.$GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0].' RENAME COLUMN '.$tmp[2].' TO tmp_'.$tmp[3]);
					$tmp[2] = 'tmp_' . $tmp[2]; 
				}
				if (!empty($tmp[9])) {
					q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0]);
				}
				pgsql_add_column($GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0], $tmp[3], $tmp[4], $tmp[5], !empty($tmp[6]), (isset($tmp[7]) ? $tmp[7] : ''), isset($tmp[8]));
				if ($tmp[4] == 'ENUM') {
					q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0].' SET '.$tmp[3].'='.$tmp[2].' WHERE '.$tmp[2].' IN('.implode(',', explode(',', preg_replace('!\s+!', '', trim($tmp[5], '()')))).') AND '.$tmp[2].' IS NOT NULL');
				} else {
					q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0].' SET '.$tmp[3].'='.$tmp[2]);
				}
				q('ALTER TABLE '.$GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0].' DROP '.$tmp[2]);
			}
			break;

		case 'ADD_INDEX':
			 #	$tmp[2] -> index_type
			 #	$tmp[3] -> index_defenition
			 #	$tmp[4] -> index_name (pgsql only)
			 #	$tmp[5] -> name of a function to execute 

			if (isset($tmp[5])) {
				call_user_func(trim($tmp[5]));
			}

			if (__dbtype__ == 'mysql') {
			 	if (match_mysql_index($table_name, $tmp[3], $tmp[2])) {
			 		break;
			 	}
			 	q('ALTER TABLE '.$table_name.' ADD '.$tmp[2].'('.$tmp[3].')');
			} else if (__dbtype__ == 'pgsql') {
				$index_name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[4]);
			
				if (q_singleval("SELECT * FROM pg_stat_user_indexes WHERE relname='".$table_name."' AND indexrelname='".$index_name."'")) {
					break;
				}
				if ($tmp[2] == 'INDEX') {
					q('CREATE INDEX '.$index_name.' ON '.$table_name.' ('.$tmp[3].')');
				} else {
					q('CREATE UNIQUE INDEX '.$index_name.' ON '.$table_name.' ('.$tmp[3].')');	
				}
			}
			break;

		case 'DROP_INDEX':
			 #	$tmp[2] -> index_type
			 #	$tmp[3] -> index_defenition
			 #	$tmp[4] -> index_name (pgsql only)

			if (__dbtype__ == 'mysql') {
				if (!($tmp[4] = match_mysql_index($table_name, $tmp[3], $tmp[2]))) {
					break;
				}
			 	q('ALTER TABLE '.$table_name.' DROP INDEX '.$tmp[4]);
			} else if (__dbtype__ == 'pgsql') {
				$index_name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[4]);
				if (!q_singleval("SELECT * FROM pg_stat_user_indexes WHERE relname='".$table_name."' AND indexrelname='".$index_name."'")) {
					break;
				}
				q('DROP INDEX '.$index_name);
			}
			break;

		case 'QUERY':
			 #      $tmp[2] -> query
		  	 #      $tmp[3] -> version

			q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[2]));
			break;	
	}	
}

function cache_avatar_image($url, $user_id)
{
	$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');
	if (!isset($GLOBALS['AVATAR_ALLOW_SWF'])) {
		$GLOBALS['AVATAR_ALLOW_SWF'] = 'N';
	}
	if (!isset($GLOBALS['CUSTOM_AVATAR_MAX_DIM'])) {
		$max_w = $max_y = 64;
	} else {
		list($max_w, $max_y) = explode('x', $GLOBALS['CUSTOM_AVATAR_MAX_DIM']);
	}

	if (!($img_info = @getimagesize($url)) || $img_info[0] > $max_w || $img_info[1] > $max_y || $img_info[2] > ($GLOBALS['AVATAR_ALLOW_SWF']!='Y'?3:4)) {
		return;
	}
	if (!($img_data = file_get_contents($url)) || strlen($img_data) > $GLOBALS['CUSTOM_AVATAR_MAX_SIZE']) {
		return;
	}
	if (!($fp = fopen($GLOBALS['WWW_ROOT_DISK'] . 'images/custom_avatars/' . $user_id . '.' . $ext[$img_info[2]], 'wb'))) {
		return;
	}
	fwrite($fp, $img_data);
	fclose($fp);

	return '<img src="'.$GLOBALS['WWW_ROOT'].'images/custom_avatars/'.$user_id . '.' . $ext[$img_info[2]].'" '.$img_info[3].' />';
}

function syncronize_theme_dir($theme, $dir, $src_thm)
{
	$path = $GLOBALS['DATA_DIR'].'thm/'.$theme.'/'.$dir;
	$spath = $GLOBALS['DATA_DIR'].'thm/'.$src_thm.'/'.$dir;

	if (!__mkdir($path)) {
		upgrade_error('Directory "'.$path.'" does not exist, and the upgrade script failed to create it.');	
	}
	if (!($d = opendir($spath))) {
		upgrade_error('Failed to open "'.$spath.'"');
	}
	readdir($d); readdir($d);
	$path .= '/';
	$spath .= '/';
	while ($f = readdir($d)) {
		if (@is_dir($spath . $f) && !is_link($spath . $f)) {
			syncronize_theme_dir($theme, $dir . '/' . $f, $src_thm);
			continue;
		}	
		if (!@file_exists($path . $f) && !copy($spath . $f, $path . $f)) {
			upgrade_error('Failed to copy "'.$spath . $f.'" to "'.$path . $f.'", check permissions then run this scripts again.');			
		} else {
			// Skip images, we do not need to replace them.
			if (preg_match('!/images/.*\.gif!', $path)) {
				continue;
			}
			if (md5_file($path . $f) == md5_file($spath . $f) || fetch_cvs_id(file_get_contents($path . $f)) == fetch_cvs_id(file_get_contents($spath . $f))) {
				continue;
			}

			backupfile($path . $f);
			copy($spath . $f, $path . $f);
		}
			
	}
	closedir($d);
}

function syncronize_theme($theme)
{
	if ($theme == 'path_info' || @file_exists($GLOBALS['DATA_DIR'].'thm/'.$theme.'/.path_info')) {
		$src_thm = 'path_info';
	} else {
		$src_thm = 'default';
	}

	syncronize_theme_dir($theme, 'tmpl', $src_thm);
	syncronize_theme_dir($theme, 'i18n', $src_thm);
}

// needed for php versions (<4.3.0) lacking this function
if (!function_exists('file_get_contents')) {
	function file_get_contents($path)
	{
		if (!($fp = fopen($path, 'rb'))) {
			return FALSE;
		}
		$data = fread($fp, filesize($path));
		fclose($fp);

		return $data;
	}
}

function clean_read_table()
{
	$r = q('SELECT thread_id, user_id, count(*) AS cnt FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'read GROUP BY thread_id,user_id ORDER BY cnt DESC');
	while ($o = db_rowobj($r)) {
		if ($o->cnt == "1") {
			break;
		}
		q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'read WHERE thread_id='.$o->thread_id.' AND user_id='.$o->user_id.' LIMIT '.($o->cnt - 1));
	}
}

function clean_forum_read_table()
{
	$r = q('SELECT forum_id, user_id, count(*) AS cnt FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum_read GROUP BY forum_id, user_id ORDER BY cnt DESC');
	while ($o = db_rowobj($r)) {
		if ($o->cnt == "1") {
			break;
		}
		q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum_read WHERE forum_id='.$o->forum_id.' AND user_id='.$o->user_id.' LIMIT '.($o->cnt - 1));
	}
}
	error_reporting(E_ALL);
	ini_set('memory_limit', '8M');
	ignore_user_abort(true);
	set_magic_quotes_runtime(0);
	@set_time_limit(600);

	if (ini_get('error_log')) {
		ini_set('error_log', '');
	}
	if (!fud_ini_get('display_errors')) {
		ini_set('display_errors', 1);
	}
	if (!fud_ini_get('track_errors')) {
		ini_set('track_errors', 1);
	}

	// php version check
	if (!version_compare(phpversion(), '4.2.0', '>=')) {
		echo '<html><body bgcolor="white">';
		upgrade_error('The upgrade script requires that you have php version 4.2.0 or higher');
	}

	// Determine SafeMode limitations
	define('SAFE_MODE', fud_ini_get('safe_mode'));
	if (SAFE_MODE && basename(__FILE__) != 'upgrade_safe.php') {
		$c = getcwd();
		copy($c . '/upgrade.php', $c . '/upgrade_safe.php');
		header('Location: '.dirname($_SERVER['SCRIPT_NAME']).'/upgrade_safe.php');
		exit;
	}

	echo '<html><body bgcolor="white">';
	// we need to verify that GLOBALS.php exists in current directory & that we can open it
	$gpath = getcwd() . '/GLOBALS.php';
	if (!@file_exists($gpath)) {
		upgrade_error('Unable to find GLOBALS.php inside the current ('.getcwd().') directory. Please place the upgrade ('.basename(__FILE__).') script inside main web directory of your forum');
	} else if (!@is_writable($gpath)) {
		upgrade_error('No permission to read/write to '.getcwd().' /GLOBALS.php. Please make sure this script had write access to all of the forum files.');
	}

	if (preg_match('!win!i', PHP_OS)) {
		preg_match('!include_once "(.*)"; !', file_get_contents($gpath), $m);
		$gpath = $m[1];
	}
	$data = file_get_contents($gpath);
	$s = strpos($data, '*/') + 2;
	$data = substr($data, $s, (strpos($data, 'DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING', $s) - $s)) . ' */';
	eval($data);

	/* this check is here to ensure the data from GLOBALS.php was parsed correctly */
	if (!isset($GLOBALS['COOKIE_NAME'])) {
		upgrade_error('Failed to parse GLOBALS.php at "'.$gpath.'" correctly');	
	}

	/* database variable conversion */
	if (!isset($GLOBALS['DBHOST_TBL_PREFIX'])) {
		$DBHOST_TBL_PREFIX 	= $MYSQL_TBL_PREFIX;
		$DBHOST 		= $MYSQL_SERVER;
		$DBHOST_USER 		= $MYSQL_LOGIN;
		$DBHOST_PASSWORD 	= $MYSQL_PASSWORD;
		$DBHOST_DBNAME 		= $MYSQL_DB;
		$DBHOST_PERSIST 	= $MYSQL_PERSIST;
		define('__dbtype__', 'mysql');
	}

	if (!isset($GLOBALS['DATA_DIR'])) {
		$GLOBALS['DATA_DIR'] = realpath($GLOBALS['INCLUDE'] . '../') . '/';
		$no_data_dir = 1;
	}

	/* Determine Database Type */
	if (!defined('__dbtype__')) {
		if (@md5_file($GLOBALS['DATA_DIR'] . 'include/theme/default/db.inc') !== md5_file($GLOBALS['DATA_DIR'] . 'sql/pgsql/db.inc')) {
			define('__dbtype__', 'mysql');
		} else {
			define('__dbtype__', 'pgsql');
		}
	}

	/* include appropriate database functions */
	init_sql_func();

	/* only allow the admin user to upgrade the forum */
	$auth = 0;
	if (count($_POST)) {
		if (get_magic_quotes_gpc()) {
			$_POST['login'] = stripslashes($_POST['login']);
			$_POST['passwd'] = stripslashes($_POST['passwd']);
		}

		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($_POST['login'])."' AND passwd='".md5($_POST['passwd'])."' AND is_mod='A'")) {
			$auth = 1;
		}
	}
	if (!$auth) {
		/* seperate the data archive into a seperate file */
		$data = file_get_contents(__FILE__);
		if (strlen($data) < 100000 && !@file_exists("fudforum_archive")) {
			upgrade_error('The upgrade script is missing the data archive, cannot run.');
		} else if (strlen($data) > 100000) {
			if (($zl = strpos($data, 'RAW_PHP_OPEN_TAG', 100000)) === FALSE && !extension_loaded('zlib')) {
				upgrade_error('The upgrade script uses zlib compression, however your PHP was not compiled with zlib support or the zlib extension is not loaded. In order to get the upgrade script to work you\'ll need to enable the zlib extension or download a non compressed upgrade script from <a href="http://fud.prohost.org/forum/">http://fud.prohost.org/forum/</a>');
			}
			/* no errors, move archive to separate file */
			$p = strpos($data, "2105111608_\\ARCH_START_HERE");
			$fp = fopen(__FILE__, "w");
			fwrite($fp, substr($data, 0, $p));
			fclose($fp);
			$data = substr($data, ($p + strlen("2105111608_\\ARCH_START_HERE") + 1));
			$checksum = substr($data, 0, 32);
			$data = substr($data, 32);
			if ($zl) {
				$data = str_replace('PHP_OPEN_TAG', '<?', $data);
			} else {
				$data_len = (int) substr($data, 0, 10);
				$data = str_replace('PHP_OPEN_TAG', '<?', substr($data, 10));
				$data = gzuncompress($data, $data_len);
			}
			if (md5($data) != $checksum) {
				upgrade_error('Archive did pass checksum test, CORRUPT ARCHIVE!<br>If you\'ve encountered this error it means that you\'ve:<br>&nbsp;&nbsp;&nbsp;&nbsp;downloaded a corrupt archive<br>&nbsp;&nbsp;&nbsp;&nbsp;uploaded the archive in ASCII and not BINARY mode<br>&nbsp;&nbsp;&nbsp;&nbsp;your FTP Server/Decompression software/Operating System added un-needed cartrige return (\'\r\') characters to the archive, resulting in archive corruption.');
			}
			
			$fp = fopen("fudforum_archive", "w");
			fwrite($fp, $data);
			fclose($fp);

			unset($data);
		}
?>		
<div align="center">
<form name="upgrade" action="<?php echo basename(__FILE__); ?>" method="post">
<table cellspacing=1 cellpadding=3 border=0 style="border: 1px dashed #1B7CAD;">
<tr bgcolor="#dee2e6">
	<th colspan=2>Please enter the login &amp; password of the administration account.</th>
</tr>
<tr bgcolor="#eeeeee">
	<td><b>Login:</b></td>
	<td><input type="text" name="login" value=""></td>
</tr>
<tr bgcolor="#eeeeee">
	<td><b>Password:</b></td>
	<td><input type="password" name="passwd" value=""></td>
</tr>
<tr bgcolor="#dee2e6">
	<td align="right" colspan=2><input type="submit" name="submit" value="Authenticate"></td>
</tr>
</table>
</form>
</div>
</body>
</html>
<?php
		exit;
	}

	if (!isset($GLOBALS['FILE_LOCK'])) {
		$GLOBALS['FILE_LOCK'] = 'Y';
	}

	// Determine open_basedir limitations
	define('open_basedir', ini_get('open_basedir'));
	if (open_basedir) {
		if (!preg_match('!win!i', PHP_OS)) { 
			$dirs = explode(':', open_basedir);
		} else {
			$dirs = explode(';', open_basedir);
		}
		$safe = 1;
		foreach ($dirs as $d) {
			if (!strncasecmp($GLOBALS['DATA_DIR'], $d, strlen($d))) {
			        $safe = 0;
			        break;
			}
		}
		if ($safe) {
			upgrade_error('Your php\'s open_basedir limitation ('.open_basedir.') will prevent the upgrade script from writing to ('.$GLOBALS['DATA_DIR'].'). Please make sure that access to ('.$GLOBALS['DATA_DIR'].') is permitted.');
		}
		if ($GLOBALS['DATA_DIR'] != $GLOBALS['WWW_ROOT_DISK']) {
			$safe = 1;
			foreach ($dirs as $d) {
				if (!strncasecmp($GLOBALS['WWW_ROOT_DISK'], $d, strlen($d))) {
				        $safe = 0;
					break;
				}
			}
			if ($safe) {
				upgrade_error('Your php\'s open_basedir limitation ('.open_basedir.') will prevent the upgrade script from writing to ('.$GLOBALS['WWW_ROOT_DISK'].'). Please make sure that access to ('.$GLOBALS['WWW_ROOT_DISK'].') is permitted.');
			}
		}
	}

	/* determine if this upgrade script was previously ran */
	if (@file_exists($GLOBALS['ERROR_PATH'] . 'UPGRADE_STATUS') && (int) trim(file_get_contents($ERROR_PATH . 'UPGRADE_STATUS')) >= $__UPGRADE_SCRIPT_VERSION) {
		upgrade_error('THIS UPGRADE SCRIPT HAS ALREADY BEEN RUN, IF YOU WISH TO RUN IT AGAIN USE THE FILE MANAGER TO REMOVE THE "'.$GLOBALS['ERROR_PATH'].'UPGRADE_STATUS" FILE.');
	}

	show_debug_message('Disable the forum');
	change_global_settings(array('FORUM_ENABLED' => 'N'));
	show_debug_message('Forum is now disabled');

	/* check that we can do all needed database operations */
	show_debug_message('Check if SQL permissions to perform the upgrade are avaliable');
	check_sql_perms();
	
	/* Upgrade Files */
	show_debug_message('Beginning the file upgrade process');
	__mkdir($GLOBALS['ERROR_PATH'] . '.backup');
	define('__time__', time());
	show_debug_message('Begining to decompress the archive');
	upgrade_decompress_archive($GLOBALS['DATA_DIR'], $GLOBALS['WWW_ROOT_DISK']);
	/* determine if this host can support .htaccess directives */
	htaccess_handler($GLOBALS['WWW_ROOT'], $GLOBALS['WWW_ROOT_DISK'] . '.htaccess');
	show_debug_message('Finished decompressing the archive');
	show_debug_message('File Upgrade Complete');
	show_debug_message('<font color="#ff0000">Any changed files were backed up to: "'.$GLOBALS['ERROR_PATH'].'.backup/"</font><br>');
	
	/* Update SQL */
	show_debug_message('Fetch SQL archive');
	$data = file_get_contents(__FILE__);
	$s = strpos($data, '042252166145_\\SQL_START_HERE') + strlen('042252166145_\\SQL_START_HERE');
	$e = strpos($data, '042252166145_\\SQL_END_HERE', $s);
	$sql_data = substr($data, $s, ($e - $s));
	show_debug_message('SQL archive is now avaliable');

	show_debug_message('Beginning SQL Upgrades');
	$qry = explode("\n", $sql_data);
	foreach($qry as $v) {
		parse_todo_entry($v);
	}

	/* Check for dublicate indexes and remove them if there are any */
	if (__dbtype__ == 'mysql') {
		$tbl_list = get_fud_table_list();
		foreach($tbl_list as $v) {
			remove_dup_indexes($v);
		}
	}
	show_debug_message('SQL Upgrades Complete');

	/* Perform various upgrades, for old versions, which could only be using MySQL */
	if (__dbtype__ == 'mysql') {
		/* Move homepage & bio from flat files to DB */
		if (!mysql_row_exists($GLOBALS['DBHOST_TBL_PREFIX'] . 'users', 'home_page')) {
			show_debug_message('Moving homepage & signature to database');
			q('ALTER TABLE ' . $GLOBALS['DBHOST_TBL_PREFIX'] . 'users ADD home_page CHAR(255), ADD bio TEXT');
			$d = opendir($GLOBALS['USER_SETTINGS_PATH']);
			readdir($d); readdir($d);
			while ($f = readdir($d)) {
				if (substr($f, -4) != '.fud') {
					continue;
				}
				$raw = file_get_contents($GLOBALS['USER_SETTINGS_PATH'] . '/' . $f);
				$l = (int) substr($raw, 0, 8);
				$bio = substr($raw, $l + 16);
				$id = basename($f, '.fud');
				q("UPDATE ".$DBHOST_TBL_PREFIX."users SET home_page='".addslashes($www)."', bio='".addslashes($bio)."' WHERE id=".$id);
			}
			closedir($d);
		}

		/* Add VISIBLE permission */
		if (!mysql_row_exists($GLOBALS['DBHOST_TBL_PREFIX'] . 'groups', 'p_VISIBLE')) {
			show_debug_message('Adding visible permission');
			q("ALTER TABLE ".$DBHOST_TBL_PREFIX."groups ADD p_VISIBLE ENUM('I', 'Y', 'N') NOT NULL DEFAULT 'N'");
			q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET p_VISIBLE='Y' WHERE p_VIEW='Y'");
			q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET up_VISIBLE='Y' WHERE up_VIEW='Y'");
		}

		/* convert the replacement system into the new format (for < 2.0 forums ) */
		if (!isset($GLOBALS['CUSTOM_AVATAR_APPOVAL'])) {
			show_debug_message('Converting replacment system');
			$r = q("SELECT * FROM ".$DBHOST_TBL_PREFIX."replace WHERE type='REPLACE'");
			while ( $obj = db_rowobj($r) ) {
				$obj->replace_str = addslashes(preg_quote($obj->replace_str));
				$obj->replace_str = '/'.str_replace('/', '\\\\/',  $obj->replace_str).'/i';
				$obj->with_str = str_replace('\\', "\\\\", $obj->with_str);
				q("UPDATE ".$DBHOST_TBL_PREFIX."replace SET replace_str='$obj->replace_str', with_str='$obj->with_str' WHERE id=$obj->id");
			}
			qf($r);
		}
		
		/* Convert the way date field is stored for announcements */
		$r = q("SHOW FIELDS FROM ".$DBHOST_TBL_PREFIX."announce LIKE 'date_started'");
		$obj = db_rowobj($r);
		if ($obj->Type == 'date') {
			show_debug_message('Converting announcment system');
			q("CREATE TABLE ".$DBHOST_TBL_PREFIX."announce_tmp ( id INT, date_started DATE, date_ended DATE,subject VARCHAR(255),text TEXT )");
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."announce_tmp SELECT * FROM ".$DBHOST_TBL_PREFIX."announce");
			q("ALTER TABLE ".$DBHOST_TBL_PREFIX."announce CHANGE date_started date_started INT NOT NULL");
			q("ALTER TABLE ".$DBHOST_TBL_PREFIX."announce CHANGE date_ended date_ended INT NOT NULL");
			q("DELETE FROM ".$DBHOST_TBL_PREFIX."announce");
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."announce SELECT id,REPLACE(date_started,'-',''),REPLACE(date_ended,'-',''),subject,text FROM ".$DBHOST_TBL_PREFIX."announce_tmp");
			q("DROP TABLE ".$DBHOST_TBL_PREFIX."announce_tmp");
		}
		qf($r);
	}

	/* convert avatars 
	 * At one point we linked to remote avatars and the URL was stored inside avatar_loc
	 * then in 2.5.0 we've began using avatar_loc to store cached <img src>
	*/
	if (!isset($GLOBALS['ENABLE_THREAD_RATING'])) { /* < 2.5.0 */
		show_debug_message('Creating Avatar Cache');

		if (q_singleval('select count(*) FROM '.$DBHOST_TBL_PREFIX.'users WHERE avatar_loc LIKE \'http://%\'')) { /* < 2.1.3 */
			$c = q('SELECT id, avatar_loc FROM '.$DBHOST_TBL_PREFIX.'users WHERE avatar_loc IS NOT NULL AND avatar_loc!=\'\'');
			while ($r = db_rowarr($c)) {
				$path = cache_avatar_image($r[1], $r[0]);
				if ($path) {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET avatar_loc=\''.addslashes($path).'\' WHERE id='.$r[0]);
				} else {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET avatar_loc=NULL, avatar_approved=\'NO\' WHERE id='.$r[0]);
				}
			}
			qf($c);
		}
		$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');
		$c = q('SELECT u.id, u.avatar, a.img, u.avatar_approved FROM '.$DBHOST_TBL_PREFIX.'users u LEFT JOIN '.$DBHOST_TBL_PREFIX.'avatar a ON u.avatar=a.id WHERE (u.avatar_approved IN(\'Y\', \'N\') AND (u.avatar_loc IS NULL OR u.avatar_loc=\'\')) OR u.avatar>0');
		while ($r = db_rowarr($c)) {
			if ($r[1]) { /* built-in avatar */
				if (!isset($av_cache[$r[1]])) {
					$im = getimagesize($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $r[2]);
					$av_cache[$r[1]] = '<img src="'.$GLOBALS['WWW_ROOT'].'images/avatars/'. $r[2] .'" '.$im[3].' />';
				}
				$path = $av_cache[$r[1]];
				$avatar_approved = 'Y';
			} else if (($im = getimagesize($GLOBALS['WWW_ROOT_DISK'] . 'images/custom_avatars/' . $r[0]))) { /* custom avatar */
				$path = '<img src="'.$GLOBALS['WWW_ROOT'].'images/custom_avatars/'. $r[0] . '.' . $ext[$im[2]].'" '.$im[3] .' />';
				rename($GLOBALS['WWW_ROOT_DISK'] . 'images/custom_avatars/' . $r[0], $GLOBALS['WWW_ROOT_DISK'] . 'images/custom_avatars/' . $r[0] . '.' . $ext[$im[2]]);
				$avatar_approved = $r[3];
			}
			if ($path) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET avatar_loc=\''.addslashes($path).'\', avatar_approved=\''.$avatar_approved.'\' WHERE id='.$r[0]);
			} else {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET avatar_loc=NULL, avatar_approved=\'NO\' WHERE id='.$r[0]);
			}
		}
		qf($c);
	}
	
	/* Add data into pdest field of pmsg table */
	if (q_singleval('SELECT count(*) FROM '.$DBHOST_TBL_PREFIX.'pmsg WHERE pdest>0')) {
		show_debug_message('Populating pdest field for private messages');
		$r = q("SELECT to_list, id FROM ".$DBHOST_TBL_PREFIX."pmsg WHERE folder_id='SENT' AND duser_id=ouser_id");
		while (list($l, $id) = db_rowarr($r)) {
			if (!($uname = strtok($l, ';'))) {
				continue;
			}
			if (!($uid = q_singleval("select id from ".$DBHOST_TBL_PREFIX."users where login='".addslashes($uname)."'"))) {
				continue;
			}
		
			q('UPDATE '.$DBHOST_TBL_PREFIX.'pmsg SET pdest='.$uid.' WHERE id='.$id);
		}
		qf($r);
	}

	if (!q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE t_default='Y'")) {
		show_debug_message('Setting default theme');
		$pspell_lang = @trim(file_get_contents($GLOBALS['DATA_DIR'] . '/thm/default/i18n/' . $GLOBALS['LANGUAGE'] . '/pspell_lang'));
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."themes(id, name, theme, lang, locale, enabled, t_default, pspell_lang) VALUES(1, 'default', 'default', '".$GLOBALS['LANGUAGE']."', '".$GLOBALS['LOCALE']."', 'Y', 'Y', '".$pspell_lang."')");
		q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET theme=1');
	}
	/* theme fixer upper for the admin users lacking a proper theme
	 * this is essential to ensure the admin user can login
	 */
	$df_theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE t_default='Y'");
	$c = q('SELECT u.id FROM '.$DBHOST_TBL_PREFIX.'users u LEFT JOIN '.$DBHOST_TBL_PREFIX.'themes t ON t.id=u.theme WHERE u.is_mod=\'A\' AND t.id IS NULL');
	while ($r = db_rowarr($c)) {
		$bt[] = $r[0];
	}
	qf($c);
	if (isset($bt)) {
		q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET theme='.$df_theme.' WHERE id IN('.implode(',', $bt).')');
	}

	/* encode user alias according to new format */
	if (!isset($GLOBALS['USE_ALIASES'])) {
		show_debug_message('Updating aliases');
		$c = q('SELECT id, alias FROM '.$DBHOST_TBL_PREFIX.'users');
		while ($r = db_rowarr($c)) {
			$alias = htmlspecialchars((strlen($r[1]) > $GLOBALS['MAX_LOGIN_SHOW'] ? substr($r[1], 0, $GLOBALS['MAX_LOGIN_SHOW']) : $r[1]));
			if ($alias != $r[1]) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET alias=\''.addslashes($alias).'\' WHERE id='.$r[0]);
			}
		}
		qf($c);
	}
	
	/* store file attachment sizes inside db */
	if (q_singleval('select count(*) from '.$DBHOST_TBL_PREFIX.'attach WHERE fsize=0')) {
		show_debug_message('Updating file sizes of attachments');
		$c = q('SELECT id, location FROM '.$DBHOST_TBL_PREFIX.'attach WHERE fsize=0');
		while ($r = db_rowarr($c)) {
			q('UPDATE '.$DBHOST_TBL_PREFIX.'attach SET fsize='.(int)@filesize($r[1]).' WHERE id='.$r[0]);
		}
		qf($c);
	}

	if (!q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE id=1 AND email=\'dev@null\' AND is_mod!=\'A\'')) {
		show_debug_message('Reserving id for anon users');
		if (($u = (array) @db_rowobj(q('SELECT * FROM '.$DBHOST_TBL_PREFIX.'users WHERE id=1'))) && !isset($u[0])) {
			q('DELETE FROM '.$DBHOST_TBL_PREFIX.'users WHERE id=1');
			unset($u['id']);
			$f = $d = '';
			foreach ($u as $k => $v) {
				if ($v) {
					$d .= "'" . addslashes($v) . "',";
					$f .= $k . ',';
				}
			}
			q('INSERT INTO '.$DBHOST_TBL_PREFIX.'users ('.rtrim($f, ',').') VALUES('.rtrim($d, ',').')');
			$new_id = q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE login=\''.addslashes($u['login']).'\'');
		
			$tbl_list = array('action_log', 'buddy', 'custom_tags', 'forum_notify', 'forum_read', 'group_cache', 'group_members', 'mod', 'msg_report', 'poll_opt_track', 'read', 'ses', 'thread_notify', 'thread_rate_track', 'user_ignore');
			foreach ($tbl_list as $t) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.$t.' SET user_id='.$new_id.' WHERE user_id=1');
			}
			q('UPDATE '.$DBHOST_TBL_PREFIX.'pmsg SET ouser_id='.$new_id.' WHERE ouser_id=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'pmsg SET duser_id='.$new_id.' WHERE duser_id=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'poll SET owner='.$new_id.' WHERE owner=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'poll_opt_track SET user_id='.$new_id.' WHERE user_id=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'attach SET owner='.$new_id.' WHERE owner=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'msg SET poster_id='.$new_id.' WHERE poster_id=1');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'msg SET updated_by='.$new_id.' WHERE updated_by=1');
		}
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users (id, login, alias, display_email, pm_messages, append_sig, time_zone, default_view, is_mod,acc_status, theme, email, passwd, name) VALUES(1, 'Anonymous Coward', 'Anonymous Coward', 'N', 'N', 'N', 'America/Montreal', 'msg', 'N', 'A', 1, 'dev@null', '1', 'Anonymous Coward')");
	}

	/* since 2.5.0 for each poll tracking entry we store the id for the voter */
	if (!isset($GLOBALS['ENABLE_THREAD_RATING'])) { /* < 2.5.0 */
		$c = q('SELECT id, poll_id, count FROM '.$DBHOST_TBL_PREFIX.'poll_opt WHERE count>0');
		while ($r = db_rowarr($c)) {
			q('UPDATE '.$DBHOST_TBL_PREFIX.'poll_opt_track SET poll_opt='.$r[0].' WHERE poll_id='.$r[1].' AND poll_opt=0 LIMIT '.$r[2]);
		}
		qf($c);
	}

	if (!q_singleval('SELECT * FROM '.$DBHOST_TBL_PREFIX.'stats_cache')) {
		q('INSERT INTO '.$DBHOST_TBL_PREFIX.'stats_cache VALUES(0,0,0,0,0,0,0)');
	}

	/* Add any needed GLOBAL OPTIONS */

	show_debug_message('Adding GLOBAL Variables');
	$s = strpos($data, '116304110503_\\GLOBAL_VARS_START_HERE') + strlen('116304110503_\\GLOBAL_VARS_START_HERE');
	$e = strpos($data, '116304110503_\\GLOBAL_VARS_END_HERE', $s);
	$gvar_data = substr($data, $s, ($e - $s));
	$gvars = explode("\n", $gvar_data);
	$add_g = '';

	foreach ($gvars as $v) {
		if (!($v = ltrim($v))) {
			continue;
		}
		$varname = strtok($v, "\t");
		if (!isset($GLOBALS[$varname])) {
			$add_g .= "\t" . $v . "\n";
		}
	}
	if (isset($no_data_dir)) {
		$add_g .= '$DATA_DIR       = "'.realpath($GLOBALS['TEMPLATE_DIR'].'../').'/";';
	} 
	if ($add_g || isset($GLOBALS['MYSQL_SERVER'])) {
		$gf = file_get_contents($GLOBALS['DATA_DIR'] . 'include/GLOBALS.php');
		if (isset($GLOBALS['MYSQL_SERVER'])) {
			$gf = strtr($gf, array(
						'MYSQL_SERVER' => 'DBHOST', 
						'MYSQL_LOGIN' => 'DBHOST_USER', 
						'MYSQL_PASSWORD' => 'DBHOST_PASSWORD', 
						'MYSQL_DB' => 'DBHOST_DBNAME', 
						'MYSQL_PERSIST' => 'DBHOST_PERSIST', 
						'MYSQL_TBL_PREFIX' => 'DBHOST_TBL_PREFIX'
			));
		}
		if ($add_g) {
			$p = strpos($gf, '$ALLOW_REGISTRATION') - 1;
			$gf = substr_replace($gf, $add_g . "\n", $p, 0);
		}
		$fp = fopen($GLOBALS['DATA_DIR'] . 'include/GLOBALS.php', 'wb');
		fwrite($fp, $gf);
		fclose($fp);
	}
	
	if (@file_exists($GLOBALS['WWW_ROOT_DISK'] . 'thread.php')) { /* remove useless files from old installs */
		show_debug_message('Removing bogus files');
		$d = opendir(rtrim($GLOBALS['WWW_ROOT_DISK'], '/'));
		readdir($d); readdir($d);
		while ($f = readdir($d)) {
			if (!is_file($GLOBALS['WWW_ROOT_DISK'] . $f)) {
				continue;
			}
			switch ($f) {
				case 'index.php':
				case 'GLOBALS.php':
				case 'upgrade.php':
				case 'upgrade_safe.php':
				case 'lib.js':
				case 'blank.gif':
				case 'php.php':
					break;
				default:
					unlink($GLOBALS['WWW_ROOT_DISK'] . $f);
				
			}
		}
		closedir($d);
		if (@is_dir(rtrim($GLOBALS['TEMPLATE_DIR'], '/'))) {
			rename(rtrim($GLOBALS['TEMPLATE_DIR'], '/'), $GLOBALS['ERROR_PATH'].'.backup/template_'.__time__);
		}
	}

	/* Compile The Forum */
	require($GLOBALS['DATA_DIR'] . 'include/compiler.inc');

	/* list of absolete template files that should be removed */
	$rm_tmpl = array('rview.tmpl', 'allperms.tmpl','avatar.tmpl','cat.tmpl','cat_adm.tmpl','customtags.tmpl','forum_adm.tmpl','ilogin.tmpl','init_errors.tmpl', 'ipfilter.tmpl','mime.tmpl','msgreport.tmpl','objutil.tmpl','que.tmpl', 'theme.tmpl', 'time.tmpl', 'url.tmpl', 'users_adm.tmpl', 'util.tmpl', 'core.tmpl', 'path_info.tmpl');

	$c = q("SELECT theme, lang, name FROM ".$DBHOST_TBL_PREFIX."themes WHERE enabled='Y' OR id=1");
	while ($r = db_rowobj($c)) {
		// See if custom themes need to have their files updated
		if ($r->theme != 'default' && $r->theme != 'path_info') {
			syncronize_theme($r->theme);
		}
		foreach ($rm_tmpl as $f) {
			@unlink($GLOBALS['DATA_DIR'].'thm/'.$r->theme.'/tmpl/' . $f);
		}
		show_debug_message('Compiling theme '.$r->name);
		compile_all($r->theme, $r->lang, $r->name);
	}
	qf($c);

	/* Insert update script marker */
	$fp = fopen($GLOBALS['ERROR_PATH'] . 'UPGRADE_STATUS', 'wb');
	fwrite($fp, $__UPGRADE_SCRIPT_VERSION);
	fclose($fp);

	if (SAFE_MODE && basename(__FILE__) == 'upgrade_safe.php') {
		unlink(__FILE__);
	}
	@unlink("fudforum_archive");
?>
<br>Executing Consistency Checker (if the popup with the consistency checker failed to appear you <a href="javascript://" onClick="javascript: window.open(\'adm/consist.php?enable_forum=1\');">MUST click here</a><br>
<script>
	window.open('adm/consist.php?enable_forum=1');
</script>
<font color="red" size="4">PLEASE REMOVE THIS FILE (<?php echo realpath('upgrade.php'); ?>) UPON COMPLETION OF THE UPGRADE PROCESS.<br>THIS IS IMPERATIVE, OTHERWISE ANYONE COULD RUN THIS SCRIPT!</font>
</body>
</html>
<?php exit; ?>
042252166145_\SQL_START_HERE
msg::ADD_COLUMN::offset_preview::INT::UNSIGNED::::0
msg::ADD_COLUMN::length_preview::INT::UNSIGNED::::0
msg::ADD_COLUMN::file_id_preview::INT::UNSIGNED::::0
msg::ALTER_COLUMN::offset::foff::INT::UNSIGNED::::0
msg::ADD_COLUMN::mlist_msg_id::VARCHAR::(100)::1
msg::ADD_INDEX::INDEX::mlist_msg_id::{SQL_TABLE_PREFIX}msg_index_mlist_msg_id
msg::ADD_INDEX::INDEX::subject::{SQL_TABLE_PREFIX}msg_index_subject
thread::DROP_COLUMN::replyallowed
thread::ADD_INDEX::INDEX::is_sticky,orderexpiry::{SQL_TABLE_PREFIX}thread_idx_is_sticky_orderexpiry
users::ADD_COLUMN::alias::CHAR::(50)::::''::::UPDATE {SQL_TABLE_PREFIX}users SET alias=login
users::ALTER_COLUMN::private_messages::email_messages::ENUM::('Y', 'N')::::'Y'
users::ADD_COLUMN::show_sigs::ENUM::('Y', 'N')::::'Y'
users::ADD_COLUMN::show_avatars::ENUM::('Y', 'N')::::'Y'
users::ADD_INDEX::UNIQUE::alias::{SQL_TABLE_PREFIX}users_index_alias
users::DROP_COLUMN::style
users::ADD_COLUMN::theme::INT::UNSIGNED::::0
users::ADD_COLUMN::pm_messages::ENUM::('Y', 'N')::::'Y'
users::ADD_COLUMN::show_im::ENUM::('Y', 'N')::::'Y'
users::ALTER_COLUMN::default_view::default_view::ENUM::('msg', 'tree', 'msg_tree', 'tree_msg')::::'msg'
smiley::ADD_COLUMN::vieworder::INT::UNSIGNED::
action_log::ALTER_COLUMN::logaction::logaction::CHAR::(100)::1
action_log::ADD_COLUMN::a_res::CHAR::(100)::1
action_log::ADD_COLUMN::a_res_id::INT::UNSIGNED::::0
replace::ALTER_COLUMN::type::type::ENUM::('REPLACE', 'PERL')::::'REPLACE'
cat::DROP_COLUMN::hidden
cat::DROP_COLUMN::creation_date
forum::DROP_COLUMN::hidden
forum::ADD_COLUMN::message_threshold::INT::UNSIGNED::::0
forum::DROP_INDEX::INDEX::hidden::{SQL_TABLE_PREFIX}forum_index_hidden
forum::ADD_INDEX::INDEX::last_post_id::{SQL_TABLE_PREFIX}forum_index_last_post_id
group_members::ADD_COLUMN::up_VISIBLE::ENUM::('Y', 'N')::::'N'
group_members::ALTER_COLUMN::up_VIEW::up_READ::ENUM::('Y', 'N')::::'N'
group_members::QUERY::UPDATE {SQL_TABLE_PREFIX}group_members SET user_id=2147483647 WHERE user_id=4294967295
group_cache::ADD_COLUMN::p_VISIBLE::ENUM::('Y', 'N')::::'N'
group_cache::ALTER_COLUMN::p_VIEW::p_READ::ENUM::('Y', 'N')::::'N'
group_cache::QUERY::UPDATE {SQL_TABLE_PREFIX}group_cache SET user_id=2147483647 WHERE user_id=4294967295
groups::ALTER_COLUMN::p_VIEW::p_READ::ENUM::('I', 'Y', 'N')::::'N'
themes::ADD_TABLE_DB
themes::ADD_COLUMN::pspell_lang::CHAR::(32)::1
pmsg::ADD_INDEX::INDEX::duser_id,folder_id,id::{SQL_TABLE_PREFIX}pmsg_index_duser_id_fid_id
pmsg::ALTER_COLUMN::offset::foff::INT::UNSIGNED::::0
pmsg::ALTER_COLUMN::length::length::INT::UNSIGNED::::0
pmsg::ADD_COLUMN::pdest::INT::UNSIGNED::::0
thread_view::ADD_COLUMN::tmp::INT::UNSIGNED::1
thread_view::ALTER_COLUMN::pos::pos::INT::UNSIGNED::::::AUTO_INCREMENT::1
ses::ADD_COLUMN::forum_id::INT::UNSIGNED::::0
mlist::ADD_TABLE_DB
nntp::ADD_TABLE_DB
attach::ADD_COLUMN::fsize::INT::::::0
users::ADD_COLUMN::cat_collapse_status::TEXT::::::
nntp::ADD_COLUMN::create_users::ENUM::('Y', 'N')::::'Y'
mlist::ADD_COLUMN::create_users::ENUM::('Y', 'N')::::'Y'
thread_notify::ADD_INDEX::INDEX::thread_id::{SQL_TABLE_PREFIX}thread_notify_index_thread_id
forum_notify::ADD_INDEX::INDEX::forum_id::{SQL_TABLE_PREFIX}forum_notify_index_forum_id
read::ADD_INDEX::INDEX::user_id::{SQL_TABLE_PREFIX}read_index_user_id
users::ADD_COLUMN::acc_status::ENUM::('A', 'P')::::'A'
users::ADD_INDEX::INDEX::acc_status::{SQL_TABLE_PREFIX}users_index_acc_status
pmsg::ALTER_COLUMN::folder_id::folder_id::ENUM::('INBOX', 'SAVED', 'SENT','DRAFT','TRASH', 'PROC')::::'PROC'
users::ADD_COLUMN::custom_color::VARCHAR::(255)::1::NULL
mlist::ADD_COLUMN::additional_headers::TEXT::::::
ip_block::ALTER_COLUMN::ca::ca::INT::UNSIGNED::::0
ip_block::ALTER_COLUMN::cb::cb::INT::UNSIGNED::::0
ip_block::ALTER_COLUMN::cc::cc::INT::UNSIGNED::::0
ip_block::ALTER_COLUMN::cd::cd::INT::UNSIGNED::::0
users::ADD_COLUMN::affero::VARCHAR::(255)::1::NULL
blocked_logins:::DROP_INDEX::UNIQUE::login::{SQL_TABLE_PREFIX}blocked_logins_index_login
ext_block::DROP_INDEX::UNIQUE::ext::{SQL_TABLE_PREFIX}ext_block_index_ext
forum_read::DROP_INDEX::INDEX::forum_id,user_id::{SQL_TABLE_PREFIX}forum_read_index_forum_id_user_id
forum_read::ADD_INDEX::UNIQUE::forum_id,user_id::{SQL_TABLE_PREFIX}forum_read_index_forum_id_user_id::clean_forum_read_table
group_cache::DROP_INDEX::INDEX::user_id,group_id::{SQL_TABLE_PREFIX}gci_uid_group_id
group_cache::DROP_INDEX::INDEX::user_id,resource_type,p_READ,p_VISIBLE::{SQL_TABLE_PREFIX}gci_uid_rt_p_READ_p_VISIBLE ON
group_cache::DROP_INDEX::INDEX::user_id,resource_type,p_VISIBLE::{SQL_TABLE_PREFIX}gci_uid_rt_p_VISIBLE
group_cache::DROP_INDEX::UNIQUE::user_id,resource_type,resource_id::{SQL_TABLE_PREFIX}gci_user_id_resource_type_resource_id
group_cache::ADD_INDEX::INDEX::resource_id,user_id::{SQL_TABLE_PREFIX}resource_id_user_id
group_cache::DROP_COLUMN::resource_type
group_members::DROP_INDEX::INDEX::user_id,group_leader::{SQL_TABLE_PREFIX}group_members_index_user_id_group_leader
group_members::ADD_INDEX::INDEX::group_leader::{SQL_TABLE_PREFIX}group_members_index_group_leader
group_resources::DROP_INDEX::INDEX::group_id,resource_type,resource_id::{SQL_TABLE_PREFIX}group_resources_index_group_id_resource_type_resource_id
group_resources::DROP_COLUMN::resource_type
group_resources::ADD_INDEX::INDEX::group_id,resource_id::{SQL_TABLE_PREFIX}group_resources_index_group_id_resource_id
group_resources::ADD_INDEX::INDEX::resource_id::{SQL_TABLE_PREFIX}group_resources_index_resource_id
groups::DROP_COLUMN::res
groups::ALTER_COLUMN::res_id::forum_id::INT::UNSIGNED::::0
groups::ADD_INDEX::INDEX::forum_id::{SQL_TABLE_PREFIX}groups_forum_id
groups::ADD_INDEX::INDEX::inherit_id::{SQL_TABLE_PREFIX}groups_inherit_id
ip_block::DROP_INDEX::INDEX::ca::{SQL_TABLE_PREFIX}ip_block_index_ca
ip_block::DROP_INDEX::INDEX::cb::{SQL_TABLE_PREFIX}ip_block_index_cb
ip_block::DROP_INDEX::INDEX::cc::{SQL_TABLE_PREFIX}ip_block_index_cc
ip_block::DROP_INDEX::INDEX::cd::{SQL_TABLE_PREFIX}ip_block_index_cd
msg::ADD_COLUMN::attach_cache::TEXT::::1
msg::ADD_COLUMN::poll_cache::TEXT::::1
poll::ADD_COLUMN::total_votes::INT::UNSIGNED::::0
poll::ADD_COLUMN::forum_id::INT::UNSIGNED::::0
poll::ADD_INDEX::INDEX::owner::{SQL_TABLE_PREFIX}poll_idx_owner
poll_opt_track::ADD_COLUMN::poll_opt::INT::UNSIGNED::::0
read::DROP_INDEX::INDEX::thread_id,user_id::{SQL_TABLE_PREFIX}read_index_thread_id_user_id
read::ADD_INDEX::UNIQUE::thread_id,user_id::{SQL_TABLE_PREFIX}read_index_thread_id_user_id::clean_read_table
ses::ADD_COLUMN::returnto::VARCHAR::(255)::1::
thread::ADD_COLUMN::n_rating::INT::UNSIGNED::::0
thread::ADD_INDEX::INDEX::root_msg_id::{SQL_TABLE_PREFIX}thread_idx_root_msg_id
thread::ADD_INDEX::INDEX::replies::{SQL_TABLE_PREFIX}thread_idx_replies
users::ALTER_COLUMN::avatar_loc::avatar_loc::TEXT::::1
users::ADD_COLUMN::buddy_list::TEXT::::1
users::ADD_COLUMN::ignore_list::TEXT::::1
users::ADD_COLUMN::group_leader_list::TEXT::::1
stats_cache::ADD_TABLE_DB
search_cache::ADD_TABLE_DB
042252166145_\SQL_END_HERE

116304110503_\GLOBAL_VARS_START_HERE
$FORUM_IMG_CNT_SIG	= "2";          /* int */
$MAX_SMILIES_SHOWN	= "15";         /* int */
$SHOW_N_MODS		= "2";
$NOTIFY_WITH_BODY	= "N";          /* boolean */
$USE_ALIASES		= "N";		/* boolean */
$MULTI_HOST_LOGIN	= "N";		/* boolean */
$USE_SMTP		= "N";		/* boolean */
$FUD_SMTP_SERVER	= "";
$FUD_SMTP_TIMEOUT	= "10";		/* seconds */
$FUD_SMTP_LOGIN		= "";
$FUD_SMTP_PASS		= "";
$FILE_LOCK		= "Y";		/* boolean */
$POLLS_PER_PAGE		= "40";		/* int */
$TREE_THREADS_ENABLE	= "N";		/* boolean */
$TREE_THREADS_MAX_DEPTH	= "15";		/* int */
$TREE_THREADS_MAX_SUBJ_LEN	= "75";	/* int */
$MODERATE_USER_REGS 	= "N";		/* boolean */
$REG_TIME_LIMIT		= "60";		/* seconds */
$AVATAR_ALLOW_SWF       = "N";          /* boolean */
$SESSION_USE_URL        = "Y";          /* boolean */
$SEARCH_CACHE_EXPIRY    = "172800";     /* seconds */
$STATS_CACHE_AGE        = "600";        /* seconds */
$ENABLE_THREAD_RATING   = "Y";          /* boolean */
$TRACK_REFERRALS        = "Y";          /* boolean */
$POST_ICONS_PER_ROW     = "9";          /* int */
$MAX_LOGGEDIN_USERS     = "25";         /* int */
$ENABLE_AFFERO          = "N";		/* boolean */
$USE_PATH_INFO		= "N";		/* boolean */
$PHP_COMPRESSION_ENABLE	= "N";		/* boolean */
$PHP_COMPRESSION_LEVEL	= "9";		/* int 1-9 */
$ALLOW_PROFILE_IMAGE	= "Y";		/* boolean */
$NEW_ACCOUNT_NOTIFY	= "Y";		/* boolean */
$MODERATED_POST_NOTIFY	= "Y";		/* boolean */
$BUST_A_PUNK		= "N";		/* boolean */
116304110503_\GLOBAL_VARS_END_HERE

2105111608_\ARCH_START_HERE
