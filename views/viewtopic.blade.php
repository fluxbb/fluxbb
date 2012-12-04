@extends('layout.main')

@section('main')

<a href="{{ route('reply', $topic) }}">{{ t('topic.post_reply') }}</a>

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
	<h2><span class="conr">#{{ $start_from + $post_count }}</span> <a href="{{ route('viewpost', $post) }}#p{{ $post->id }}">{{ ($post->posted) }}</a></h2>{{-- TODO: format_time for posted --}}
	<dl>
	@if (fluxbb\Models\User::current()->canViewUsers())
		<dt><strong><a href="{{ route('profile', $post->author) }}">{{ ($post->author->username) }}</a></strong></dt>{{-- TODO: Escape username --}}
	@else
		<dt><strong>{{ ($post->author->username) }}</strong></dt><!-- TODO: linkify if logged in and g_view_users is enabled for this group and escape username! -->
	@endif
		<dd class="usertitle"><strong>{{ ($post->author->title()) }}</strong></dd>{{-- TODO: Escape title --}}
	@if ($post->author->hasAvatar())
		<dd class="postavatar">{{ ($post->author->avatar) }}</dd>{{-- TODO: HTML::avatar() --}}
	@endif
	@if ($post->author->hasLocation()) <!-- TODO: and if user is allowed to view this (logged in and show_user_info -->
		<dd>{{ t('topic.from', array('name' => ($post->author->location))) }}</dd>{{-- TODO: Escape location --}}
	@endif
		<dd>{{ t('topic.registered', array('time' => ($post->author->registered))) }}</dd>{{-- TODO: format_time for registered --}}
		<dd>{{ t('topic.posts', array('count' => ($post->author->num_posts))) }}</dd>{{-- TODO: number_format --}}
		<dd><a href="get_host_for_pid" title="{{ $post->author->ip }}">{{ t('topic.ip_address_logged') }}</a></dd>
	@if ($post->author->hasAdminNote())
		<dd>{{ t('topic.note') }} <strong>{{ ($post->author->admin_note) }}</strong></dd>{{-- TODO: Escape --}}
	@endif

		<dd class="usercontacts">
			<span class="email"><a href="mailto:{{ $post->author->email }}">{{ t('common.email') }}</a></span>
			<span class="email"><a href="{{ route('email', $post->author) }}">{{ t('common.email') }}</a></span>
	@if ($post->author->hasUrl())
			<span class="website"><a href="{{ e($post->author->url) }}">{{ t('topic.website') }}</a></span>
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
@if ($post->author->hasSignature())
	<div class="postsignature postmsg"><hr />{{ $post->author->signature() }}</div>
@endif

@if (!$post->author->guest())
	@if ($post->author->isOnline())
	<p><strong>{{ t('topic.online') }}</strong></p>
	@else
	<p><span>{{ t('topic.offline') }}</span></p>
	@endif
@endif

@if (true)
	<ul>
		<!-- TODO: Only show these if appropriate -->
		<li><a href="{{ route('post_report', $post) }}">{{ t('topic.report') }}</a></li>
		<li><a href="{{ route('post_delete', $post) }}">{{ t('topic.delete') }}</a></li>
		<li><a href="{{ route('post_edit', $post) }}">{{ t('topic.edit') }}</a></li>
		<li><a href="{{ route('post_quote', $post) }}">{{ t('topic.quote') }}</a></li>
	</ul>
@endif

@endforeach

<a href="{{ route('reply', $topic) }}">{{ t('topic.post_reply') }}</a>

@stop
