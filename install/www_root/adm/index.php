<?php
	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	header('Location: '.$WWW_ROOT.'adm/admglobal.php?'.__adm_rsidl);
?>