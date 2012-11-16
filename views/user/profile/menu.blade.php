<?php
	$menuItems=array("Essentials", "Personal", "Messaging", "Personality", "Display", "Privacy");
	//TODO: if user is admin add administration to array
?>
<div class="blockmenu">
	<h2><span>Profile menu</span></h2>
	<div class="box">
		<div class="inbox">
			<ul>
				@foreach($menuItems as $menuItem)
					@if($menuItem == Str::title($currentItem)) <?php // with $currentItem as passed variable for the menu part which the user is viewing now ?>
						<li class="isactive">{{ HTML::link_to_route('profile', $menuItem, array($user->id, Str::lower($menuItem))) }}</li>
					@else
						<li>{{ HTML::link_to_route('profile', $menuItem, array($user->id, Str::lower($menuItem))) }}</li>
					@endif
				@endforeach
			</ul>
		</div>
	</div>
</div>