<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admsmileysel.php,v 1.2 2003/04/28 15:24:41 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	require('GLOBALS.php');
	fud_use('adm.inc', true);
	
	echo '<html><body bgcolor="#ffffff">';

	if ($dp = opendir($GLOBALS['WWW_ROOT_DISK'] . 'images/smiley_icons')) {
		readdir($dp); readdir($dp);
		$icons_per_row = 6;
		$col = $i = 0;
		echo '<table border=0 cellspacing=1 cellpadding=2><tr>';
		while ($de = readdir($dp)) {
			$ext = strtolower(substr($de, -4));
			if ($ext != '.gif' && $ext != '.jpg' && $ext != '.png' && $ext != 'jpeg') {
				continue;
			}
			if (!($col++%$icons_per_row)) {
				echo '</tr><tr>';
			}
			$bgcolor = (!($i++%2)) ? ' bgcolor="#f4f4f4"' : '';

			echo '<td '.$bgcolor.' nowrap valign=center align=center><a href="javascript: window.opener.document.prev_icon.src=\'../images/smiley_icons/'.$de.'\'; window.opener.document.frm_sml.sml_img.value=\''.$de.'\'; window.close();"><img src="../images/smiley_icons/'.$de.'" border=0><br><font size=-2>'.$de.'</font></a></td>';
		}
		closedir($dp);
		echo '</tr></table>';
	}
	if (!$i) {
		echo 'There are no smilies';	
	}
	echo '</body></html>'; 
?>