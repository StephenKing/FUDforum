<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: smiley.inc.t,v 1.19 2004/11/29 16:05:12 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

$GLOBALS['__SML_CHR_CHK__'] = array("\n"=>1, "\r"=>1, "\t"=>1, " "=>1, "]"=>1, "["=>1, "<"=>1, ">"=>1, "'"=>1, '"'=>1, "("=>1, ")"=>1, "."=>1, ","=>1, "!"=>1, "?"=>1);

function smiley_to_post($text)
{
	$text_l = strtolower($text);
	include $GLOBALS['FORUM_SETTINGS_PATH'].'sp_cache';

	foreach ($SML_REPL as $k => $v) {
		$a = 0;
		$len = strlen($k);
		while (($a = strpos($text_l, $k, $a)) !== false) {
			if ((!$a || isset($GLOBALS['__SML_CHR_CHK__'][$text_l[$a - 1]])) && ((@$ch = $text_l[$a + $len]) == "" || isset($GLOBALS['__SML_CHR_CHK__'][$ch]))) {
				$text_l = substr_replace($text_l, $v, $a, $len);
				$text = substr_replace($text, $v, $a, $len);
				$a += strlen($v) - $len;
			} else {
				$a += $len;
			}
		}
	}

	return $text;
}

function post_to_smiley($text)
{
	/* include once since draw_post_smiley_cntrl() may use it too */
	include_once $GLOBALS['FORUM_SETTINGS_PATH'].'ps_cache';
	if (isset($PS_SRC)) {
		$GLOBALS['PS_SRC'] = $PS_SRC;
		$GLOBALS['PS_DST'] = $PS_DST;
	} else {
		$PS_SRC = $GLOBALS['PS_SRC'];
		$PS_DST = $GLOBALS['PS_DST'];
	}

	/* check for emoticons */
	foreach ($PS_SRC as $k => $v) {
		if (strpos($text, $v) === false) {
			unset($PS_SRC[$k], $PS_DST[$k]);
		}
	}

	return $PS_SRC ? str_replace($PS_SRC, $PS_DST, $text) : $text;
}
?>