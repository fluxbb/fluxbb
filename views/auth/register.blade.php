@extends('layout.main')

@section('main')
<div id="regform" class="blockform">
	<h2><span>{{ t('register.register') }}</span></h2>
	<div class="box">
		<form id="register" method="post" action="{{ url('register') }}" onsubmit="this.register.disabled=true;if(process_form(this)){return true;}else{this.register.disabled=false;return false;}">
			<div class="inform">
				<div class="forminfo">
					<h3>{{ t('common.important') }}</h3>
					<p>{{ t('register.desc1') }}</p>
					<p>{{ t('register.desc2') }}</p>
				</div>
				<fieldset>
					<legend>{{ t('register.legend_username') }}</legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						{{-- TODO: Repopulate this with old values on errors --}}
						<label class="required">
							<strong>{{ t('common.username') }} <span>{{ t('common.required') }}</span></strong><br />
							<input type="text" name="user" size="25" maxlength="25" /><br />
						</label>
					</div>
				</fieldset>
			</div>
@if (FluxBB\Models\Config::disabled('o_regs_verify'))
			<div class="inform">
				<fieldset>
					<legend>{{ t('register.legend_pass') }}</legend>
					<div class="infldset">
						<label class="conl required">
							<strong>{{ t('common.password') }} <span>{{ t('common.required') }}</span></strong><br />
							<input type="password" name="password" size="16" /><br />
						</label>
						<label class="conl required">
							<strong>{{ t('prof_reg.confirm_pass') }} <span>{{ t('common.required') }}</span></strong><br />
							<input type="password" name="password_confirmation" size="16" /><br />
						</label>
						<p class="clearb">{{ t('register.info_pass') }}</p>
					</div>
				</fieldset>
			</div>
@endif
			<div class="inform">
				<fieldset>
@if (FluxBB\Models\Config::enabled('o_regs_verify'))
					<legend>{{ t('prof_reg.legend_email2') }}</legend>
@else
					<legend>{{ t('prof_reg.legend_email') }}</legend>
@endif
					<div class="infldset">
@if (FluxBB\Models\Config::enabled('o_regs_verify'))
					<p>{{ t('register.info_email') }}</p>
@endif
						<label class="required">
							<strong>{{ t('common.email') }} <span>{{ t('common.required') }}</span></strong><br />
							<input type="email" name="email" size="50" maxlength="80" /><br />{{-- TODO: Escape old input (see above, too) --}}
						</label>
@if (FluxBB\Models\Config::enabled('o_regs_verify'))
						<label class="required">
							<strong>{{ t('register.confirm_email') }} <span>{{ t('common.required') }}</span></strong><br />
							<input type="email" name="email_confirmation" size="50" maxlength="80" /><br />
						</label>
@endif
					</div>
				</fieldset>
			</div>

@if (FluxBB\Models\Config::enabled('o_rules'))
			<div class="inform">
				<fieldset>
					<legend>{{ t('register.legend_pass') }}</legend>
					<div class="infldset">
						<label class="required">
							<strong>{{ t('register.rules_legend') }} </strong><br /><br />{{ FluxBB\Models\Config::get('o_rules_message') }}
							<p class="checkbox"><input type="checkbox" name="rules" value="1" />{{ t('register.agree') }}<span><strong>{{ t('common.required') }}</strong></p></span>
						</label>
					</div>
				</fieldset>
			</div>
@endif
			<p class="buttons"><input type="submit" name="register" value="{{ t('register.register') }}" /></p>
		</form>
	</div>
</div>
@stop
