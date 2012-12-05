@extends('layout.main')

@section('main')
<div id="viewprofile" class="block">
	<h2><span>Profile</span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<fieldset>
				<legend>Personal</legend>
					<div class="infldset">
						<dl>
							<dt>Username</dt>
							<dd>{{$user->username}}</dd>
							@if (!empty($user->title))
								<dt>Title</dt>
								<dd>{{$user->title}}</dd>
							@endif
							@if (!empty($user->realname))
								<dt>Real name</dt>
								<dd>{{$user->realname}}</dd>
							@endif
							@if (!empty($user->location))
								<dt>Location</dt>
								<dd>{{$user->location}}</dd>
							@endif							
							@if (!empty($user->url))
								<dt>Website</dt>
								<dd><span class="website"><a href="adf">{{$user->url}}</a></span></dd>
							@endif
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			@if (!empty($user->signature))
			<div class="inform">
				<fieldset>
				<legend>Personality</legend>
					<div class="infldset">
						<dl>
							<dt>Signature</dt>
							<dd><div class="postsignature postmsg"><p>{{$user->signature}}</p></div></dd>
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			@endif
			<div class="inform">
				<fieldset>
				<legend>User activity</legend>
					<div class="infldset">
						<dl>
						
							<dt>Posts</dt>
							<dd>{{$user->num_posts}} - <a href="#">Show all topics</a> - <a href="#">Show all posts</a></dd>
							<dt>Last post</dt>
							<dd><?php echo ($user->last_post) ?></dd>{{-- TODO: format_time --}}
							<dt>Registered</dt>
							<dd><?php echo ($user->registered) ?></dd>{{-- TODO: format_time --}}
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>

		</div>
	</div>
</div>
@stop