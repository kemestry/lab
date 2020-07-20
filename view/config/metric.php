<?php
/**
 *
 */

use \App\Lab_Metric;

?>

<style>
.table-metric label {
	display: block;
	margin: 0;
}
.table-metric td {
	font-size: 120%;
}
</style>

<div class="container mt-2">

<h1>Config :: Metrics</h1>
<p>Configure which metrics are used with which product classes.</p>

<table class="table table-sm table-bordered table-metric">
<?php
foreach ($this->data['metric_list'] as $m) {

	if ($m['type'] != $type_x) {
	?>
		<tr class="thead-dark">
			<th colspan="7"><?= h($m['type']) ?></th>
		</tr>
		<tr class="thead-dark">
			<th>Name</th>
			<th>LOD</th>
			<th>LOQ</th>
			<th>Max</th>
			<th colspan="3">Products</th>
		</tr>
	<?php
	}

?>
	<tr>
		<td><?= h($m['name']) ?></td>
		<td class="r">
			<div class="input-group input-group-sm" style="width: 8em;">
				<input class="form-control form-control-sm" name="<?= sprintf('%s-lod', $m['id']) ?>">
				<div class="input-group-append">
					<div class="input-group-text">ppm</div>
				</div>
			</div>
		</td>
		<td class="r">
			<div class="input-group input-group-sm" style="width: 8em;">
				<input class="form-control form-control-sm" name="<?= sprintf('%s-loq', $m['id']) ?>">
				<div class="input-group-append">
					<div class="input-group-text">ppm</div>
				</div>
			</div>
		</td>
		<td class="r">
			<div class="input-group input-group-sm" style="width: 8em;">
				<input class="form-control form-control-sm" name="<?= sprintf('%s-max', $m['id']) ?>">
				<div class="input-group-append">
					<div class="input-group-text">ppm</div>
				</div>
			</div>
		</td>
		<td><label><input <?= ($m['flag'] & Lab_Metric::FLAG_FLOWER) ? 'checked' : null ?> type="checkbox"> Flower</label></td>
		<td><label><input <?= ($m['flag'] & Lab_Metric::FLAG_EDIBLE) ? 'checked' : null ?> type="checkbox"> Edible</label></td>
		<td><label><input <?= ($m['flag'] & Lab_Metric::FLAG_EXTRACT) ? 'checked' : null ?> type="checkbox"> Extract</label></td>
	</tr>

	<?php
	$type_x = $m['type'];
	?>

<?php
}
?>
</table>
</div>
