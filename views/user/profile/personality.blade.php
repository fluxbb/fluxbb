@extends('layout.main')

@section('main')
<?php $currentItem = 'Personality'; ?>

<div id="profile" class="block2col">
	@include('user.profile.menu')
	<div class="blockform">
		<h2><span>Personality</span></h2>
		<div class="box">
			<form id="profile4" method="post" action="{{ URL::to_action('user@profile', array($user->id, 'personality')) }}">
				<div class="inform">
					<fieldset id="profileavatar">
						<legend>Set your avatar display options</legend>
						<div class="infldset">
							<p>An avatar is a small image that will be displayed with all your posts. You can upload an avatar by clicking the link below.</p>
							<p class="clearb actions"><span><a href="profile.php?action=upload_avatar&amp;id=3">Upload avatar</a></span></p>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Compose your signature</legend>
						<div class="infldset">
							<p>A signature is a small piece of text that is attached to your posts. In it, you can enter just about anything you like. Perhaps you would like to enter your favourite quote or your star sign. It's up to you! In your signature you can use BBCode if it is allowed in this particular forum. You can see the features that are allowed/enabled listed below whenever you edit your signature.</p>
							<div class="txtarea">
								<label>Max length: 400 characters / Max lines: 4<br>
								<textarea name="signature" rows="4" cols="65"></textarea><br></label>
							</div>
							<ul class="bblinks">
								<?php //TODO: all these links ?>
								<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;">BBCode:</a> on</span></li>
								<li><span><a href="help.php#url" onclick="window.open(this.href); return false;">[url] tag:</a> on</span>
								</li><li><span><a href="help.php#img" onclick="window.open(this.href); return false;">[img] tag:</a> off</span></li>
								<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;">Smilies:</a> on</span></li>
							</ul>
							<p>No signature currently stored in profile.</p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="Submit"> When you update your profile, you will be redirected back to this page.</p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
@stop