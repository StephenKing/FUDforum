<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admpdf.php,v 1.14 2004/11/29 16:05:40 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');

	$help_ar = read_help();

	if (isset($_POST['form_posted'])) {
		$NEW_FUD_OPT_2 = 0;

		foreach ($_POST as $k => $v) {
			if (!strncmp($k, 'CF_', 3)) {
				$k = substr($k, 3);
				if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
					$ch_list[$k] = is_numeric($v) ? (int) $v : $v;
				}
			} else if (!strncmp($k, 'FUD_OPT_2', 9)) {
				$NEW_FUD_OPT_2 |= (int) $v;
			}
		}

		if (($NEW_FUD_OPT_2 ^ $FUD_OPT_2) & (268435456|134217728)) {
			if (!($NEW_FUD_OPT_2 & 268435456)) {
				$FUD_OPT_2 &= ~268435456;
			} else {
				$FUD_OPT_2 |= 268435456;
			}
			if (!($NEW_FUD_OPT_2 & 134217728)) {
				$FUD_OPT_2 &= ~134217728;
			} else {
				$FUD_OPT_2 |= 134217728;
			}

			$ch_list['FUD_OPT_2'] = $FUD_OPT_2;
		}

		if (isset($ch_list)) {
			change_global_settings($ch_list);
			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$rdf_url = $WWW_ROOT . 'pdf.php';
?>
<h2>PDF Output Configuration</h2>
<form method="post" action="admpdf.php"><?php echo _hs; ?>
<table class="datatable solidtable">
<?php
	print_bit_field('PDF Output Enabled', 'PDF_ENABLED');
	print_bit_field('Complete Forum Output', 'PDF_ALLOW_FULL');

	$opts = "A3\nA4\nA5\nletter\nlegal";
	$names = "A3: 842 x 1190\nA4: 595 x 842\nA5: 421 x 595\nletter: 612 x 792\nlegal: 612 x 1008";

	$sel = create_select('CF_PDF_PAGE', $names, $opts, $PDF_PAGE);
	echo '<tr class="field"><td>Page Dimensions: <br><font size="-1">The sizes are in points, each point is 1/72 of an inch.</font></td><td valign="top">'.$sel.'</td></tr>';

	print_reg_field('Horizontal Margin', 'PDF_WMARGIN', 1);
	print_reg_field('Vertical Margin', 'PDF_HMARGIN', 1);
	print_reg_field('Maximum CPU Time', 'PDF_MAX_CPU', 1);
?>
<tr class="fieldaction"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Change Settings"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<br>
<table border=0 cellspacing=1 cellpadding=3>
<tr><th><b>Quick PDF Tutorial</b></th></tr>
<tr class="tutor"><td>
If enabled, this feature will allow forum visitors to generate PDF files based on the forum data for easy printing and other uses.<br />
This facility supports 3 data retrieval modes, messages, topics & entire forums.<br />
<b>Examples:</b>
<blockquote>
	<a href="<?php echo $rdf_url; ?>?frm=1"><?php echo $rdf_url; ?>?frm=1</a> will generate a pdf with all the messages from forum with an id of 1.<br />
	<a href="<?php echo $rdf_url; ?>?frm=1&page=3"><?php echo $rdf_url; ?>?frm=1&page=3</a> will generate a pdf with all the messages from forum with an id of 1, which can be found on page 3.<br />
	<a href="<?php echo $rdf_url; ?>?thread=1"><?php echo $rdf_url; ?>?thread=1</a> will generate a pdf with all the messages from topic with an id of 1.<br />
	<a href="<?php echo $rdf_url; ?>?msg=1"><?php echo $rdf_url; ?>?msg=1</a> will generate a pdf contaning a message with an id of 1.<br />
</blockquote>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
