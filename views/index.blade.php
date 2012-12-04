@extends('layout.main')

@section('main')

{{-- TODO: Escape all the variables!!! --}}
<ul>
@foreach ($categories as $cat_info)
<?php $category = $cat_info['category']; ?>
	<li>
		{{ $category->cat_name }}
		<ul>
@foreach ($cat_info['forums'] as $forum)
			<li>
				<a href="{{ route('viewforum', $forum) }}">{{ $forum->forum_name }}</a>
				<em>{{ $forum->forum_desc }}</em>
				<ul>
					<li>{{ $forum->numTopics() }} topics</li>
					<li>{{ $forum->numPosts() }} posts</li>
				</ul>
			</li>
@endforeach
		</ul>
	</li>
@endforeach
</ul>

@stop
