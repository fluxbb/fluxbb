@extends('layout.main')

@section('main')
<h2>{{ t('common.login') }}</h2>
<form id="login" method="post" action="{{ url('login') }}" onsubmit="return process_form(this)">
	<fieldset>
		<legend>{{ t('login.login_legend') }}</legend>
		<input type="hidden" name="form_sent" value="1" />
		<input type="hidden" name="redirect_url" value="{{ isset($redirect_url) ? $redirect_url : '' }}" />{{-- TODO: Escape value --}}
		<label class="conl required"><strong>{{ t('common.username') }} <span>{{ t('common.required') }}</span></strong><br /><input type="text" name="req_username" size="25" maxlength="25" tabindex="1" /><br /></label>
		<label class="conl required"><strong>{{ t('common.password') }} <span>{{ t('common.required') }}</span></strong><br /><input type="password" name="req_password" size="25" tabindex="2" /><br /></label>

		<div class="rbox clearb">
			<label><input type="checkbox" name="save_pass" value="1" tabindex="3" />{{ t('login.remember_me') }}<br /></label>
		</div>

		<p class="clearb">{{ t('login.info') }}</p>
		<p class="actions"><span><a href="{{ url('register') }}" tabindex="5">{{ t('login.not_registered') }}</a></span> <span><a href="{{ url('forgot_password') }}" tabindex="6">{{ t('login.forgotten_pass') }}</a></span></p>
	</fieldset>
	<p class="buttons"><input type="submit" name="login" value="{{ t('common.login') }}" tabindex="4" /></p>
</form>
@stop
