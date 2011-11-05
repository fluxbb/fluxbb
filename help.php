<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the help template
define('PUN_HELP', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang->t('No view'));


// Load the help.php language file
$lang->load('help');


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Help'));
define('PUN_ACTIVE_PAGE', 'help');
require PUN_ROOT.'header.php';

?>
<h2><span><?php echo $lang->t('BBCode') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="bbcode"></a><?php echo $lang->t('BBCode info 1') ?></p>
		<p><?php echo $lang->t('BBCode info 2') ?></p>
	</div>
</div>
<h2><span><?php echo $lang->t('Text style') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang->t('Text style info') ?></p>
		<p><code>[b]<?php echo $lang->t('Bold text') ?>[/b]</code> <?php echo $lang->t('produces') ?> <samp><strong><?php echo $lang->t('Bold text') ?></strong></samp></p>
		<p><code>[u]<?php echo $lang->t('Underlined text') ?>[/u]</code> <?php echo $lang->t('produces') ?> <samp><span class="bbu"><?php echo $lang->t('Underlined text') ?></span></samp></p>
		<p><code>[i]<?php echo $lang->t('Italic text') ?>[/i]</code> <?php echo $lang->t('produces') ?> <samp><em><?php echo $lang->t('Italic text') ?></em></samp></p>
		<p><code>[s]<?php echo $lang->t('Strike-through text') ?>[/s]</code> <?php echo $lang->t('produces') ?> <samp><span class="bbs"><?php echo $lang->t('Strike-through text') ?></span></samp></p>
		<p><code>[del]<?php echo $lang->t('Deleted text') ?>[/del]</code> <?php echo $lang->t('produces') ?> <samp><del><?php echo $lang->t('Deleted text') ?></del></samp></p>
		<p><code>[ins]<?php echo $lang->t('Inserted text') ?>[/ins]</code> <?php echo $lang->t('produces') ?> <samp><ins><?php echo $lang->t('Inserted text') ?></ins></samp></p>
		<p><code>[em]<?php echo $lang->t('Emphasised text') ?>[/em]</code> <?php echo $lang->t('produces') ?> <samp><em><?php echo $lang->t('Emphasised text') ?></em></samp></p>
		<p><code>[color=#FF0000]<?php echo $lang->t('Red text') ?>[/color]</code> <?php echo $lang->t('produces') ?> <samp><span style="color: #ff0000"><?php echo $lang->t('Red text') ?></span></samp></p>
		<p><code>[color=blue]<?php echo $lang->t('Blue text') ?>[/color]</code> <?php echo $lang->t('produces') ?> <samp><span style="color: blue"><?php echo $lang->t('Blue text') ?></span></samp></p>
		<p><code>[h]<?php echo $lang->t('Heading text') ?>[/h]</code> <?php echo $lang->t('produces') ?></p> <div class="postmsg"><h5><?php echo $lang->t('Heading text') ?></h5></div>
	</div>
</div>
<h2><span><?php echo $lang->t('Links and images') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang->t('Links info') ?></p>
		<p><code>[url=<?php echo pun_htmlspecialchars(get_base_url(true).'/') ?>]<?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?>[/url]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/') ?>"><?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?></a></samp></p>
		<p><code>[url]<?php echo pun_htmlspecialchars(get_base_url(true).'/') ?>[/url]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/') ?>"><?php echo pun_htmlspecialchars(get_base_url(true).'/') ?></a></samp></p>
		<p><code>[url=/help.php]<?php echo $lang->t('This help page') ?>[/url]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo get_base_url(true).'/help.php' ?>"><?php echo $lang->t('This help page') ?></a></samp></p>
		<p><code>[email]myname@mydomain.com[/email]</code> <?php echo $lang->t('produces') ?> <samp><a href="mailto:myname@mydomain.com">myname@mydomain.com</a></samp></p>
		<p><code>[email=myname@mydomain.com]<?php echo $lang->t('My email address') ?>[/email]</code> <?php echo $lang->t('produces') ?> <samp><a href="mailto:myname@mydomain.com"><?php echo $lang->t('My email address') ?></a></samp></p>
		<p><code>[topic=1]<?php echo $lang->t('Test topic') ?>[/topic]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?id=1') ?>"><?php echo $lang->t('Test topic') ?></a></samp></p>
		<p><code>[topic]1[/topic]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?id=1') ?>"><?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?id=1') ?></a></samp></p>
		<p><code>[post=1]<?php echo $lang->t('Test post') ?>[/post]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?pid=1#p1') ?>"><?php echo $lang->t('Test post') ?></a></samp></p>
		<p><code>[post]1[/post]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?pid=1#p1') ?>"><?php echo pun_htmlspecialchars(get_base_url(true).'/viewtopic.php?pid=1#p1') ?></a></samp></p>
		<p><code>[forum=1]<?php echo $lang->t('Test forum') ?>[/forum]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewforum.php?id=1') ?>"><?php echo $lang->t('Test forum') ?></a></samp></p>
		<p><code>[forum]1[/forum]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/viewforum.php?id=1') ?>"><?php echo pun_htmlspecialchars(get_base_url(true).'/viewforum.php?id=1') ?></a></samp></p>
		<p><code>[user=2]<?php echo $lang->t('Test user') ?>[/user]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/profile.php?id=2') ?>"><?php echo $lang->t('Test user') ?></a></samp></p>
		<p><code>[user]2[/user]</code> <?php echo $lang->t('produces') ?> <samp><a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/profile.php?id=2') ?>"><?php echo pun_htmlspecialchars(get_base_url(true).'/profile.php?id=2') ?></a></samp></p>
	</div>
	<div class="inbox">
		<p><a name="img"></a><?php echo $lang->t('Images info') ?></p>
		<p><code>[img=<?php echo $lang->t('FluxBB bbcode test') ?>]<?php echo pun_htmlspecialchars(get_base_url(true)) ?>/img/test.png[/img]</code> <?php echo $lang->t('produces') ?> <samp><img style="height: 21px" src="<?php echo pun_htmlspecialchars(get_base_url(true)) ?>/img/test.png" alt="<?php echo $lang->t('FluxBB bbcode test') ?>" /></samp></p>
	</div>
</div>
<h2><span><?php echo $lang->t('Quotes') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang->t('Quotes info') ?></p>
		<p><code>[quote=James]<?php echo $lang->t('Quote text') ?>[/quote]</code></p>
		<p><?php echo $lang->t('produces quote box') ?></p>
		<div class="postmsg">
			<div class="quotebox"><cite>James <?php echo $lang->t('wrote') ?></cite><blockquote><div><p><?php echo $lang->t('Quote text') ?></p></div></blockquote></div>
		</div>
		<p><?php echo $lang->t('Quotes info 2') ?></p>
		<p><code>[quote]<?php echo $lang->t('Quote text') ?>[/quote]</code></p>
		<p><?php echo $lang->t('produces quote box') ?></p>
		<div class="postmsg">
			<div class="quotebox"><blockquote><div><p><?php echo $lang->t('Quote text') ?></p></div></blockquote></div>
		</div>
		<p><?php echo $lang->t('quote note') ?></p>
	</div>
</div>
<h2><span><?php echo $lang->t('Code') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang->t('Code info') ?></p>
		<p><code>[code]<?php echo $lang->t('Code text') ?>[/code]</code></p>
		<p><?php echo $lang->t('produces code box') ?></p>
		<div class="postmsg">
			<div class="codebox"><pre><code><?php echo $lang->t('Code text') ?></code></pre></div>
		</div>
	</div>
</div>
<h2><span><?php echo $lang->t('Lists') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="lists"></a><?php echo $lang->t('List info') ?></p>
		<p><code>[list][*]<?php echo $lang->t('List text 1') ?>[/*][*]<?php echo $lang->t('List text 2') ?>[/*][*]<?php echo $lang->t('List text 3') ?>[/*][/list]</code>
		<br /><span><?php echo $lang->t('produces list') ?></span></p>
		<div class="postmsg">
			<ul><li><p><?php echo $lang->t('List text 1') ?></p></li><li><p><?php echo $lang->t('List text 2') ?></p></li><li><p><?php echo $lang->t('List text 3') ?></p></li></ul>
		</div>
		<p><code>[list=1][*]<?php echo $lang->t('List text 1') ?>[/*][*]<?php echo $lang->t('List text 2') ?>[/*][*]<?php echo $lang->t('List text 3') ?>[/*][/list]</code>
		<br /><span><?php echo $lang->t('produces decimal list') ?></span></p>
		<div class="postmsg">
			<ol class="decimal"><li><p><?php echo $lang->t('List text 1') ?></p></li><li><p><?php echo $lang->t('List text 2') ?></p></li><li><p><?php echo $lang->t('List text 3') ?></p></li></ol>
		</div>
		<p><code>[list=a][*]<?php echo $lang->t('List text 1') ?>[/*][*]<?php echo $lang->t('List text 2') ?>[/*][*]<?php echo $lang->t('List text 3') ?>[/*][/list]</code>
		<br /><span><?php echo $lang->t('produces alpha list') ?></span></p>
		<div class="postmsg">
			<ol class="alpha"><li><p><?php echo $lang->t('List text 1') ?></p></li><li><p><?php echo $lang->t('List text 2') ?></p></li><li><p><?php echo $lang->t('List text 3') ?></p></li></ol>
		</div>
	</div>
</div>
<h2><span><?php echo $lang->t('Nested tags') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang->t('Nested tags info') ?></p>
		<p><code>[b][u]<?php echo $lang->t('Bold, underlined text') ?>[/u][/b]</code> <?php echo $lang->t('produces') ?> <samp><strong><span class="bbu"><?php echo $lang->t('Bold, underlined text') ?></span></strong></samp></p>
	</div>
</div>
<h2><span><?php echo $lang->t('Smilies') ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="smilies"></a><?php echo $lang->t('Smilies info') ?></p>
<?php

// Display the smiley set
require PUN_ROOT.'include/parser.php';

$smiley_groups = array();

foreach ($smilies as $smiley_text => $smiley_img)
	$smiley_groups[$smiley_img][] = $smiley_text;

foreach ($smiley_groups as $smiley_img => $smiley_texts)
	echo "\t\t".'<p><code>'.implode('</code> '.$lang->t('and').' <code>', $smiley_texts).'</code> <span>'.$lang->t('produces').'</span> <samp><img src="'.pun_htmlspecialchars(get_base_url(true)).'/img/smilies/'.$smiley_img.'" width="15" height="15" alt="'.$smiley_texts[0].'" /></samp></p>'."\n";

?>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
