@extends('layout.main')

@section('main')
<h2><?php echo $action ?></h2>
@if (isset($topic))
<form action="{{ route('reply', $topic) }}" method="POST" id="post">
@else
<form action="{{ route('new_topic', $forum) }}" method="POST" id="post">
@endif
	<fieldset>
		<legend>{{ t('common.write_message_legend') }}</legend>
<?php

$cur_index = 1;

if (!FluxBB\Auth::check())
{
	$email_label = FluxBB\Models\Config::enabled('p_force_guest_email') ? '<strong>'.t('common.email').' <span>'.t('common.required').'</span></strong>' : t('common.email');
	$email_form_name = FluxBB\Models\Config::enabled('p_force_guest_email') ? 'req_email' : 'email';

?>
		<label><strong>{{ t('post.guest_name') }} <span>{{ t('common.required') }}</span></strong><br /><input type="text" name="req_username" size="25" maxlength="25" value="" /><br /></label> {{-- TODO: Escape --}}
		<label class="conl<?php echo FluxBB\Models\Config::enabled('p_force_guest_email') ? ' required' : '' ?>"><?php echo $email_label ?><br /><input type="text" name="{{ $email_form_name }}" size="50" maxlength="80" value=""><br /></label> {{-- TODO: Escape --}}
<?php

}

if (isset($forum)): ?>
		<label class="required"><strong>{{ t('common.subject') }} <span>{{ t('common.required') }}</span></strong><br /><input type="text" name="req_subject" class="longinput" size="80" value="" /><br /></label>{{-- TODO: Escape --}}
<?php endif; ?>						

		<label class="required"><strong>{{ t('common.message') }} <span>{{ t('common.required') }}</span></strong><br /></label>
		<textarea name="req_message" id="req_message" cols="95" rows="20"></textarea><br /></label>{{-- TODO: Escape --}}
		<ul class="bblinks">
			<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;">{{ t('common.bbcode') }}</a> <?php echo FluxBB\Models\Config::enabled('p_message_bbcode') ? t('common.on') : t('common.off'); ?></span></li>
			<li><span><a href="help.php#url" onclick="window.open(this.href); return false;">{{ t('common.url_tag') }}</a> <?php echo FluxBB\Models\Config::enabled('p_message_bbcode') && FluxBB\Models\User::current()->group->g_post_links == '1' ? t('common.on') : t('common.off'); ?></span></li>
			<li><span><a href="help.php#img" onclick="window.open(this.href); return false;">{{ t('common.img_tag') }}</a> <?php echo FluxBB\Models\Config::enabled('p_message_bbcode') && FluxBB\Models\Config::enabled('p_message_img_tag') ? t('common.on') : t('common.off'); ?></span></li>
			<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;">{{ t('common.smilies') }}</a> <?php echo FluxBB\Models\Config::enabled('o_smilies') ? t('common.on') : t('common.off'); ?></span></li>
		</ul>
	</fieldset>
<?php

$checkboxes = array();
if (isset($topic) && $topic->forum->isAdmMod() || isset($forum) && $forum->isAdmMod())
	$checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" tabindex="'.($cur_index++).'" />'.t('common.stick_topic').'<br /></label>';

if (FluxBB\Auth::check())
{
	if (FluxBB\Models\Config::enabled('o_smilies'))
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'" />'.t('post.hide_smilies').'<br /></label>';

	if (FluxBB\Models\Config::enabled('o_topic_subscriptions'))
	{
		$is_subscribed = isset($topic) && $topic->is_user_subscribed();
		$subscr_checked = false;

		// If it's a preview
		//if (Input::has('preview'))
			//$subscr_checked = Input::has('subscribe');
		// If auto subscribed
		/* else */ if (FluxBB\Models\User::current()->auto_notify == '1')
			$subscr_checked = true;
		// If already subscribed to the topic
		else if ($is_subscribed)
			$subscr_checked = true;

		$checkboxes[] = '<label><input type="checkbox" name="subscribe" value="1" tabindex="'.($cur_index++).'"'.($subscr_checked ? ' checked="checked"' : '').' />'.($is_subscribed ? t('post.stay_subscribed') : t('post.subscribe')).'<br /></label>';
	}
}
else if (FluxBB\Models\Config::enabled('o_smilies'))
	$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'" />'.t('post.hide_smilies').'<br /></label>';

?>

@if (!empty($checkboxes))
	<fieldset>
		<legend>{{ t('common.options') }}</legend>
		<?php echo implode("\n\t\t\t\t\t\t\t", $checkboxes)."\n" ?>
	</fieldset>
@endif
	<p class="buttons"><input type="submit" name="submit" value="{{ t('common.submit') }}" tabindex="<?php echo $cur_index++ ?>" accesskey="s" /> <input type="submit" name="preview" value="{{ t('post.preview') }}" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /> <a href="javascript:history.go(-1)">{{ t('common.go_back') }}</a></p>
</form>
@stop
