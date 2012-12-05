@extends('layout.main')

@section('main')
<?php $currentItem = 'Essentials'; ?>

<div id="profile" class="block2col">
	@include('user.profile.menu')
	<div class="blockform">
		<h2><span>Essentials</span></h2>
		<div class="box">
			{{ Form::open(URL::to_action('user@profile', array($user->id, 'essentials')), 'PUT', array('id' => 'profile', 'onsubmit' => 'return process_form(this)')) }}
				<div class="inform">
					<fieldset>
						<legend>Enter your username and password</legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1">
							<label class="required"><strong>Username <span>(Required)</span></strong><br>
							@if ($user->is_admin())
							{{ Form::text('username', $user->username, array('size' =>  "25", 'maxlength' => "25")) }}
							@else
							{{ $user->username }}
							@endif
							<br></label>
							<p class="actions"><span><a href="#">Change password</a></span></p>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Enter a valid email address</legend>
						<div class="infldset">
							<label class="required"><strong>Email <span>(Required)</span></strong><br>{{ Form::text('email', $user->email, array('size' => "40", "maxlength" => "80")) }}<br></label><p><span class="email"><a href="misc.php?email=2">Send email</a></span></p>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Set your localisation options</legend>
						<div class="infldset">
							<p>For the forum to display times correctly you must select your local time zone. If Daylight Savings Time is in effect you should also check the option provided which will advance times by 1 hour.</p>
							<label>Time zone
							<br><select name="timezone">
								<option value="-12">(UTC-12:00) International Date Line West</option>
								<option value="-11">(UTC-11:00) Niue, Samoa</option>
								<option value="-10">(UTC-10:00) Hawaii-Aleutian, Cook Island</option>
								<option value="-9.5">(UTC-09:30) Marquesas Islands</option>
								<option value="-9">(UTC-09:00) Alaska, Gambier Island</option>
								<option value="-8.5">(UTC-08:30) Pitcairn Islands</option>
								<option value="-8">(UTC-08:00) Pacific</option>
								<option value="-7">(UTC-07:00) Mountain</option>
								<option value="-6">(UTC-06:00) Central</option>
								<option value="-5">(UTC-05:00) Eastern</option>
								<option value="-4">(UTC-04:00) Atlantic</option>
								<option value="-3.5">(UTC-03:30) Newfoundland</option>
								<option value="-3">(UTC-03:00) Amazon, Central Greenland</option>
								<option value="-2">(UTC-02:00) Mid-Atlantic</option>
								<option value="-1">(UTC-01:00) Azores, Cape Verde, Eastern Greenland</option>
								<option value="0" selected="selected">(UTC) Western European, Greenwich</option>
								<option value="1">(UTC+01:00) Central European, West African</option>
								<option value="2">(UTC+02:00) Eastern European, Central African</option>
								<option value="3">(UTC+03:00) Eastern African</option>
								<option value="3.5">(UTC+03:30) Iran</option>
								<option value="4">(UTC+04:00) Moscow, Gulf, Samara</option>
								<option value="4.5">(UTC+04:30) Afghanistan</option>
								<option value="5">(UTC+05:00) Pakistan</option>
								<option value="5.5">(UTC+05:30) India, Sri Lanka</option>
								<option value="5.75">(UTC+05:45) Nepal</option>
								<option value="6">(UTC+06:00) Bangladesh, Bhutan, Yekaterinburg</option>
								<option value="6.5">(UTC+06:30) Cocos Islands, Myanmar</option>
								<option value="7">(UTC+07:00) Indochina, Novosibirsk</option>
								<option value="8">(UTC+08:00) Greater China, Australian Western, Krasnoyarsk</option>
								<option value="8.75">(UTC+08:45) Southeastern Western Australia</option>
								<option value="9">(UTC+09:00) Japan, Korea, Chita, Irkutsk</option>
								<option value="9.5">(UTC+09:30) Australian Central</option>
								<option value="10">(UTC+10:00) Australian Eastern</option>
								<option value="10.5">(UTC+10:30) Lord Howe</option>
								<option value="11">(UTC+11:00) Solomon Island, Vladivostok</option>
								<option value="11.5">(UTC+11:30) Norfolk Island</option>
								<option value="12">(UTC+12:00) New Zealand, Fiji, Magadan</option>
								<option value="12.75">(UTC+12:45) Chatham Islands</option>
								<option value="13">(UTC+13:00) Tonga, Phoenix Islands, Kamchatka</option>
								<option value="14">(UTC+14:00) Line Islands</option>
							</select>
							<br></label>
							<div class="rbox">
								<label><input type="checkbox" name="dst" value="1">Daylight Savings Time is in effect (advance time by 1 hour).<br></label>
							</div>
							<label>Time format
							<br><select name="time_format">
								<option value="0" selected="selected">{{ date('h:i:s') }} (Default)</option>
								<option value="2">{{date('h:i') }}</option>
								<option value="3">{{date('g:i:s a') }}</option>
								<option value="4">{{date('g:i a') }}</option>
							</select>
							<br></label>
							<label>Date format
							<br><select name="date_format">
								<option value="0" selected="selected">{{ date("Y-m-d") }} (Default)</option>
								<option value="2">{{ date("Y-d-m") }}</option>
								<option value="3">{{ date("d-m-Y") }}</option>
								<option value="4">{{ date("m-d-Y") }}</option>
								<option value="5">{{ date("M j Y") }}</option>
								<option value="6">{{ date("jS M Y") }}</option>
							</select>
							<br></label>

						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>User activity</legend>
						<div class="infldset">
							<p>Registered: {{ HTML::format_time($user->registered, true, "Y-m-d") }}</p>
							<p>Last post: {{ HTML::format_time($user->last_post) }}</p>
							<p>Last visit: {{ HTML::format_time($user->last_visit) }}</p>
							<label>Posts: {{ $user->num_posts }}<br></label><p class="actions">
							{{--- TODO: add input field for posts when admin + add links to controller actions --}}
							<a href="search.php?action=show_user_topics&amp;user_id=2">Show all topics</a> - <a href="search.php?action=show_user_posts&amp;user_id=2">Show all posts</a> - <a href="search.php?action=show_subscriptions&amp;user_id=2">Show all subscriptions</a></p>
							@if ($user->is_admin())
							<label>Admin note<br>
							{{ Form::text('admin_note', $user->admin_note, array('size' => '30', 'maxlength' => '30', 'id' => "admin_note")) }}<br></label>
							@endif
						</div>
					</fieldset>
				</div>
				<p class="buttons">{{ Form::submit('Submit', array('name' => 'update')) }} When you update your profile, you will be redirected back to this page.</p>
			{{ Form::close() }}
		</div>
	</div>
	<div class="clearer"></div>
</div>
@stop