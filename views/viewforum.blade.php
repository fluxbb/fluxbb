@extends('layout.main')

@section('main')

<div class="linkst">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="postlink conr"><a href="{{ url('new_topic', $forum) }}">{{ t('forum.post_topic') }}</a></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="vf" class="blocktable">
	<h2><span>{{ ($forum->forum_name) }}</span></h2>{{-- TODO: Escape --}}
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col">Topic</th>
					<th class="tc2" scope="col">{{ t('common.replies') }}</th>
					<th class="tc3" scope="col">{{ t('forum.views') }}</th> <!-- TODO: Only show if o_topic_views is enabled -->
					<th class="tcr" scope="col">{{ t('common.last_post') }}</th>
				</tr>
			</thead>
			<tbody>

<?php $topic_count = 0; ?>
@foreach ($topics as $topic)
<?php

$topic_count++;
$icon_type = 'icon';
if (fluxbb\Models\User::current()->isMember() && $topic->last_post > fluxbb\Models\User::current()->last_visit && (!isset($tracked_topics['topics'][$topic->id]) || $tracked_topics['topics'][$topic->id] < $topic->last_post) && (!isset($tracked_topics['forums'][$forum->id]) || $tracked_topics['forums'][$forum->id] < $topic->last_post) && is_null($topic->moved_to))
{
	// TODO: For obvious reasons, this if statement should not be here in the view (in that form)
	$icon_type = 'icon icon-new';
}

?>
				<tr class="row">
					<td class="tcl">
						<div class="{{ $icon_type }}"><div class="nosize">{{ $topic_count + $start_from }}</div></div>{{-- number_format --}}
						<div class="tclcon">
							<div>
								<a href="{{ url('viewtopic', $topic) }}">{{ ($topic->subject) }}</a> <span class="byuser">{{ t('common.by', array('author' => ($topic->poster))) }}</span>{{-- TODO: Escape subject and poster --}}
							</div>
						</div>
					</td>
					<td class="tc2">{{ $topic->numReplies() }}</td>
					<td class="tc3">{{ $topic->numViews() }}</td> <!-- TODO: Only show if o_topic_views is enabled -->
	@if ($topic->wasMoved())
					<td class="tcr">- - -</td>
	@else
					<!-- TODO: Pass $lasT_post instead of $topic to url() -->
					<td class="tcr"><a href="{{ url('viewpost', $topic) }}#p{{ $topic->last_post_id }}">{{ ($topic->last_post) }}</a> <span class="byuser">{{ t('common.by', array('author' => ($topic->last_poster))) }}</span></td>{{-- TODO: Escape author and format_time for last_post --}}
	@endif
				</tr>
@endforeach

			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="postlinksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="postlink conr"><a href="{{ url('new_topic', $forum) }}">{{ t('forum.post_topic') }}</a></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

@stop
