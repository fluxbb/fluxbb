<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('he_start')) ? eval($hook) : null;

$section = isset($_GET['section']) ? $_GET['section'] : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the help.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/help.php';


$page_title = pun_htmlencode($pun_config['o_board_title']).' - '.$lang_help['Help'];
define('PUN_PAGE', 'help');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_help['Help'] ?></span></h1>

<?php

if (!$section || $section == 'bbcode')
{

?>
	<div class="main-head">
		<h2><span><?php printf($lang_help['Help with'], $lang_common['BBCode']) ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<p><?php echo $lang_help['BBCode info 1'] ?></p>
			<p><?php echo $lang_help['BBCode info 2'] ?></p>
		</div>
		<div class="frm-form">
			<div class="frm-set set1">
				<h3><?php echo $lang_help['Text style'] ?></h3>
				<ul class="example">
					<li>
						<code>[b]<?php echo $lang_help['Bold text'] ?>[/b]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><strong><?php echo $lang_help['Bold text'] ?></strong></samp>
					</li>
					<li>
						<code>[u]<?php echo $lang_help['Underlined text'] ?>[/u]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><em class="bbuline"><?php echo $lang_help['Underlined text'] ?></em></samp>
					</li>
					<li>
						<code>[i]<?php echo $lang_help['Italic text'] ?>[/i]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><i><?php echo $lang_help['Italic text'] ?></i></samp>
					</li>
					<li>
						<code>[color=#FF0000]<?php echo $lang_help['Red text'] ?>[/color]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><span style="color: #ff0000"><?php echo $lang_help['Red text'] ?></span></samp>
					</li>
					<li>
						<code>[color=blue]<?php echo $lang_help['Blue text'] ?>[/color]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><span style="color: blue"><?php echo $lang_help['Blue text'] ?></span></samp>
					</li>
					<li>
						<code>[b][u]<?php echo $lang_help['Bold, underlined text'] ?>[/u][/b]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><em class="bbuline"><b><?php echo $lang_help['Bold, underlined text'] ?></b></em></samp>
					</li>
				</ul>
			</div>
			<div class="frm-set">
				<h3><?php echo $lang_help['Links info'] ?></h3>
				<ul class="example">
					<li>
						<code>[url=<?php echo $base_url.'/' ?>]<?php echo pun_htmlencode($pun_config['o_board_title']) ?>[/url]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><a href="<?php echo $base_url.'/' ?>"><?php echo pun_htmlencode($pun_config['o_board_title']) ?></a></samp>
					</li>
					<li>
						<code>[url]<?php echo $base_url.'/' ?>[/url]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><a href="<?php echo $base_url ?>"><?php echo $base_url.'/' ?></a></samp>
					</li>
					<li>
						<code>[email]name@example.com[/email]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><a href="mailto:name@example.com">name@example.com</a></samp>
					</li>
					<li>
						<code>[email=name@example.com]<?php echo $lang_help['My e-mail address'] ?>[/email]</code><span><?php echo $lang_help['produces'] ?></span>
						<samp><a href="mailto:name@example.com"><?php echo $lang_help['My e-mail address'] ?></a></samp>
					</li>
				</ul>
			</div>
			<div class="frm-set">
				<h3><span><?php echo $lang_help['Quotes info'] ?></span></h3>
				<ul class="example">
					<li><code>[quote=James]<?php echo $lang_help['Quote text'] ?>[/quote]</code> <span><?php echo $lang_help['produces named'] ?></span>
						<div class="entry-content samp">
							<div class="quotebox"><cite>James <?php echo $lang_common['wrote'] ?>:</cite><blockquote><p><?php echo $lang_help['Quote text'] ?></p></blockquote></div>
						</div>
					</li>
					<li><code>[quote]<?php echo $lang_help['Quote text'] ?>[/quote]</code> <span><?php echo $lang_help['produces unnamed'] ?></span>
						<div class="entry-content samp">
							<div class="quotebox"><blockquote><p><?php echo $lang_help['Quote text'] ?></p></blockquote></div>
						</div>
					</li>
				</ul>
			</div>
			<div class="frm-set">
				<h3><span><?php echo $lang_help['Code info'] ?></span></h3>
				<ul class="example">
					<li><code>[code]<?php echo $lang_help['Code text'] ?>[/code]</code> <span><?php echo $lang_help['produces code box'] ?></span>
						<div class="entry-content samp">
							<div class="codebox"><strong class="legend"><?php echo $lang_common['Code'] ?>:</strong><pre><code><?php echo $lang_help['Code text'] ?></code></pre></div>
						</div>
					</li>
					<li><code>[code]<?php echo $lang_help['Code text long'] ?>[/code]</code> <span><?php echo $lang_help['produces scroll box'] ?></span>
						<div class="entry-content samp">
							<div class="codebox"><strong class="legend"><?php echo $lang_common['Code'] ?>:</strong><pre><code><?php echo $lang_help['Code text long'] ?></code></pre></div>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</div>
<?php

}
else if ($section == 'img')
{

?>
	<div class="main-head">
		<h2><span><?php printf($lang_help['Help with'], $lang_common['Images']) ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<p><?php echo $lang_help['Image info 1'] ?></p>
			<p><?php echo $lang_help['Image info 2'] ?></p>
		</div>
		<div class="frm-form">
			<ul class="example">
				<li>
					<code>[img=PunBB logo]<?php echo $base_url ?>/img/logo.png[/img]</code>
					<samp><img src="<?php echo $base_url ?>/img/logo.png" alt="PunBB logo" /></samp>
				</li>
			</ul>
		</div>
	</div>
<?php

}
else if ($section == 'smilies')
{

?>
	<div id="smilies" class="main-head">
		<h2><span><?php printf($lang_help['Help with'], $lang_common['Smilies']) ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<p><?php echo $lang_help['Smilies info'] ?></p>
		</div>
		<div class="frm-form">
			<ul class="example">
<?php

	// Display the smiley set
	require PUN_ROOT.'include/parser.php';

	$num_smilies = count($smiley_text);
	for ($i = 0; $i < $num_smilies; ++$i)
	{
		// Is there a smiley at the current index?
		if (!isset($smiley_text[$i]))
			continue;

		echo "\t\t\t\t".'<li><code>'.$smiley_text[$i];

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

		echo ' <span>'.$lang_help['produces'].'</span> <img src="'.$base_url.'/img/smilies/'.$cur_img.'" width="15" height="15" alt="'.$cur_text.'" /></code></li>'."\n";
	}

?>
			</ul>
		</div>
	</div>
<?php

}

($hook = get_hook('he_new_section')) ? eval($hook) : null;

?>

</div>
<?php

($hook = get_hook('he_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
