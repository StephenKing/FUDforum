<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admavatarsel.php,v 1.11 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	echo '<html><body bgcolor="#ffffff">';

	$path = $WWW_ROOT_DISK . 'images//avatars/';

	/* here we draw the avatar control */
	if (($files = glob($path. '/{*.jpg,*.gif,*.png,*.jpeg}', GLOB_BRACE|GLOB_NOSORT))) {
		$col = $i = 0;
		$icons_per_row = 7;
		echo '<table border=0 cellspacing=1 cellpadding=2><tr>';
		foreach ($files as $file) {
			$de = basename($file);
			if (!($col++%$icons_per_row)) {
				echo '</tr><tr>';
			}
			$bgcolor = (!($i++%2)) ? ' bgcolor="#f4f4f4"' : '';

			echo '<td '.$bgcolor.' nowrap valign=center align=center><a href="javascript: window.opener.document.prev_icon.src=\'../images/avatars/'.$de.'\'; window.opener.document.frm_avt.avt_img.value=\''.$de.'\'; window.close();"><img src="../images/avatars/'.$de.'" border=0><br><font size=-2>'.$de.'</font></a></td>';
		}
		echo '</tr></table>';
	} else {
		echo 'There are no built-in avatars';
	}

	echo '</body></html>';
?>