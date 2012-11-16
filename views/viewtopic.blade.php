@extends('layout.main')

@section('main')

<div class="linkst">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="postlink conr"><a href="{{ url('reply', $topic) }}">{{ t('topic.post_reply') }}</a></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<?php $post_count = 0; ?>

<!-- TODO: Maybe use "render_each" here? (What about counting?) -->
@foreach ($posts as $post)
<?php

$post_count++;
$post_classes = 'row';
if ($post->id == $topic->first_post_id) $post_classes .= ' firstpost';
if ($post_count == 1) $post_classes .= ' blockpost1';

?>
<div id="p{{ $post->id }}" class="blockpost {{ $post_classes }}">
	<h2><span><span class="conr">#{{ $start_from + $post_count }}</span> <a href="{{ url('viewpost', $post) }}#p{{ $post->id }}">{{ ($post->posted) }}</a></span></h2>{{-- TODO: format_time for posted --}}
	<div class="box">
		<div class="inbox">
			<div class="postbody">
				<div class="postleft">
					<dl>
	@if (fluxbb\Models\User::current()->canViewUsers())
						<dt><strong><a href="{{ url('profile', $post->poster) }}">{{ ($post->poster_name) }}</a></strong></dt>{{-- TODO: Escape username --}}
	@else
						<dt><strong>{{ ($post->poster->username) }}</strong></dt><!-- TODO: linkify if logged in and g_view_users is enabled for this group and escape username! -->
	@endif
						<dd class="usertitle"><strong>{{ ($post->poster->title()) }}</strong></dd>{{-- TODO: Escape title --}}
	@if ($post->poster->hasAvatar())
						<dd class="postavatar">{{ ($post->poster->avatar) }}</dd>{{-- TODO: HTML::avatar() --}}
	@endif
	@if ($post->poster->hasLocation()) <!-- TODO: and if user is allowed to view this (logged in and show_user_info -->
						<dd><span>{{ t('topic.from', array('name' => ($post->poster->location))) }}</span></dd>{{-- TODO: Escape location --}}
	@endif
						<dd><span>{{ t('topic.registered', array('time' => ($post->poster->registered))) }}</span></dd>{{-- TODO: format_time for registered --}}
						<dd><span>{{ t('topic.posts', array('count' => ($post->poster->num_posts))) }}</span></dd>{{-- TODO: number_format --}}
						<dd><span><a href="get_host_for_pid" title="{{ $post->poster->ip }}">{{ t('topic.ip_address_logged') }}</a></span></dd>
	@if ($post->poster->hasAdminNote())
						<dd><span>{{ t('topic.note') }} <strong>{{ ($post->poster->admin_note) }}</strong></span></dd>{{-- TODO: Escape --}}
	@endif

						<dd class="usercontacts">
							<span class="email"><a href="mailto:{{ $post->poster_email }}">{{ t('common.email') }}</a></span>
							<span class="email"><a href="{{ url('email', $post->poster) }}">{{ t('common.email') }}</a></span>
	@if ($post->poster->hasUrl())
							<span class="website"><a href="{{ e($post->poster->url) }}">{{ t('topic.website') }}</a></span>
	@endif
						</dd>

					</dl>
				</div>
				<div class="postright">
					<h3><?php if ($post->id != $topic->first_post_id) echo t('topic.re').' '; ?>{{ ($topic->subject) }}</h3>{{-- TODO: Escape subject --}}
					<div class="postmsg">
						{{ $post->message() }}
	@if ($post->wasEdited())
						<p class="postedit"><em>{{ t('topic.last_edit').' '.($post->edited_by).' ('.($post->edited) }})</em></p>{{-- TODO: Escape edited_by, format_time for edited --}}
	@endif
					</div>
	@if ($post->poster->hasSignature())
					<div class="postsignature postmsg"><hr />{{ $post->poster->signature() }}</div>
	@endif
				</div>
			</div>
		</div>
		<div class="inbox">
			<div class="postfoot clearb">
				<div class="postfootleft">
	@if (!$post->poster->isGuest())
		@if ($post->poster->isOnline())
					<p><strong>{{ t('topic.online') }}</strong></p>
		@else
					<p><span>{{ t('topic.offline') }}</span></p>
		@endif
	@endif
				</div>
	@if (true)
				<div class="postfootright">
					<ul>
						<!-- TODO: Only show these if appropriate -->
						<li class="postreport"><span><a href="{{ url('post_report', $post) }}">{{ t('topic.report') }}</a></span></li>
						<li class="postdelete"><span><a href="{{ url('post_delete', $post) }}">{{ t('topic.delete') }}</a></span></li>
						<li class="postedit"><span><a href="{{ url('post_edit', $post) }}">{{ t('topic.edit') }}</a></span></li>
						<li class="postquote"><span><a href="{{ url('post_quote', $post) }}">{{ t('topic.quote') }}</a></span></li>
					</ul>
				</div>
	@endif
			</div>
		</div>
	</div>
</div>
@endforeach

<div class="postlinksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="postlink conr"><a href="{{ url('reply', $topic) }}">{{ t('topic.post_reply') }}</a></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

@stop
