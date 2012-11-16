@layout('layout.main')

@section('main')
<?php $currentItem = 'Privacy'; ?>

<div id="profile" class="block2col">
	@include('user.profile.menu')
	<div class="blockform">
		<h2><span>Privacy</span></h2>
		<div class="box">
			<form id="profile6" method="post" action="profile.php?section=privacy&amp;id=3">
				<div class="inform">
					<fieldset>
						<legend>Set your privacy options</legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1">
							<p>Select whether you want your email address to be viewable to other users or not and if you want other users to be able to send you email via the forum (form email) or not.</p>
							<div class="rbox">
								<label><input type="radio" name="form[email_setting]" value="0">Display your email address.<br></label>
								<label><input type="radio" name="form[email_setting]" value="1" checked="checked">Hide your email address but allow form email.<br></label>
								<label><input type="radio" name="form[email_setting]" value="2">Hide your email address and disallow form email.<br></label>
							</div>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Set your subscription options</legend>
						<div class="infldset">
							<div class="rbox">
								<label><input type="checkbox" name="form[notify_with_post]" value="1">Include a plain text version of new posts in subscription notification emails.<br></label>
								<label><input type="checkbox" name="form[auto_notify]" value="1">Automatically subscribe to every topic you post in.<br></label>
							</div>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="Submit"> When you update your profile, you will be redirected back to this page.</p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
@endsection