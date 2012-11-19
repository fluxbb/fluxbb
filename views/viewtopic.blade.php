@extends('layout.main')

@section('main')

<a href="{{ url('reply', $topic) }}">{{ t('topic.post_reply') }}</a>

<?php $post_count = 0; ?>

<!-- TODO: Maybe use "render_each" here? (What about counting?) -->
@foreach ($posts as $post)
<?php

$post_count++;
$post_classes = 'row';
if ($post->id == $topic->first_post_id) $post_classes .= ' firstpost';
if ($post_count == 1) $post_classes .= ' blockpost1';

?>
<div id="p{{ $post->id }}">
	<h2><span class="conr">#{{ $start_from + $post_count }}</span> <a href="{{ url('viewpost', $post) }}#p{{ $post->id }}">{{ ($post->posted) }}</a></h2>{{-- TODO: format_time for posted --}}
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
		<dd>{{ t('topic.from', array('name' => ($post->poster->location))) }}</dd>{{-- TODO: Escape location --}}
	@endif
		<dd>{{ t('topic.registered', array('time' => ($post->poster->registered))) }}</dd>{{-- TODO: format_time for registered --}}
		<dd>{{ t('topic.posts', array('count' => ($post->poster->num_posts))) }}</dd>{{-- TODO: number_format --}}
		<dd><a href="get_host_for_pid" title="{{ $post->poster->ip }}">{{ t('topic.ip_address_logged') }}</a></dd>
	@if ($post->poster->hasAdminNote())
		<dd>{{ t('topic.note') }} <strong>{{ ($post->poster->admin_note) }}</strong></dd>{{-- TODO: Escape --}}
	@endif

		<dd class="usercontacts">
			<span class="email"><a href="mailto:{{ $post->poster_email }}">{{ t('common.email') }}</a></span>
			<span class="email"><a href="{{ url('email', $post->poster) }}">{{ t('common.email') }}</a></span>
	@if ($post->poster->hasUrl())
			<span class="website"><a href="{{ e($post->poster->url) }}">{{ t('topic.website') }}</a></span>
	@endif
		</dd>

	</dl>

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

@if (!$post->poster->isGuest())
	@if ($post->poster->isOnline())
	<p><strong>{{ t('topic.online') }}</strong></p>
	@else
	<p><span>{{ t('topic.offline') }}</span></p>
	@endif
@endif

@if (true)
	<ul>
		<!-- TODO: Only show these if appropriate -->
		<li><a href="{{ url('post_report', $post) }}">{{ t('topic.report') }}</a></li>
		<li><a href="{{ url('post_delete', $post) }}">{{ t('topic.delete') }}</a></li>
		<li><a href="{{ url('post_edit', $post) }}">{{ t('topic.edit') }}</a></li>
		<li><a href="{{ url('post_quote', $post) }}">{{ t('topic.quote') }}</a></li>
	</ul>
@endif

@endforeach

<a href="{{ url('reply', $topic) }}">{{ t('topic.post_reply') }}</a>

@stop
