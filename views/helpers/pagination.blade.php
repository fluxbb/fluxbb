<?php
	$presenter = new Illuminate\Pagination\BootstrapPresenter($paginator);
?>

<div class="pagination">
	<ul>
		{{ $presenter->render() }}
	</ul>
</div>
