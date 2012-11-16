<?php
	$presenter = new Illuminate\Pagination\BootstrapPresenter($paginator);
?>

@if ($paginator->getLastPage() > 1)
	<div class="pagination">
		<ul>
			{{ $presenter->render() }}
		</ul>
	</div>
@endif
