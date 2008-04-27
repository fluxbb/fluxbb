<?php
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// Tell header.php to use the help template
define('PUN_HELP', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


// Load the help.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/help.php';


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$lang_help['Help'];
require PUN_ROOT.'header.php';

?>
<h2><?php echo $lang_common['BBCode'] ?></h2>
<div class="box">
	<div class="inbox">
		<p><a name="bbcode"></a><?php echo $lang_help['BBCode info 1'] ?></p><br />
		<p><?php echo $lang_help['BBCode info 2'] ?></p>
	</div>
</div>
<h2><?php echo $lang_help['Text style'] ?></h2>
<div class="box">
	<p><?php echo $lang_help['Text style info'] ?></p><br />
	<div style="padding-left: 4px">
		[b]<?php echo $lang_help['Bold text'] ?>[/b] <?php echo $lang_help['produces'] ?> <b><?php echo $lang_help['Bold text'] ?></b><br />
		[u]<?php echo $lang_help['Underlined text'] ?>[/u] <?php echo $lang_help['produces'] ?> <span class="bbu"><?php echo $lang_help['Underlined text'] ?></span><br />
		[i]<?php echo $lang_help['Italic text'] ?>[/i] <?php echo $lang_help['produces'] ?> <i><?php echo $lang_help['Italic text'] ?></i><br />
		[color=#FF0000]<?php echo $lang_help['Red text'] ?>[/color] <?php echo $lang_help['produces'] ?> <span style="color: #ff0000"><?php echo $lang_help['Red text'] ?></span><br />
		[color=blue]<?php echo $lang_help['Blue text'] ?>[/color] <?php echo $lang_help['produces'] ?> <span style="color: blue"><?php echo $lang_help['Blue text'] ?></span>
	</div>
</div>
<h2><?php echo $lang_help['Links and images'] ?></h2>
<div class="box">
	<p><?php echo $lang_help['Links info'] ?></p><br />
	<div style="padding-left: 4px">
		[url=<?php echo $pun_config['o_base_url'].'/' ?>]<?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?>[/url] <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'].'/' ?>"><?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?></a><br />
		[url]<?php echo $pun_config['o_base_url'].'/' ?>[/url] <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'] ?>"><?php echo $pun_config['o_base_url'].'/' ?></a><br />
		[email]myname@mydomain.com[/email] <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com">myname@mydomain.com</a><br />
		[email=myname@mydomain.com]<?php echo $lang_help['My e-mail address'] ?>[/email] <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com"><?php echo $lang_help['My e-mail address'] ?></a><br /><br />
	</div>
	<p><a name="img"></a><?php echo $lang_help['Images info'] ?></p>
	<div>[img]http://www.punbb.org/img/small_logo.png[/img] <?php echo $lang_help['produces'] ?> <img src="http://www.punbb.org/img/small_logo.png" alt="http://www.punbb.org/img/small_logo.png" /></div>
</div>
<h2><?php echo $lang_help['Quotes'] ?></h2>
<div class="box">
	<div style="padding-left: 4px">
		<?php echo $lang_help['Quotes info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[quote=James]<?php echo $lang_help['Quote text'] ?>[/quote]<br /><br />
		<?php echo $lang_help['produces quote box'] ?><br /><br />
		<div class="postmsg">
			<blockquote><div class="incqbox"><h4>James <?php echo $lang_common['wrote'] ?>:</h4><p><?php echo $lang_help['Quote text'] ?></p></div></blockquote>
		</div>
		<br />
		<?php echo $lang_help['Quotes info 2'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[quote]<?php echo $lang_help['Quote text'] ?>[/quote]<br /><br />
		<?php echo $lang_help['produces quote box'] ?><br /><br />
		<div class="postmsg">
			<blockquote><div class="incqbox"><p><?php echo $lang_help['Quote text'] ?></p></div></blockquote>
		</div>
	</div>
</div>
<h2><?php echo $lang_help['Code'] ?></h2>
<div class="box">
	<div style="padding-left: 4px">
		<?php echo $lang_help['Code info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[code]<?php echo $lang_help['Code text'] ?>[/code]<br /><br />
		<?php echo $lang_help['produces code box'] ?><br /><br />
		<div class="postmsg">
			<div class="codebox"><div class="incqbox"><h4><?php echo $lang_common['Code'] ?>:</h4><div class="scrollbox" style="height: 4.5em"><pre><?php echo $lang_help['Code text'] ?></pre></div></div></div>
		</div>
	</div>
</div>
<h2><?php echo $lang_help['Nested tags'] ?></h2>
<div class="box">
	<div style="padding-left: 4px">
		<?php echo $lang_help['Nested tags info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[b][u]<?php echo $lang_help['Bold, underlined text'] ?>[/u][/b] <?php echo $lang_help['produces'] ?> <span class="bbu"><b><?php echo $lang_help['Bold, underlined text'] ?></b></span><br /><br />
	</div>
</div>
<h2><?php echo $lang_common['Smilies'] ?></h2>
<div class="box">
	<div style="padding-left: 4px">
		<a name="smilies"></a><?php echo $lang_help['Smilies info'] ?><br /><br />
<?php

// Display the smiley set
require PUN_ROOT.'include/parser.php';

$num_smilies = count($smiley_text);
for ($i = 0; $i < $num_smilies; ++$i)
{
	// Is there a smiley at the current index?
	if (!isset($smiley_text[$i]))
		continue;

	echo "\t\t".'&nbsp;&nbsp;&nbsp;&nbsp;'.$smiley_text[$i];

	// Save the current text and image
	$cur_img = $smiley_img[$i];
	$cur_text = $smiley_text[$i];

	// Loop through the rest of the array and see if there are any duplicate images
	// (more than one text representation for one image)
	for ($next = $i + 1; $next < $num_smilies; ++$next)
	{
		// Did we find a dupe?
		if (isset($smiley_img[$next]) && $smiley_img[$i] == $smiley_img[$next])
		{
			echo ' '.$lang_common['and'].' '.$smiley_text[$next];

			// Remove the dupe so we won't display it twice
			unset($smiley_text[$next]);
			unset($smiley_img[$next]);
		}
	}

	echo ' '.$lang_help['produces'].' <img src="img/smilies/'.$cur_img.'" width="15" height="15" alt="'.$cur_text.'" /><br />'."\n";
}

?>
		<br />
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
