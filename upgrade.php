<?php
/* ���ظ ���� )�)����  	
 * First 20 bytes of linux 2.4.18, so various windows utils think
 * this is a binary file and don't apply CRLF logic
 */

/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: upgrade.php,v 1.88 2002/09/17 20:12:26 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$__UPGRADE_SCRIPT_VERSION = 360;

define('admin_form', '1', 1);

function read_global_config()
{
	return filetomem($GLOBALS['__GLOBALS.INC__']);
}

function write_global_config($data)
{
	$fp = fopen($GLOBALS['__GLOBALS.INC__'], 'wb');
		fwrite($fp, $data);
	fclose($fp);
}

function change_global_val($name, $val, &$data)
{
	if( ($s=strpos($data, '$'.$name." ")) === false ) $s=strpos($data, '$'.$name."\t");

	if( $s !== false ) {
		$s = strpos($data, '"', $s)+1;
		$e = strpos($data, '";', $s);
		
		$data = substr_replace($data, $val, $s, ($e-$s));
	}
	else { /* Adding new option */
		$s = strpos($data, '$ALLOW_REGISTRATION')-1;
		$data = substr_replace($data, "\t\$$name\t= \"$val\";\n", $s, 0);
	}
}

function global_config_ar($data)
{
	$ar = array();

	while( ($pos = strpos($data, '$')) ) {
		$line = substr($data, $pos, (($le=strpos($data, "\n", $pos))-$pos));
		
		$tp = strpos($line, "\t");
		$ts = strpos($line, " ");
		
		if( $tp === false )
			$key_end = $ts;
		else if( $ts === false )
			$key_end = $tp;	
		else if( $ts > $tp ) 
			$key_end = $tp;	
		else if( $tp > $ts )
			$key_end = $ts;	
		
		$key = rtrim(substr($line, 1, $key_end-1));	
		if( $key == strtoupper($key) && !strpos($key, ']') ) {
			if( ($vs = strpos($line, '"', $key_end)) ) {
				$vs++;
				$ve = strpos($line, '";', $vs);
				$val = substr($line, $vs, ($ve-$vs));
				
				$ar[$key]=$val;
			}
		}
		
		$data = substr($data, $le+1);
	}
	
	return $ar;
}

function filetomem($fn)
{
	if ( !($fp = @fopen($fn, 'rb')) ) {
		echo "Unable to open (<b>$fn</b>) in (<b>".getcwd()."</b>)<br>";
		return;
	}
	$st = fstat($fp);
	$size = isset($st['size']) ? $st['size'] : $st[7];
	$str = fread($fp, $size);
	fclose($fp);
	
	return $str;
}

function versiontoint($str)
{
	$modifier = 0;
	if( preg_match('!RC([0-9]+)!', $str, $ret) ) {
		$modifier = (float) (100-$ret[1])/100000;
		$str = str_replace($ret[0], '', $str);
	}	

	$str = preg_replace('![^0-9]!', '', $str);
	return (float) substr_replace($str, '.', 1, 0)-$modifier;
}

function backupfile($source)
{
	$dir = $GLOBALS['ERROR_PATH'].'.backup';
	if( !@is_dir($dir) ) { 
		$m = umask(0);
		mkdir($dir, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0700 : 0755));
		umask($m);
	}	

	if( basename($source) == 'core.inc' ) {
		$GLOBALS['core_backup'] = $dir.'/'.basename($source).'.'.get_random_value();
		copy($source, $GLOBALS['core_backup']);
	}
	else 
		copy($source, $dir.'/'.basename($source).'.'.get_random_value());
}

function __mkdir($dir)
{
	clearstatcache();
	
	if( @is_dir($dir) ) return 1;
	
	$m = umask(0);
	if( !($ret = @mkdir($dir, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0700 : 0755))) ) 
		$ret = @mkdir(dirname($dir), ($GLOBALS['FILE_LOCK'] == 'Y' ? 0700 : 0755));
	umask($m);
	
	return $ret;
}

function fetch_cvs_id($data)
{
	if( ($s = strpos($data, '$Id')) === false ) return;
	if( ($e = strpos($data, 'Exp $', $s)) === false ) return;
	return substr($data, $s, ($e-$s));
}

function syncronize_theme_dir($theme, $dir)
{
	if( !@is_dir($GLOBALS['DATA_DIR'].'thm/'.$theme.'/'.$dir) ) {
		if( !__mkdir($GLOBALS['DATA_DIR'].'thm/'.$theme.'/'.$dir, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0700 : 0755)) ) {
			exit("Directory ".$GLOBALS['DATA_DIR'].'thm/'.$theme.'/'.$dir." does not exist, and the upgrade script failed to create it.<br>\n");
		}
	}

	$dp = opendir($GLOBALS['DATA_DIR'].'thm/default/'.$dir);
	if( !$dp ) exit("Failed to open ".$GLOBALS['DATA_DIR'].'thm/default/'.$dir."<br>\n");
	
	readdir($dp); readdir($dp);
	while( $file = readdir($dp) ) {
	
		if( is_dir($file) ) {
			syncronize_theme_dir($theme, $dir.'/'.$file);
			continue;
		}	
		
		$source_file = $GLOBALS['DATA_DIR'].'thm/default/'.$dir.'/'.$file;
		$dest_file = $GLOBALS['DATA_DIR'].'thm/'.$theme.'/'.$dir.'/'.$file;
		
		if( !@file_exists($dest_file) ) {
			if( @is_dir($source_file) ) {
				syncronize_theme_dir($theme, $dir.'/'.$file);
				continue;
			}
			
			if( !copy($source_file, $dest_file) )
				exit("Failed to copy $source_file to $dest_file, check permissions then run this scripts again<br>\n");
		}
		else {
			// Skip images, we do not need to replace them.
			if( preg_match('!/images/.*\.gif!', $path) ) continue;
		
			$source_data = filetomem($source_file);
			$dest_data = filetomem($dest_file);
			
			if( md5($source_data) == md5($dest_data) ) continue;
			if( fetch_cvs_id($source_data) ==  fetch_cvs_id($dest_data) ) continue; 
			
			backupfile($dest_file);
			
			$fp = fopen($dest_file, "wb");
			fwrite($fp, $source_data);
			fclose($fp);
		}
			
	}
	closedir($dp);
}

function syncronize_theme($theme)
{
	syncronize_theme_dir($theme, 'tmpl');
	syncronize_theme_dir($theme, 'i18n');
}

function upgrade_decompress_archive($data_root, $web_root, $data)
{
	$pos = strpos($data, "2105111608_\\ARCH_START_HERE");

	if( $pos === false ) exit("Couldn't locate start of archive<br>\n");
	
	$data = substr($data, $pos+strlen("2105111608_\\ARCH_START_HERE")+1);
	if( !($data = gzuncompress(str_replace('PHP_OPEN_TAG', '<?', $data))) ) {
		exit("Failed decompressing the archive");
	}
	$data = "\n".$data;
	
	$pos=0;
	
	$oldmask = umask(($GLOBALS['FILE_LOCK'] == 'Y' ? 0177 : 0111));
	
	while( ($pos = strpos($data, "\n//", $pos)) !== false ) {
		$end = strpos($data, "\n", $pos+1);
		$meta_data = explode('//',  substr($data, $pos, ($end-$pos)));
		$pos = $end;
		
		if( $meta_data[3] == '/install' || !isset($meta_data[3]) ) continue;
		
		$path = preg_replace('!^/install/forum_data!', $data_root, $meta_data[3]);
		$path = preg_replace('!^/install/www_root!', $web_root, $path);
		$path .= "/".$meta_data[1];
		
		$path = str_replace("//", "/", $path);
		
		if( isset($meta_data[5]) ) {
			$file = substr($data, ($pos+1), $meta_data[5]);
			if( md5($file) != $meta_data[4] ) exit("ERROR: file ".$meta_data[1]." not read properly from archive\n");
			
			if( $meta_data[1] == 'GLOBALS.php' ) continue;
			
			if( @file_exists($path) ) {
				$ofile=filetomem($path);
				/* Skip the file because it is the same */
				if( md5($file) == md5($ofile) ) continue;
			
				/* Compare CVS Id to ensure we do not pointlessly replace files modified by the user */
				if( ($cvsid=fetch_cvs_id($file)) == fetch_cvs_id($ofile) && $cvsid ) continue;
				
				backupfile($path);
			}
			
			$fp = @fopen($path, 'wb');
			if( !$fp ) {
				if( $GLOBALS['core_backup'] ) copy($GLOBALS['core_backup'], $GLOBALS['DATA_DIR'].'include/core.inc');
				exit("Couldn't open $path for write<br>\n");
			}	
				fwrite($fp, $file);
			fclose($fp);
		}
		else {
			if( substr($path, -1) == '/' ) $path = preg_replace('!/+$!', '', $path);
			clearstatcache();
			if( !@is_dir($path) && !__mkdir($path) ) {
				if( $GLOBALS['core_backup'] ) copy($GLOBALS['core_backup'], $GLOBALS['DATA_DIR'].'include/core.inc');
				exit("ERROR: failed creating $path directory<br>\n");
			}	
		}
	}
	umask($oldmask);
	unset($ofile);
}

function fetch_img($url)
{
	$ub = parse_url($url);
	
	if( empty($ub['port']) ) $ub['port'] = 80;
	if( !empty($ub['query']) ) $ub['path'] .= '?'.$ub['query'];
	
	$fs = fsockopen($ub['host'], $ub['port'], $errno, $errstr, 10);
	if( !$fs ) return;
	
	fputs($fs, "GET ".$ub['path']." HTTP/1.0\r\nHost: ".$ub['host']."\r\n\r\n");
	
	$ret_code = fgets($fs, 255);
	
	if( !strstr($ret_code, '200') ) {
		fclose($fs);
		return;
	}
	
	$img_str = '';
	
	while( !feof($fs) && strlen($img_str)<$GLOBALS['CUSTOM_AVATAR_MAX_SIZE'] ) 
		$img_str .= fread($fs, $GLOBALS['CUSTOM_AVATAR_MAX_SIZE']);
	fclose($fs);
	
	$img_str = substr($img_str, strpos($img_str, "\r\n\r\n")+4);

	$fp = false;
	do {
		if ( $fp ) fclose($fp);
		$fp = fopen(($path=tempnam($GLOBALS['TMP'],getmypid())), 'ab');
	} while ( ftell($fp) );
	
	fwrite($fp, $img_str);
	fclose($fp);
	
	if( function_exists("GetImageSize") && !@GetImageSize($path) ) { unlink($path); return; }
		
	return $path;
}

function match_mysql_index($table, $fields, $idx_type)
{
	$m = array();
	$r = q("show index from ".$table);
	while( $obj = db_rowobj($r) ) {
		if( isset($m[$obj->Key_name]) )
			$m[$obj->Key_name][0] .= ','.$obj->Column_name;
		else 
			$m[$obj->Key_name] = array(0=>$obj->Column_name, 1=>$obj->Non_unique);
	}
	qf($r);
	
	$fields = preg_replace('!\s+!', '', $fields);
	
	foreach($m as $index) {
		if( (($idx_type=='INDEX' && $index[1] == 1) || ($idx_type=='UNIQUE' && $index[1] == 0))
			 && (strtolower($index[0]) == strtolower($fields)) ) return 1;
	}
	
	return;
}

function get_pgsql_row_info($table, $field)
{
	$obj = q_singleobj(q("SELECT a.attname, format_type(a.atttypid, a.atttypmod), a.attnotnull, a.atthasdef, a.attnum FROM pg_class c, pg_attribute a WHERE c.relname = '".$table."' AND a.attnum > 0 AND a.attrelid = c.oid AND a.attname='".$field."'"));
	$obj->default = q_singleval("SELECT substring(d.adsrc for 128) FROM pg_attrdef d, pg_class c WHERE c.relname = 'fud_msg' AND c.oid = d.adrelid AND d.adnum = ".$obj->attnum);

	foreach( $obj as $ent )
		$obj->$ent = str_replace('character varying', 'varchar', $obj->$ent);
		
	return $obj;
}

function get_table_defenition($table)
{
	$d_dir = isset($GLOBALS['DATA_DIR']) ? $GLOBALS['DATA_DIR'] : realpath($GLOBALS['INCLUDE'].'../').'/';

	if( !($table_data = filetomem($d_dir.'sql/'.__dbtype__.'/fud_'.$table.'.tbl')) ) {
		restore_core_inc();
		exit("Failed to read table defenition for '$table' at: ".$d_dir.'sql/'.__dbtype__.'/fud_'.$table.".tbl<br>\n");
	}
	
	$table_data = substr($table_data, strpos($table_data, 'CREATE TABLE'));
	$table_data = preg_replace("!#(.*?)\n!", "", $table_data);
	$table_data = preg_replace("!\s+!", " ", $table_data);
	
	return explode(';', $table_data);
}

function pgsql_drop_sequences($table)
{
	$r = q("SELECT c.relname FROM pg_class c LEFT JOIN pg_user u ON c.relowner = u.usesysid WHERE c.relkind IN ('S','') AND c.relname ~ '^".$table."'");
	while( list($seq_name) = db_rowarr($r) ) 
		q("DROP SEQUENCE ".$seq_name);
	qf($r);
}

function pgsql_drop_indexes($table)
{
	$r = q("SELECT c.relname FROM pg_class c LEFT JOIN pg_user u ON c.relowner = u.usesysid WHERE c.relkind IN ('i','') AND c.relname ~ '^".$table."'");
	while( list($seq_name) = db_rowarr($r) ) 
		q("DROP INDEX ".$seq_name);
	qf($r);
}

function pgsql_drop_if_exists($table)
{
	if( q_singleval("SELECT c.relname FROM pg_class c LEFT JOIN pg_user u ON c.relowner = u.usesysid WHERE c.relkind IN ('r','') AND c.relname='".$table."'") ) 
		q("DROP TABLE ".$table);
}

function pgsql_rebuild_table($table)
{
	if( isset($GLOBALS['REBUILT_TABLES'][$table]) ) 
		return;
	else
		$GLOBALS['REBUILT_TABLES'][$table] = 1;	

	pgsql_drop_sequences($GLOBALS['DBHOST_TBL_PREFIX'].$table);
	pgsql_drop_indexes($GLOBALS['DBHOST_TBL_PREFIX'].$table);

	$tmp_prefix = $GLOBALS['DBHOST_TBL_PREFIX'].'tmp_';

	$tmp = get_table_defenition($table);
	if( count($tmp) ) {
		foreach($tmp as $ent ) { 
			if( trim($ent) ) {
				if( strpos($ent, 'CREATE TABLE') !== false ) {
					pgsql_drop_sequences($tmp_prefix.$table);
					pgsql_drop_if_exists($tmp_prefix.$table);

					q("ALTER TABLE ".$GLOBALS['DBHOST_TBL_PREFIX'].$table." RENAME TO ".$tmp_prefix.$table);

					q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent)); 
					
					$fl_o = get_field_list($tmp_prefix.$table);
					$fl = '';
					while( list($fl_n) = db_rowarr($fl_o) ) $fl .= $fl_n.',';
					qf($fl_o);
					$fl = substr($fl, 0, -1);
					
					q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX'].$table." (".$fl.") SELECT ".$fl." FROM ".$tmp_prefix.$table);
					
					if( strstr($end, 'SERIAL PRIMARY KEY') ) {
						if( !($m = q_singleval("SELECT MAX(id) FROM ".$GLOBALS['DBHOST_TBL_PREFIX'].$table)) ) 
							$m = 0;
					
						q('SELECT setval('.$GLOBALS['DBHOST_TBL_PREFIX'].$table.'_id_seq, '.$m);
					}
					
					q("DROP TABLE ".$tmp_prefix.$table);
				}
				else {
					q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent));
				}	
			}
		}
	}
}

function parse_todo_entry($line)
{
	$tmp = get_fud_table_list();
	$table_list = array();
	foreach( $tmp as $tmp_val ) $table_list[$tmp_val] = $tmp_val;
	
	$tmp = explode('::', trim($line));
	if( !($c = count($tmp)) ) return;	
	
	$table_name = $GLOBALS['DBHOST_TBL_PREFIX'].$tmp[0];
	$action = $tmp[1];
	
	switch( $action )
	{
		case 'ADD_TABLE': // $tmp[2] -> table defenition
			if( isset($table_list[$table_name]) ) break;
			
			q("CREATE TABLE ".$table_name." ".$tmp[2]);
			$table_list[$table_name] = $table_name;
			break;
		case 'ADD_TABLE_DB':
			if( isset($table_list[$table_name]) ) break;
			
			$tmp = get_table_defenition($tmp[0]);
				
			if( count($tmp) ) {
				foreach($tmp as $ent ) { 
					if( trim($ent) ) 
						q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ent)); 
				}
			} else {
				echo "bad table defenition<br>\n";
			}
			break;
		case 'DROP_TABLE':
			if( !isset($table_list[$table_name]) ) break;
			
			q("DROP TABLE ".$table_name);
			if( __dbtype__ == 'pgsql' ) pgsql_drop_sequences($table_name);
			unset($table_list[$table_name]);
			break;
		case 'ADD_COLUMN': 
			/*
			 *	$tmp[2] -> column_name
			 *	$tmp[3] -> column_type
			 *	$tmp[4] -> column_value
			 *	$tmp[5] -> is_null
			 *	$tmp[6] -> default_value
			 *	$tmp[7] -> auto_increment
			 *	$tmp[8] -> trigger queries, separated by ;
			 */
			if( __dbtype__ == 'mysql' ) {
				if( q_singleval("show fields from ".$table_name." like '".$tmp[2]."'") ) break;
				$query = 'ALTER TABLE '.$table_name.' ADD '.$tmp[2].' '.$tmp[3].' '.$tmp[4];
				if( empty($tmp[5]) ) $query .= ' NOT NULL';
				if( isset($tmp[6]) && strlen($tmp[6]) ) $query .= ' DEFAULT '.$tmp[6];
				if( isset($tmp[7]) && strlen($tmp[7]) ) $query .= ' AUTO_INCREMENT';
				
				q($query);
			}
			else if( __dbtype__ == 'pgsql' ) {
				if( q_singleval("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$table_name."' AND a.attnum>0 AND a.attrelid=c.oid AND a.attname='".$tmp[2]."'") ) break;
				pgsql_rebuild_table($tmp[0]);				
			}
			
			if( isset($tmp[8]) ) {
				$tmp = explode(';', $tmp[8]);
					
				foreach($tmp as $qy) {
					if( trim($qy) ) {
						q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $qy));
					}	
				}	
			}
			
			break;	
		case 'DROP_COLUMN': // $tmp[2] -> column_name
			if( __dbtype__ == 'mysql' && !q_singleval("show fields from ".$table_name." like '".$tmp[2]."'") ) break;
			if( __dbtype__ == 'pgsql' && !q_singleval("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '".$table_name."' AND a.attnum>0 AND a.attrelid=c.oid AND a.attname='".$tmp[2]."'") ) break;
			
			q("ALTER TABLE ".$table_name." DROP ".$tmp[2]);	
			break;
		case 'ALTER_COLUMN':
			/*
			 *	$tmp[2] -> old_column_name
			 *	$tmp[3] -> column_name 
			 *	$tmp[4] -> column_type
			 *	$tmp[5] -> column_value
			 *	$tmp[6] -> is_null
			 *	$tmp[7] -> default_value
			 *	$tmp[8] -> auto_increment
			 */
			if( __dbtype__ == 'mysql' ) {
				if( $tmp[2] != $tmp[3] && !q_singleval("show fields from ".$table_name." like '".$tmp[2]."'") ) break;
				$query = 'ALTER TABLE '.$table_name.' CHANGE '.$tmp[2].' '.$tmp[3].' '.$tmp[4].' '.$tmp[5];
				if( empty($tmp[6]) ) $query .= ' NOT NULL';
				if( isset($tmp[7]) && strlen($tmp[7]) ) $query .= ' DEFAULT '.$tmp[7];
				if( isset($tmp[8]) && strlen($tmp[8]) ) $query .= ' AUTO_INCREMENT';
				
				q($query);
			}
			else if( __dbtype__ == 'pgsql' ) {
				pgsql_rebuild_table($tmp[0]);
			}
			break;
		case 'ADD_INDEX':
			/*
			 *	$tmp[2] -> index_type
			 *	$tmp[3] -> index_defenition
			 *	$tmp[4] -> index_name (pgsql only)
			 */
			if( __dbtype__ == 'mysql' ) {
			 	if( match_mysql_index($table_name, $tmp[3], $tmp[2]) ) break;
			 	q("ALTER TABLE ".$table_name." ADD ".$tmp[2]."(".$tmp[3].")");
			}
			else if( __dbtype__ == 'pgsql' ) {
				$index_name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[4]);
			
				if( q_singleval("SELECT * FROM pg_stat_user_indexes WHERE relname='".$table_name."' AND indexrelname='".$index_name."'") ) break;
				if( $tmp[2] == 'INDEX' ) 
					q("CREATE INDEX ".$index_name." ON ".$table_name." (".$tmp[3].")");
				else
					q("CREATE UNIQUE INDEX ".$index_name." ON ".$table_name." (".$tmp[3].")");	
			}
			break;
		case 'DROP_INDEX':
			/*
			 *	$tmp[2] -> index_type
			 *	$tmp[3] -> index_defenition
			 *	$tmp[4] -> index_name (pgsql only)
			 */
			if( __dbtype__ == 'mysql' ) {
				if( !($tmp[4]=match_mysql_index($table_name, $tmp[3], $tmp[2])) ) break;
			 	q("ALTER TABLE ".$table_name." DROP INDEX ".$tmp[4]);
			}
			else if( __dbtype__ == 'pgsql' ) {
				$index_name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[4]);
				if( !q_singleval("SELECT * FROM pg_stat_user_indexes WHERE relname='".$table_name."' AND indexrelname='".$index_name."'") ) break;
				q("DROP INDEX ".$index_name);
			}
			break;
		case 'QUERY':
			/*
			 *      $tmp[2] -> query
		  	 *      $tmp[3] -> version
		  	 */
			q(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tmp[2]));
			break;	
	}	
}

function restore_core_inc()
{
	if( $GLOBALS['core_backup'] ) copy($GLOBALS['core_backup'], $GLOBALS['DATA_DIR'].'include/core.inc');
}

function determine_db_type()
{
	if( @file_exists($GLOBALS["INCLUDE"].'theme/default/db.inc') && 
		preg_match("!define\('__dbtype__', 'pgsql'\);!", filetomem($GLOBALS["INCLUDE"].'theme/default/db.inc')) 
	) 
		return 'pgsql';	

	return 'mysql';	
}

function init_sql_func()
{
	if( __dbtype__ == 'mysql' ) {
		mysql_connect($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or die(mysql_errno().': '.mysql_error().restore_core_inc());
		mysql_select_db($GLOBALS['DBHOST_DBNAME']) or die(mysql_errno().': '.mysql_error().restore_core_inc());
		
		function q($query) 
		{
			$r = mysql_query($query) or die(mysql_errno().': '.mysql_error().restore_core_inc()."<br>\nOriginal Query: ".htmlspecialchars($query));
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
			$r = q($query);
			if( !mysql_num_rows($r) ) {
				qf($r);
				return;
			}
			list($val) = db_rowarr($r);
			qf($r);
			
			return $val;
		}
		
		function get_fud_table_list()
		{
			$ret = array();
			$r = q("show tables LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
			while( list($name) = db_rowarr($r) ) {
				$ret[] = $name;
			}	
			qf($r);
	
			return $ret;	
		}
		
		function check_sql_perms()
		{
			mysql_query("DROP TABLE IF EXISTS upgrade_test_table");
			if( !mysql_query("CREATE TABLE upgrade_test_table (test_val INT)") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's MySQL account does not have permissions to create new MySQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}	
			if( !mysql_query("ALTER TABLE upgrade_test_table ADD test_val2 INT") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's MYSQL account does not have permissions to run ALTER queries on existing MySQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}	
			if( !mysql_query("DROP TABLE upgrade_test_table") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's MYSQL account does not have permissions to run DROP TABLE queries on existing MySQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}
		}
		
		function remove_dup_indexes($tbl_name)
		{
		        $idx_chk = $idx_chk2 = array();
        
			$r = q("SHOW INDEX FROM ".$tbl_name);
		        while( $obj = db_rowobj($r) ) {
		        	$idx_chk[$obj->Key_name.' '.$tbl_name] .= $obj->Column_name;
			}
		        qf($r);
                                                
		        foreach( $idx_chk as $k => $v ) {
				if( isset($idx_chk2[$v]) ) {
					list($idx_n, $tbl_n) = explode(' ', $k);
					q("ALTER TABLE ".$tbl_n." DROP INDEX ".$idx_n);
				} else {
        	        	$idx_chk2[$v] = $k;
				}
			}
		}
	} 
	else if ( __dbtype__ == 'pgsql' ) {
		$connect_str = '';
		if ( $GLOBALS['DBHOST'] ) 	$connect_str .= 'host='.$GLOBALS['DBHOST'].' ';
		if ( $GLOBALS['DBHOST_PORT'] )	$connect_str .= 'port='.$GLOBALS['DBHOST_PORT'].' ';
		if ( $GLOBALS['DBHOST_USER'] )	$connect_str .= 'user='.$GLOBALS['DBHOST_USER'].' ';
		if ( $GLOBALS['DBHOST_PASSWORD'] ) $connect_str .= 'password='.$GLOBALS['DBHOST_PASSWORD'].' ';
		if ( $GLOBALS['DBHOST_TTY'] )	$connect_str .= 'tty='.$GLOBALS['DBHOST_TTY'].' ';
		if ( $GLOBALS['DBHOST_DBNAME'] ) $connect_str .= 'dbname='.$GLOBALS['DBHOST_DBNAME'].' ';
		$connect_str = substr($connect_str, 0 ,-1);
		
		$GLOBALS['__DB_INC__']['SQL_LINK'] = pg_pconnect($connect_str) or die (pg_last_error($GLOBALS['__DB_INC__']['SQL_LINK']).restore_core_inc());
		
		function q($query)
		{
			$result = pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], $query) or die (restore_core_inc().pg_result_error($result)."<br>\nOriginal Query: ".$query);
			return $result;
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
			$r = q($query);
			if( !pg_num_rows($r) ) {
				qf($r);
				return;
			}
			list($val) = db_rowarr($r);
			qf($r);
			
			return $val;
		}
		
		function get_fud_table_list()
		{
			$ret = array();

			$r = q("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
			while( list($name) = db_rowarr($r) ) 
				$ret[] = $name;
			qf($r);
	
			return $ret;	
		}
		
		function check_sql_perms()
		{
			@pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], "DROP TABLE upgrade_test_table");
			if( !pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], "CREATE TABLE upgrade_test_table (test_val INT)") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's PostgreSQL account does not have permissions to create new PostgreSQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}	
			if( !pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], "ALTER TABLE upgrade_test_table ADD test_val2 INT") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's MYSQL account does not have permissions to run ALTER queries on existing PostgreSQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}	
			if( !pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], "DROP TABLE upgrade_test_table") ) {
				restore_core_inc();
				exit("FATAL ERROR: your forum's MYSQL account does not have permissions to run DROP TABLE queries on existing PostgreSQL tables<br>\nEnable this functionality and restart the script.<br>\n");
			}
		}
		
		function get_field_list($tbl)
		{
			return q("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '$tbl' AND a.attnum > 0 AND a.attrelid = c.oid ORDER BY a.attnum");
		}
	}
	else { 
		exit("NO VALID DATABASE TYPE SPECIFIED");
	}	
}

function zlib_check()
{
	if( !extension_loaded('zlib') ) {
		/* Handle situations where using dl() would result in E_ERROR, causing script termination */
		if( ini_get('enable_dl') && !ini_get('safe_mode') ) {
			if( !preg_match('!win!i', PHP_OS) ) {
				dl("zlib.so");
			} else {
				dl("php_zlib.dll");
			}
		}	
	}
	
	if( !extension_loaded('zlib') ) {
		exit("zlib extension required to decompress the archive is not loaded.<br>\nAttempts to load the extension, failed.<br>\n
		Please recompile your PHP with zlib support or load the zlib extension");
	}
}

	if( !ini_get("track_errors") ) ini_set("track_errors", 1);
	if( !ini_get("display_errors") ) ini_set("display_errors", 1);
	
	zlib_check();
	
	error_reporting(E_ALL & ~E_NOTICE);
	ini_set("memory_limit", "20M");
	ignore_user_abort(true);
	@set_time_limit(6000);
	
	if( !isset($HTTP_SERVER_VARS['PATH_TRANSLATED']) && isset($HTTP_SERVER_VARS['SCRIPT_FILENAME']) ) 
		$HTTP_SERVER_VARS['PATH_TRANSLATED'] = $GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED'] = $HTTP_SERVER_VARS['SCRIPT_FILENAME'];
	
	/* Safe Mode sucks, now the user is instructed to jump through hoops */
	$st = stat('index.php');
	$uid = isset($st['uid']) ? $st['uid'] : $st[4];
	if( ini_get("safe_mode") && getmyuid() != $uid && $GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED'][0] == '/' ) {
		if( __FILE__ == 'upgrade_safe.php' ) {
			if( @copy(__FILE__, 'upgrade_safe.php') ) {
				header("Location: upgrade_safe.php");
				exit;
			}
		}
		
		echo '<font color="#FF0000">SAFE_MODE is enabled!<br>Use the file manager to upload this script into the WWW_SERVER_ROOT directory</font>';
		exit;
	}

	echo '<html><body bgcolor="#FFFFFF">';

	if( !@file_exists('GLOBALS.php') ) {
		echo '<font color="#FF0000">Cannot open GLOBALS.php, this does not appear to be a forum directory. You need to upload the file in to the forum\'s WWW_SERVER_ROOT directory</font>';
		exit;	
	}
	
	include_once "GLOBALS.php";
	
	/* Upgrade Marker Check */
	
	if( @file_exists($ERROR_PATH.'UPGRADE_STATUS') ) {
		$marker = filetomem($ERROR_PATH.'UPGRADE_STATUS');
		if( $marker && $marker >= $__UPGRADE_SCRIPT_VERSION ) {
			echo '<font color="#FF0000">THIS UPGRADE SCRIPT HAS ALREADY BEEN RUN, IF YOU WISH TO RUN IT AGAIN USE THE FILE MANAGER TO REMOVE THE "'.$ERROR_PATH.'UPGRADE_STATUS" FILE.</font>';
			exit;	
		}
	}	
	
	$data = filetomem($GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED']);
	
	$CUR_FORUM_VERSION = versiontoint($FORUM_VERSION);
	
	$GLOBALS_FILE = read_global_config();
	change_global_val('FORUM_ENABLED', 'N', $GLOBALS_FILE);
	write_global_config($GLOBALS_FILE);
	
	$GLOBALS_FILE_B = $GLOBALS_FILE;
	
	/* database variable conversion */
	if( !isset($DBHOST_TBL_PREFIX) ) {
		$DBHOST_TBL_PREFIX 	= $MYSQL_TBL_PREFIX;
		$DBHOST 		= $MYSQL_SERVER;
		$DBHOST_USER 		= $MYSQL_LOGIN;
		$DBHOST_PASSWORD 	= $MYSQL_PASSWORD;
		$DBHOST_DBNAME 		= $MYSQL_DB;
		$DBHOST_PERSIST 	= $DBHOST_PERSIST;
	}	
	
	/* Determine Database Type */
	define('__dbtype__', determine_db_type());
	
	/* include appropriate database functions */
	init_sql_func();
	
	/* check that we can do all needed database operations */
	check_sql_perms();
	
	/* Upgrade Files */
	
	echo "Beginning the file upgrade process<br>\n";
	$d_dir = isset($GLOBALS['DATA_DIR']) ? $GLOBALS['DATA_DIR'] : realpath($GLOBALS['INCLUDE'].'../').'/';
	
	upgrade_decompress_archive($d_dir , $WWW_ROOT_DISK, $data);	
	echo "File Upgrade Complete<br>\n";
	echo '<font color="#ff0000">Any changed files were backed up to: "'.$GLOBALS['ERROR_PATH'].'.backup/"</font><br>';
	flush();
	
	/* Update SQL */
	
	$s = strpos($data, "042252166145_\\SQL_START_HERE") + strlen("042252166145_\\SQL_START_HERE");
	$e = strpos($data, "042252166145_\\SQL_END_HERE", $s);
	$sql_data = substr($data, $s, ($e-$s));
	
	echo "\n<br>Beginning SQL Upgrades<br>\n";
	$qry = explode("\n", $sql_data);
	foreach($qry as $v) parse_todo_entry($v);
	
	/* Check for dublicate indexes and remove them if there are any */
	if( __dbtype__ == 'mysql' ) {
		$tbl_list = get_fud_table_list();
		foreach($tbl_list as $v) remove_dup_indexes($v);
	}	
	unset($sql_data, $qry, $q, $tbl_list, $v);
	echo "SQL Upgrade Complete<br>\n";
	flush();

	/* Perform various upgrades, for old version, which could only be using MySQL */
	if( __dbtype__ == 'mysql' ) {
	
		/* Move homepage & bio from flat files to DB */
		if ( !q_singleval("SHOW FIELDS FROM ".$DBHOST_TBL_PREFIX."users LIKE 'home_page'") ) {
			q("ALTER TABLE ".$DBHOST_TBL_PREFIX."users ADD home_page CHAR(255), ADD bio TEXT");
			$curdir = getcwd();
			chdir($GLOBALS['USER_SETTINGS_PATH']);
			$dir = opendir('.');
			readdir($dir); readdir($dir);
			while( $file = readdir($dir) ) {
				if( substr($file, -4) != '.fud' ) continue;
				list($www, $bio) = read_ext_set($file);
				$id = substr($file, 0, strpos($file, '.'));
				q("UPDATE ".$DBHOST_TBL_PREFIX."users SET home_page='".addslashes($www)."', bio='".addslashes($bio)."' WHERE id=$id");
			}
			closedir($dir);
			chdir($curdir);
		}

		/* Add VISIBLE permission */
		if ( !q_singleval("SHOW FIELDS FROM ".$DBHOST_TBL_PREFIX."groups LIKE 'p_VISIBLE'") ) {
			echo "adding visble permisson<Br>";
			q("ALTER TABLE ".$DBHOST_TBL_PREFIX."groups ADD p_VISIBLE ENUM('I', 'Y', 'N') NOT NULL DEFAULT 'N'");
			q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET p_VISIBLE='Y' WHERE p_VIEW='Y'");
			q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET up_VISIBLE='Y' WHERE up_VIEW='Y'");
		
			$r = q("SHOW FIELDS FROM ".$DBHOST_TBL_PREFIX."groups");
			while ( $obj = db_rowobj($r) ) {
				if ( substr($obj->Field, 0, 2) != 'p_' ) continue; 
				if ( $obj->Field == 'p_VISIBLE' ) break;
		
				$r2 = q("SELECT $obj->Field, id FROM ".$DBHOST_TBL_PREFIX."groups");
				while ( $obj2 = db_rowobj($r2) ) {
					$vals[$obj2->id] = $obj2->{$obj->Field};
				}
		
				q("ALTER TABLE ".$DBHOST_TBL_PREFIX."groups DROP $obj->Field");
				q("ALTER TABLE ".$DBHOST_TBL_PREFIX."groups ADD $obj->Field ENUM('I', 'Y', 'N') NOT NULL DEFAULT 'N'");
			
				foreach($vals as $k => $v) q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET $obj->Field='$v' WHERE id=$k");
				qf($r2);
			}
			qf($r);

			$r = q("SHOW FIELDS FROM ".$DBHOST_TBL_PREFIX."group_members");
			while ( $obj = db_rowobj($r) ) {
				if ( substr($obj->Field, 0, 3) != 'up_' ) continue; 
				if ( $obj->Field == 'up_VISIBLE' ) break;
		
				$r2 = q("SELECT $obj->Field, id FROM ".$DBHOST_TBL_PREFIX."group_members");
				while ( $obj2 = db_rowobj($r2) ) {
					$vals[$obj2->id] = $obj2->{$obj->Field};
				}
			
				q("ALTER TABLE ".$DBHOST_TBL_PREFIX."group_members DROP $obj->Field");
				q("ALTER TABLE ".$DBHOST_TBL_PREFIX."group_members ADD $obj->Field ENUM('Y', 'N') NOT NULL DEFAULT 'N'");

				foreach($vals as $k => $v) q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET $obj->Field='$v' WHERE id=$k");

				qf($r2);
			}
			qf($r);
		}

		/* convert the replacement system into the new format */
		if ( $CUR_FORUM_VERSION < versiontoint('2.0') ) {
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
		if( $obj->Type == 'date' ) {
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
	
	/* Convert old url based avatars to new format */
	$r = q("SELECT id,avatar_loc FROM ".$DBHOST_TBL_PREFIX."users WHERE avatar_loc!=''");
	while( $obj = db_rowobj($r) ) {
		if( ($avt_path = fetch_img($obj->avatar_loc)) )
			copy($avt_path, $GLOBALS['WWW_ROOT_DISK'].'images/custom_avatars/'.$obj->id);
		else
			q("UPDATE ".$DBHOST_TBL_PREFIX."users SET avatar_approved='NO' WHERE id=".$obj->id);
	}
	qf($r);
	
	q("UPDATE ".$DBHOST_TBL_PREFIX."users SET avatar_loc='' WHERE avatar_loc!=''");
	
	/* Add data into pdest field of pmsg table */
	if ( $CUR_FORUM_VERSION < versiontoint('2.2.1') ) {
		$r = q("SELECT to_list,id FROM ".$DBHOST_TBL_PREFIX."pmsg WHERE folder_id='SENT' AND duser_id=ouser_id");
		while( list($l,$id) = db_rowarr($r) ) {
			if( ($p=strpos($l, ';')) ) 
				$uname = substr($l, 0, $p);
			else
				$uname = $l;
		
			if( !trim($uname) ) continue;		
			if( !($uid = q_singleval("select id from ".$DBHOST_TBL_PREFIX."users where login='".addslashes($uname)."'")) ) continue;
		
			q("UPDATE ".$DBHOST_TBL_PREFIX."pmsg SET pdest=".$uid." WHERE id=".$id);
		}
		qf($r);
	}
	if ( !q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE t_default='Y'") ) {
		$pspell_lang = (@file_exists($d_dir.'/thm/default/i18n/'.$GLOBALS['LANGUAGE'].'/pspell_lang')) ? trim(filetomem($d_dir.'/thm/default/i18n/'.$GLOBALS['LANGUAGE'].'/pspell_lang')) : '';
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."themes(id, name, theme, lang, locale, enabled, t_default, pspell_lang)
			VALUES(1, 'default', 'default', '".$GLOBALS['LANGUAGE']."', '".$GLOBALS['LOCALE']."', 'Y', 'Y', '$pspell_lang')");
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET theme=1");
	}
	
	/* encode user alias according to new format */
	if( $CUR_FORUM_VERSION < versiontoint('2.2.4RC3') ) {
		$r = q("SELECT id,alias FROM ".$DBHOST_TBL_PREFIX."users");
		while( list($id,$alias) = db_rowarr($r) ) {
			if( isset($alias[$GLOBALS['MAX_LOGIN_SHOW']+1]) ) $alias = substr($alias, 0, $GLOBALS['MAX_LOGIN_SHOW']);
			q("UPDATE ".$DBHOST_TBL_PREFIX."users SET alias='".addslashes(htmlspecialchars($alias))."' WHERE id=".$id);
		}
		qf($r);
	}
	
	/* store file attachment sizes inside db */
	if( $CUR_FORUM_VERSION < versiontoint('2.3.2RC1') ) {
		$r = q("SELECT id,location FROM ".$DBHOST_TBL_PREFIX."attach");
		while( list($id,$loc) = db_rowarr($r) ) {
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET fsize=".intzero(filesize($loc))." WHERE id=".$id);
		}
		qf($r);
	}
	
	/* Add any needed GLOBAL OPTIONS */
	
	echo "\n<br>Adding GLOBAL Variables<br>\n";
	$s = strpos($data, "116304110503_\\GLOBAL_VARS_START_HERE") + strlen("116304110503_\\GLOBAL_VARS_START_HERE");
	$e = strpos($data, "116304110503_\\GLOBAL_VARS_END_HERE", $s);
	$gvar_data = substr($data, $s, ($e-$s));
	$gvars = explode("\n", $gvar_data);
	
	if ( !isset($GLOBALS['DATA_DIR']) ) $gvars[] = '$DATA_DIR	= "'.realpath($GLOBALS['TEMPLATE_DIR'].'../').'/";';
	
	foreach($gvars as $v) {
		if( !($v = ltrim($v)) ) continue;
		if( ($e = strpos($v, "\t")) === false ) $e = strpos($v, " ");
		
		$varname = trim(substr($v, 1, ($e-1)));
		
		if( isset(${$varname}) ) continue;
		
		$GLOBALS_FILE = substr_replace($GLOBALS_FILE, $v."\n\t", strpos($GLOBALS_FILE, '$ADMIN_EMAIL'), 0);
	}
	
	/* convert the name of the database related global variables */
	if( isset($MYSQL_SERVER) ) {
		$GLOBALS_FILE = str_replace('MYSQL_SERVER', 'DBHOST', $GLOBALS_FILE);
		$GLOBALS_FILE = str_replace('MYSQL_LOGIN', 'DBHOST_USER', $GLOBALS_FILE);
		$GLOBALS_FILE = str_replace('MYSQL_PASSWORD', 'DBHOST_PASSWORD', $GLOBALS_FILE);
		$GLOBALS_FILE = str_replace('MYSQL_DB', 'DBHOST_DBNAME', $GLOBALS_FILE);
		$GLOBALS_FILE = str_replace('MYSQL_PERSIST', 'DBHOST_PERSIST', $GLOBALS_FILE);
		$GLOBALS_FILE = str_replace('MYSQL_TBL_PREFIX', 'DBHOST_TBL_PREFIX', $GLOBALS_FILE);
	}
	
	if( $GLOBALS_FILE_B != $GLOBALS_FILE ) {
		$fp = fopen($GLOBALS['__GLOBALS.INC__'], 'wb');
			fwrite($fp, $GLOBALS_FILE);
		fclose($fp);
	}
	unset($gvars, $gvar_data, $GLOBALS_FILE_B, $GLOBALS_FILE);
	
	echo "Finished Adding GLOBAL Variables<br>\n";
	flush();
	
	if ( $CUR_FORUM_VERSION < versiontoint('2.1') ) {
		$oldcwd = getcwd();
		chdir($GLOBALS['WWW_ROOT_DISK']);
	
		$dp = opendir('.');
		readdir($dp); readdir($dp);
		$arr = array('index.php'=>1, 'GLOBALS.php'=>1, 'php.php'=>1, basename($HTTP_SERVER_VARS['PATH_TRANSLATED'])=>1);
		while ( $de = readdir($dp) ) 
		{
			if ( substr($de, -4) != '.php' || isset($arr[$de]) || !@is_file($de) ) continue;
			unlink($de);
		}
		closedir($dp);
		chdir($oldcwd);
		$u = umask(0);
		if ( !@is_dir($GLOBALS['ERROR_PATH'].'.backup') ) mkdir($GLOBALS['ERROR_PATH'].'.backup', '0700');
		umask($u);
		$src = substr($GLOBALS['TEMPLATE_DIR'], 0, -1);
		$dst = $GLOBALS['ERROR_PATH'].'.backup/template_'.time();
		if ( !rename($src, $dst) )
			echo "unable to rename (<b>$src</b>) to (<b>$dst</b>)<br>\n";
	}
	/* Compile The Forum */
	
	fud_use('compiler.inc', true);

	$r = q("SELECT * FROM ".$DBHOST_TBL_PREFIX."themes WHERE enabled='Y'");
	if ( !isset($GLOBALS['DATA_DIR']) ) $GLOBALS['DATA_DIR'] = $d_dir;
	if ( substr($GLOBALS['DATA_DIR'], -1) != '/' ) $GLOBALS['DATA_DIR'] .= '/';
	echo "DATADIR: ".$GLOBALS['DATA_DIR']."<br>\n";
	
	/* if need be remove core.inc.t */
	if( @file_exists($GLOBALS['DATA_DIR'].'src/core.inc.t') ) unlink($GLOBALS['DATA_DIR'].'src/core.inc.t');
	
	while ( $obj = db_rowobj($r) ) {
		// See if custom themes need to have their files updated
		if( $obj->theme != 'default' ) syncronize_theme($obj->theme);
		
		/* remove core.tmpl if need be */
		if( @file_exists($GLOBALS['DATA_DIR'].'thm/'.$obj->theme.'/tmpl/core.tmpl') ) 
			unlink($GLOBALS['DATA_DIR'].'thm/'.$obj->theme.'/tmpl/core.tmpl');
		
		echo "Compiling $obj->name<br>\n";
		compile_all($obj->theme, $obj->lang, $obj->name);
	}
	qf($r);
	
	/* Insert update script marker */
	$fp = fopen($ERROR_PATH.'UPGRADE_STATUS', 'wb');
		fwrite($fp, $__UPGRADE_SCRIPT_VERSION);
	fclose($fp);
	
	echo '<br>Executing Consistency Checker (if the popup with the consistency checker failed to appear you <a href="javascript://" onClick="javascript: window.open(\'adm/consist.php?enable_forum=1\');">MUST click here</a><br>';
	echo "
		<script>
			window.open('adm/consist.php?enable_forum=1');
		</script>";
		
	if( basename($HTTP_SERVER_VARS['SCRIPT_FILENAME']) == 'upgrade_safe.php' ) {
		unlink('upgrade_safe.php');
		$HTTP_SERVER_VARS['PATH_TRANSLATED'] = realpath('upgrade.php');
	}
		
	echo '<font color="red" size="4">PLEASE REMOVE THIS FILE('.$HTTP_SERVER_VARS['PATH_TRANSLATED'].') UPON COMPLETION OF THE UPGRADE PROCESS.<br>THIS IS IMPERATIVE, OTHERWISE ANYONE COULD RUN THIS SCRIPT!</font>';
?>
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
action_log::ADD_COLUMN::a_res::CHAR::100)::1
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
thread_view::ALTER_COLUMN::pos::pos::INT::UNSIGNED::::::AUTO_INCREMENT
ses::ADD_COLUMN::forum_id::INT::UNSIGNED::::0
mlist::ADD_TABLE_DB
nntp::ADD_TABLE_DB
attach::ADD_COLUMN::fsize::INT::::::0
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
$TREE_THREADS_PER_PAGE	= "15";		/* int */
$TREE_THREADS_MAX_DEPTH	= "15";		/* int */
$TREE_THREADS_MAX_SUBJ_LEN	= "75";	/* int */
116304110503_\GLOBAL_VARS_END_HERE

2105111608_\ARCH_START_HERE
