<?php
/* --------- CONFIG OPTIONS START (required) ----------- */
$GLOBALS['PATH_TO_FUD_FORUM_GLOBALS_PHP'] = '';
// this value is usually $DATA_DIR/include/theme/default/db.inc, if this is the case
// leave the value empty.
$GLOBALS['PATH_TO_FUD_FORUM_DB_INC'] = '';
/* --------- CONFIG OPTIONS END (required) ----------- */

/* The following function will take the forum's user id and log the user into the forum
   On successful execution the return value will be the session id for the user.
   Upon failure the return value will be NULL, this can only happen if invalid user id is specified.
*/
function external_fud_login($user_id)
{
	if (($user_id = (int) $user_id) < 2) {
		return;
	} 

	/* load forum config */
	$data = file_get_contents($GLOBALS['PATH_TO_FUD_FORUM_GLOBALS_PHP']);
	eval(str_replace('<?php', '', substr_replace($data, '', strpos($data, 'require'))));

	/* db.inc needs certain vars inside the global scope to work, so we export them */
	foreach (array('FUD_OPT_1', 'DBHOST', 'DBHOST_USER', 'DBHOST_PASSWORD', 'DBHOST_DBNAME') as $v) {
		$GLOBALS[$v] = $$v;
	}

	if (!$GLOBALS['PATH_TO_FUD_FORUM_DB_INC']) {
		require_once $DATA_DIR . 'include/theme/default/db.inc';
	} else {
		require_once $GLOBALS['PATH_TO_FUD_FORUM_DB_INC'];
	}

	/* validate user */
	if (!q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE id=".$user_id)) {
		return;
	}

	/* create session */
	$sys_id = __ses_make_sysid(($FUD_OPT_2 & 256), ($FUD_OPT_3 & 16));
	$ses_id = md5($user_id . time() . getmypid());
	q("REPLACE INTO ".$DBHOST_TBL_PREFIX."ses (ses_id, time_sec, sys_id, user_id) VALUES ('".$ses_id."', ".time().", '".$sys_id."', ".$user_id.")");
	setcookie($COOKIE_NAME, $ses_id, time()+$COOKIE_TIMEOUT, $COOKIE_PATH, $COOKIE_DOMAIN);

	return $ses_id;
}

/* internal functions, do not modify */
function __ses_make_sysid($a, $b)
{
	if ($a) {
		return;
	}

	$keys = array('HTTP_USER_AGENT', 'SERVER_PROTOCOL', 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE');
	if ($b && strpos($_SERVER['HTTP_USER_AGENT'], 'AOL') === false) {
		$keys[] = 'HTTP_X_FORWARDED_FOR';
		$keys[] = 'REMOTE_ADDR';
	}
	$pfx = '';
	foreach ($keys as $v) {
		if (isset($_SERVER[$v])) {
			$pfx .= $_SERVER[$v];
		}
	}
	return md5($pfx);
}
?>