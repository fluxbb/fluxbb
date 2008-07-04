<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('he_start')) ? eval($hook) : null;

$section = isset($_GET['section']) ? $_GET['section'] : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the help.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/help.php';


$page_title = forum_htmlencode($forum_config['o_board_title']).' - '.$lang_help['Help'];
define('FORUM_PAGE', 'help');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('he_main_output_start')) ? eval($hook) : null;


?>
<div id="brd-main" class="main">

	<h1><span><?php echo $lang_help['Help'] ?></span></h1>

<?php

if (!$section || $section == 'bbcode')
{

?>
	<div class="main-head">
		<h2><span><?php printf($lang_help['Help with'], $lang_common['BBCode']) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="frm-info">
			<p><?php echo $lang_help['BBCode info 1'] ?></p>
			<p><?php echo $lang_help['BBCode info 2'] ?></p>
		</div>
		<div class="frm-form">
			<div class="frm-fset fset1">
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
<?php ($hook = get_hook('he_new_bbcode_text_style')) ? eval($hook) : null; ?>
				</ul>
			</div>
			<div class="frm-set">
				<h3><?php echo $lang_help['Links info'] ?></h3>
				<ul class="example">
					<li>
						<code>[url=<?php echo $base_url.'/' ?>]<?php echo forum_htmlencode($forum_config['o_board_title']) ?>[/url]</code> <span><?php echo $lang_help['produces'] ?></span>
						<samp><a href="<?php echo $base_url.'/' ?>"><?php echo forum_htmlencode($forum_config['o_board_title']) ?></a></samp>
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
<?php ($hook = get_hook('he_new_bbcode_link')) ? eval($hook) : null; ?>
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
			<div class="frm-set">
				<h3><span><?php echo $lang_help['List info'] ?></span></h3>
				<ul class="example">
					<li><code>[list][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces list'] ?></span>
						<div class="entry-content samp">
							<ul><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
						</div>
					</li>
					<li><code>[list=1][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces decimal list'] ?></span>
						<div class="entry-content samp">
							<ol class="decimal"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
						</div>
					</li>
					<li><code>[list=a][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces alpha list'] ?></span>
						<div class="entry-content samp">
							<ol class="alpha"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
						</div>
					</li>
				</ul>
			</div>
<?php ($hook = get_hook('he_new_bbcode_section')) ? eval($hook) : null; ?>
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
	<div class="main-content main-frm">
		<div class="frm-info">
			<p><?php echo $lang_help['Image info 1'] ?></p>
			<p><?php echo $lang_help['Image info 2'] ?></p>
		</div>
		<div class="frm-form">
			<ul class="example">
				<li>
					<code>[img=FluxBB bbcode test]<?php echo $base_url ?>/img/test.png[/img]</code>
					<samp><img src="<?php echo $base_url ?>/img/test.png" alt="FluxBB bbcode test" /></samp>
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

	<div class="main-content main-frm">
		<div class="frm-info">
			<p><?php echo $lang_help['Smilies info'] ?></p>
		</div>
		<div class="frm-form">
			<ul class="example">
<?php

	// Display the smiley set
	if (!defined('FORUM_PARSER_LOADED'))
		require FORUM_ROOT.'include/parser.php';

	$smiley_groups = array();

	foreach ($smilies as $smiley_text => $smiley_img)
		$smiley_groups[$smiley_img][] = $smiley_text;

	foreach ($smiley_groups as $smiley_img => $smiley_texts)
		echo "\t\t\t\t".'<li><code>'.implode(' '.$lang_common['and'].' ', $smiley_texts).' <span>'.$lang_help['produces'].'</span> <img src="'.$base_url.'/img/smilies/'.$smiley_img.'" width="15" height="15" alt="'.$smiley_texts[0].'" /></code></li>'."\n";

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

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
