<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: error.php.t,v 1.16 2004/11/24 19:53:34 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	if (isset($_POST['ok'])) {
		check_return($usr->returnto);
	}
	$TITLE_EXTRA = ': {TEMPLATE: error_title}';

/*{POST_HTML_PHP}*/

	if (isset($usr->data['er_msg'], $usr->data['err_t'])) {
		$error_message	= $usr->data['er_msg'];
		$error_title	= $usr->data['err_t'];
		ses_putvar((int)$usr->sid, null);
	} else {
		$error_message	= '{TEMPLATE: error_invalidurl}';
		$error_title	= '{TEMPLATE: error_error}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ERROR_PAGE}