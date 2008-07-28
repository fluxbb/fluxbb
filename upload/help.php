<?php
/**
 * Help page
 *
 * Provides examples of how to use various features of the forum (ie: BBCode, smilies)
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('he_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

($hook = get_hook('he_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;


?>
<div id="brd-main" class="main">

<div class="main-head">
	<h1 class="hn"><span><?php echo $lang_help['Help'] ?></span></h1>
</div>
<?php

if (!$section || $section == 'bbcode')
{

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_help['Help with'], $lang_common['BBCode']) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php echo $lang_help['BBCode info'] ?></p>
		</div>
		<div class="ct-box help-box">
			<h3 class="hn"><span><?php echo $lang_help['Text style'] ?></span></h3>
			<div class="entry-content">
				<code>[b]<?php echo $lang_help['Bold text'] ?>[/b]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><strong><?php echo $lang_help['Bold text'] ?></strong></samp>
			</div>
			<div class="entry-content">
				<code>[u]<?php echo $lang_help['Underlined text'] ?>[/u]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><span class="bbu"><?php echo $lang_help['Underlined text'] ?></span></samp>
			</div>
			<div class="entry-content">
				<code>[i]<?php echo $lang_help['Italic text'] ?>[/i]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><i><?php echo $lang_help['Italic text'] ?></i></samp>
			</div>
			<div class="entry-content">
				<code>[color=#FF0000]<?php echo $lang_help['Red text'] ?>[/color]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><span style="color: #ff0000"><?php echo $lang_help['Red text'] ?></span></samp>
			</div>
			<div class="entry-content">
				<code>[color=blue]<?php echo $lang_help['Blue text'] ?>[/color]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><span style="color: blue"><?php echo $lang_help['Blue text'] ?></span></samp>
			</div>
			<div class="entry-content">
				<code>[b][u]<?php echo $lang_help['Bold, underlined text'] ?>[/u][/b]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><span class="bbu"><strong><?php echo $lang_help['Bold, underlined text'] ?></strong></span></samp>
			</div>
			<div class="entry-content">
				<code>[h]<?php echo $lang_help['Heading text'] ?>[/h]</code> <span><?php echo $lang_help['produces'] ?></span>
				<div class="entry-content"><h5><samp><?php echo $lang_help['Heading text'] ?></samp></h5></div>
			</div>
<?php ($hook = get_hook('he_new_bbcode_text_style')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
		</div>
		<div class="ct-box help-box">
			<h3 class="hn"><span><?php echo $lang_help['Links info'] ?></span></h3>
			<div class="entry-content">
				<code>[url=<?php echo $base_url.'/' ?>]<?php echo forum_htmlencode($forum_config['o_board_title']) ?>[/url]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><a href="<?php echo $base_url.'/' ?>"><?php echo forum_htmlencode($forum_config['o_board_title']) ?></a></samp>
			</div>
			<div class="entry-content">
				<code>[url]<?php echo $base_url.'/' ?>[/url]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><a href="<?php echo $base_url ?>"><?php echo $base_url.'/' ?></a></samp>
			</div>
			<div class="entry-content">
				<code>[email]name@example.com[/email]</code> <span><?php echo $lang_help['produces'] ?></span>
				<samp><a href="mailto:name@example.com">name@example.com</a></samp>
			</div>
			<div class="entry-content">
				<code>[email=name@example.com]<?php echo $lang_help['My e-mail address'] ?>[/email]</code><span><?php echo $lang_help['produces'] ?></span>
				<samp><a href="mailto:name@example.com"><?php echo $lang_help['My e-mail address'] ?></a></samp>
			</div>
<?php ($hook = get_hook('he_new_bbcode_link')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
		</div>
		<div class="ct-box help-box">
			<h3 class="hn"><span><?php echo $lang_help['Quotes info'] ?></span></h3>
			<div class="entry-content">
				<code>[quote=James]<?php echo $lang_help['Quote text'] ?>[/quote]</code> <span><?php echo $lang_help['produces named'] ?></span>
				<div class="quotebox"><cite>James <?php echo $lang_common['wrote'] ?>:</cite><blockquote><p><?php echo $lang_help['Quote text'] ?></p></blockquote></div>
				<code>[quote]<?php echo $lang_help['Quote text'] ?>[/quote]</code> <span><?php echo $lang_help['produces unnamed'] ?></span>
				<div class="quotebox"><blockquote><p><?php echo $lang_help['Quote text'] ?></p></blockquote></div>
			</div>
		</div>
		<div class="ct-box help-box">
			<h3 class="hn"><span><?php echo $lang_help['Code info'] ?></span></h3>
			<div class="entry-content">
				<code>[code]<?php echo $lang_help['Code text'] ?>[/code]</code> <span><?php echo $lang_help['produces code box'] ?></span>
				<div class="codebox"><pre><code><?php echo $lang_help['Code text'] ?></code></pre></div>
				<code>[code]<?php echo $lang_help['Code text long'] ?>[/code]</code> <span><?php echo $lang_help['produces scroll box'] ?></span>
				<div class="codebox"><pre><code><?php echo $lang_help['Code text long'] ?></code></pre></div>
			</div>
		</div>
		<div class="ct-box help-box">
			<h3 class="hn"><span><?php echo $lang_help['List info'] ?></span></h3>
			<div class="entry-content">
				<code>[list][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces list'] ?></span>
				<ul><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ul>
				<code>[list=1][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces decimal list'] ?></span>
				<ol class="decimal"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
				<code>[list=a][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces alpha list'] ?></span>
				<ol class="alpha"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
			</div>
		</div>
<?php ($hook = get_hook('he_new_bbcode_section')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
	</div>
<?php

}
else if ($section == 'img')
{

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_help['Help with'], $lang_common['Images']) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box help-box">
			<p class="hn"><?php echo $lang_help['Image info'] ?></p>
			<div class="entry-content">
				<code>[img=FluxBB bbcode test]<?php echo $base_url ?>/img/test.png[/img]</code>
				<samp><img src="<?php echo $base_url ?>/img/test.png" alt="FluxBB bbcode test" /></samp>
			</div>
		</div>
	</div>
<?php

}
else if ($section == 'smilies')
{

?>
	<div id="smilies" class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_help['Help with'], $lang_common['Smilies']) ?></span></h2>
	</div>

	<div class="main-content main-frm">
		<div class="ct-box help-box">
			<p class="hn"><?php echo $lang_help['Smilies info'] ?></p>
			<div class="entry-content">
<?php

	// Display the smiley set
	if (!defined('FORUM_PARSER_LOADED'))
		require FORUM_ROOT.'include/parser.php';

	$smiley_groups = array();

	foreach ($smilies as $smiley_text => $smiley_img)
		$smiley_groups[$smiley_img][] = $smiley_text;

	foreach ($smiley_groups as $smiley_img => $smiley_texts)
		echo "\t\t\t\t".'<p>'.implode(' '.$lang_common['and'].' ', $smiley_texts).' <span>'.$lang_help['produces'].'</span> <img src="'.$base_url.'/img/smilies/'.$smiley_img.'" width="15" height="15" alt="'.$smiley_texts[0].'" /></p>'."\n";

?>
			</div>
		</div>
	</div>
<?php

}

($hook = get_hook('he_new_section')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>

</div>
<?php

($hook = get_hook('he_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
