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
		<a name="bbcode"></a><?php echo $lang_help['BBCode info 1'] ?><br /><br />
		<?php echo $lang_help['BBCode info 2'] ?>
	</div>
</div>
<h2><?php echo $lang_help['Text style'] ?></h2>
<div class="box">
	<div class="inbox">
		<?php echo $lang_help['Text style info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[b]<?php echo $lang_help['Bold text'] ?>[/b] <?php echo $lang_help['produces'] ?> <strong><?php echo $lang_help['Bold text'] ?></strong><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[u]<?php echo $lang_help['Underlined text'] ?>[/u] <?php echo $lang_help['produces'] ?> <span class="bbu"><?php echo $lang_help['Underlined text'] ?></span><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[i]<?php echo $lang_help['Italic text'] ?>[/i] <?php echo $lang_help['produces'] ?> <i><?php echo $lang_help['Italic text'] ?></i><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[color=#FF0000]<?php echo $lang_help['Red text'] ?>[/color] <?php echo $lang_help['produces'] ?> <span style="color: #ff0000"><?php echo $lang_help['Red text'] ?></span><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[color=blue]<?php echo $lang_help['Blue text'] ?>[/color] <?php echo $lang_help['produces'] ?> <span style="color: blue"><?php echo $lang_help['Blue text'] ?></span>
	</div>
</div>
<h2><?php echo $lang_help['Links and images'] ?></h2>
<div class="box">
	<div class="inbox">
		<?php echo $lang_help['Links info'] ?><br /><br />		
		&nbsp;&nbsp;&nbsp;&nbsp;[url=<?php echo $pun_config['o_base_url'].'/' ?>]<?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?>[/url] <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'].'/' ?>"><?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?></a><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[url]<?php echo $pun_config['o_base_url'].'/' ?>[/url] <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'] ?>"><?php echo $pun_config['o_base_url'].'/' ?></a><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[email]myname@mydomain.com[/email] <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com">myname@mydomain.com</a><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[email=myname@mydomain.com]<?php echo $lang_help['My e-mail address'] ?>[/email] <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com"><?php echo $lang_help['My e-mail address'] ?></a>
	</div>
	<br /><br />
	<div class="inbox">
		<a name="img"></a><?php echo $lang_help['Images info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[img=FluxBB bbcode test]<?php echo $pun_config['o_base_url'].'/' ?>img/test.png[/img] <?php echo $lang_help['produces'] ?> <img src="<?php echo $pun_config['o_base_url'].'/' ?>img/test.png" alt="FluxBB bbcode test" />
	</div>
</div>
<h2><?php echo $lang_help['Quotes'] ?></h2>
<div class="box">
	<div class="inbox">
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
	<div class="inbox">
		<?php echo $lang_help['Code info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[code]<?php echo $lang_help['Code text'] ?>[/code]<br /><br />
		<?php echo $lang_help['produces code box'] ?><br /><br />
		<div class="postmsg">
			<div class="codebox"><div class="incqbox"><h4><?php echo $lang_common['Code'] ?>:</h4><div class="scrollbox" style="height: 4.5em"><pre><?php echo $lang_help['Code text'] ?></pre></div></div></div>
		</div>
	</div>
</div>
<h2><?php echo $lang_help['Lists'] ?></h2>
<div class="box">
	<div class="inbox">
		<a name="lists"></a><?php echo $lang_help['List info'] ?><br /><br />

		<p><code>[list][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces list'] ?></span></p>
		<ul><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ul>

		<p><code>[list=1][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces decimal list'] ?></span></p>
		<ol class="decimal"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>

		<p><code>[list=a][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces alpha list'] ?></span></p>
		<ol class="alpha"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
	</div>
</div>
<h2><?php echo $lang_help['Nested tags'] ?></h2>
<div class="box">
	<div class="inbox">
		<?php echo $lang_help['Nested tags info'] ?><br /><br />
		&nbsp;&nbsp;&nbsp;&nbsp;[b][u]<?php echo $lang_help['Bold, underlined text'] ?>[/u][/b] <?php echo $lang_help['produces'] ?> <strong><span class="bbu"><?php echo $lang_help['Bold, underlined text'] ?></span></strong>
	</div>
</div>
<h2><?php echo $lang_common['Smilies'] ?></h2>
<div class="box">
	<div class="inbox">
		<a name="smilies"></a><?php echo $lang_help['Smilies info'] ?><br /><br />

		<ul>
<?php

// Display the smiley set
require PUN_ROOT.'include/parser.php';

$smiley_groups = array();

foreach ($smilies as $smiley_text => $smiley_img)
	$smiley_groups[$smiley_img][] = $smiley_text;

foreach ($smiley_groups as $smiley_img => $smiley_texts)
	echo "\t\t\t".'<li>'.implode(' '.$lang_common['and'].' ', $smiley_texts).' <span>'.$lang_help['produces'].'</span> <img src="img/smilies/'.$smiley_img.'" width="15" height="15" alt="'.$smiley_texts[0].'" /></li>'."\n";

?>
		</ul>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
