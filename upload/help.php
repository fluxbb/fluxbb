<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the help template
define('PUN_HELP', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


// Load the help.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/help.php';


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_help['Help']);
define('PUN_ACTIVE_PAGE', 'help');
require PUN_ROOT.'header.php';

?>
<h2><span><?php echo $lang_help['BBCode'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="bbcode"></a><?php echo $lang_help['BBCode info 1'] ?></p>
		<p><?php echo $lang_help['BBCode info 2'] ?></p>
	</div>
</div>
<h2><span><?php echo $lang_help['Text style'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang_help['Text style info'] ?></p>
		<p><code>[b]<?php echo $lang_help['Bold text'] ?>[/b]</code> <?php echo $lang_help['produces'] ?> <strong><?php echo $lang_help['Bold text'] ?></strong></p>
		<p><code>[u]<?php echo $lang_help['Underlined text'] ?>[/u]</code> <?php echo $lang_help['produces'] ?> <span class="bbu"><?php echo $lang_help['Underlined text'] ?></span></p>
		<p><code>[i]<?php echo $lang_help['Italic text'] ?>[/i]</code> <?php echo $lang_help['produces'] ?> <i><?php echo $lang_help['Italic text'] ?></i></p>
		<p><code>[color=#FF0000]<?php echo $lang_help['Red text'] ?>[/color]</code> <?php echo $lang_help['produces'] ?> <span style="color: #ff0000"><?php echo $lang_help['Red text'] ?></span></p>
		<p><code>[color=blue]<?php echo $lang_help['Blue text'] ?>[/color]</code> <?php echo $lang_help['produces'] ?> <span style="color: blue"><?php echo $lang_help['Blue text'] ?></span></p>
		<p><code>[h]<?php echo $lang_help['Heading text'] ?>[/h]</code> <?php echo $lang_help['produces'] ?></p><h5><?php echo $lang_help['Heading text'] ?></h5>
	</div>
</div>
<h2><span><?php echo $lang_help['Links and images'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang_help['Links info'] ?></p>
		<p><code>[url=<?php echo $pun_config['o_base_url'].'/' ?>]<?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?>[/url]</code> <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'].'/' ?>"><?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?></a></p>
		<p><code>[url]<?php echo $pun_config['o_base_url'].'/' ?>[/url]</code> <?php echo $lang_help['produces'] ?> <a href="<?php echo $pun_config['o_base_url'] ?>"><?php echo $pun_config['o_base_url'].'/' ?></a></p>
		<p><code>[email]myname@mydomain.com[/email]</code> <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com">myname@mydomain.com</a></p>
		<p><code>[email=myname@mydomain.com]<?php echo $lang_help['My email address'] ?>[/email]</code> <?php echo $lang_help['produces'] ?> <a href="mailto:myname@mydomain.com"><?php echo $lang_help['My email address'] ?></a></p>
	</div>
	<div class="inbox">
		<p><a name="img"></a><?php echo $lang_help['Images info'] ?></p>
		<p><code>[img=FluxBB bbcode test]<?php echo $pun_config['o_base_url'].'/' ?>img/test.png[/img]</code> <?php echo $lang_help['produces'] ?> <img src="<?php echo $pun_config['o_base_url'].'/' ?>img/test.png" alt="FluxBB bbcode test" /></p>
	</div>
</div>
<h2><span><?php echo $lang_help['Quotes'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang_help['Quotes info'] ?></p>
		<p><code>[quote=James]<?php echo $lang_help['Quote text'] ?>[/quote]</code></p>
		<p><?php echo $lang_help['produces quote box'] ?></p>
		<div class="postmsg">
			<div class="quotebox"><cite>James <?php echo $lang_common['wrote'] ?></cite><blockquote><div><p><?php echo $lang_help['Quote text'] ?></p></div></blockquote></div>
		</div>
		<p><?php echo $lang_help['Quotes info 2'] ?></p>
		<p><code>[quote]<?php echo $lang_help['Quote text'] ?>[/quote]</code></p>
		<p><?php echo $lang_help['produces quote box'] ?></p>
		<div class="postmsg">
			<div class="quotebox"><blockquote><div><p><?php echo $lang_help['Quote text'] ?></p></div></blockquote></div>
		</div>
		<p><?php echo $lang_help['quote note'] ?></p>
	</div>
</div>
<h2><span><?php echo $lang_help['Code'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang_help['Code info'] ?></p>
		<p><code>[code]<?php echo $lang_help['Code text'] ?>[/code]</code></p>
		<p><?php echo $lang_help['produces code box'] ?></p>
		<div class="postmsg">
			<div class="codebox"><pre><code><?php echo $lang_help['Code text'] ?></code></pre></div>
		</div>
	</div>
</div>
<h2><span><?php echo $lang_help['Lists'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="lists"></a><?php echo $lang_help['List info'] ?></p>
		<p><code>[list][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces list'] ?></span></p>
		<div class="postmsg">
			<ul><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ul>
		</div>
		<p><code>[list=1][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces decimal list'] ?></span></p>
		<div class="postmsg">
			<ol class="decimal"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
		</div>
		<p><code>[list=a][*]<?php echo $lang_help['List text 1'] ?>[/*][*]<?php echo $lang_help['List text 2'] ?>[/*][*]<?php echo $lang_help['List text 3'] ?>[/*][/list]</code> <span><?php echo $lang_help['produces alpha list'] ?></span></p>
		<div class="postmsg">
			<ol class="alpha"><li><?php echo $lang_help['List text 1'] ?></li><li><?php echo $lang_help['List text 2'] ?></li><li><?php echo $lang_help['List text 3'] ?></li></ol>
		</div>
	</div>
</div>
<h2><span><?php echo $lang_help['Nested tags'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><?php echo $lang_help['Nested tags info'] ?></p>
		<p><code>[b][u]<?php echo $lang_help['Bold, underlined text'] ?>[/u][/b]</code> <?php echo $lang_help['produces'] ?> <strong><span class="bbu"><?php echo $lang_help['Bold, underlined text'] ?></span></strong></p>
	</div>
</div>
<h2><span><?php echo $lang_help['Smilies'] ?></span></h2>
<div class="box">
	<div class="inbox">
		<p><a name="smilies"></a><?php echo $lang_help['Smilies info'] ?></p>
		<div class="postmsg">
<?php

// Display the smiley set
require PUN_ROOT.'include/parser.php';

$smiley_groups = array();

foreach ($smilies as $smiley_text => $smiley_img)
	$smiley_groups[$smiley_img][] = $smiley_text;

foreach ($smiley_groups as $smiley_img => $smiley_texts)
	echo "\t\t\t".'<p><code>'.implode('</code> '.$lang_common['and'].' <code>', $smiley_texts).'</code> <span>'.$lang_help['produces'].'</span> <img src="'.$pun_config['o_base_url'].'/img/smilies/'.$smiley_img.'" width="15" height="15" alt="'.$smiley_texts[0].'" /></p>'."\n";

?>
		</div>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
