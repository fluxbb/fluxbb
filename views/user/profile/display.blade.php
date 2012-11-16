@layout('layout.main')

@section('main')
<?php $currentItem = 'Display'; ?>

<div id="profile" class="block2col">
	@include('user.profile.menu')
	<div class="blockform">
		<h2><span>Display</span></h2>
		<div class="box">
			{{ Form::open(URL::to_action('user@profile', array($user->id, 'display')), 'PUT', array('id' => 'profile', 'onsubmit' => 'return process_form(this)')) }}
				<div class="inform">
					<fieldset>
						<legend>Select your preferred style</legend>
						<div class="infldset">
							<label>Styles<br>
							<select name="style">
								<option value="Air" selected="selected">Air</option>
								<option value="Cobalt">Cobalt</option>
								<option value="Earth">Earth</option>
								<option value="Fire">Fire</option>
								<option value="Lithium">Lithium</option>
								<option value="Mercury">Mercury</option>
								<option value="Oxygen">Oxygen</option>
								<option value="Radium">Radium</option>
								<option value="Sulfur">Sulfur</option>
								<option value="Technetium">Technetium</option>
							</select>
							<br></label>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Set your options for viewing posts</legend>
						<div class="infldset">
							<p>If you are on a slow connection, disabling these options, particularly showing images in posts and signatures, will make pages load faster.</p>
							<div class="rbox">
								<label><input type="checkbox" name="show_smilies" value="1" checked="checked">Show smilies as graphic icons.<br></label>
								<label><input type="checkbox" name="show_sig" value="1" checked="checked">Show user signatures.<br></label>
								<label><input type="checkbox" name="show_avatars" value="1" checked="checked">Show user avatars in posts.<br></label>
								<label><input type="checkbox" name="show_img" value="1" checked="checked">Show images in posts.<br></label>
							</div>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Enter your pagination options</legend>
						<div class="infldset">
							<label class="conl">Topics<br><input type="text" name="disp_topics" value="" size="6" maxlength="3"><br></label>
							<label class="conl">Posts<br><input type="text" name="disp_posts" value="" size="6" maxlength="3"><br></label>
							<p class="clearb">Enter the number of topics and posts you wish to view on each page. Leave blank to use forum default.</p>
						</div>
					</fieldset>
				</div>
				<p class="buttons">{{ Form::submit('Submit', array('name' => 'update')) }} When you update your profile, you will be redirected back to this page.</p>
			{{ Form::close() }}
		</div>
	</div>
	<div class="clearer"></div>
</div>
@endsection