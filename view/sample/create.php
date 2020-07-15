<?php
/**
 * $this is the Controller
 */

?>

<form method="post">
<div class="container mt-4">

	<h1>Sample :: Create</h1>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">Origin License:</div>
			</div>
			<input autofocus name="license_origin" class="form-control license-autocomplete">
			<div class="input-group-append">
				<button class="btn btn-outline-secondary" type="button"><i class="fas fa-sync"></i></button>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">Origin Identifier:</div>
			</div>
			<input name="lot_id_origin" class="form-control">
		</div>
	</div>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">Product Type:</div>
			</div>
			<select class="form-control" name="product-type">
				<?php foreach ($this->data['product_type'] as $k => $v) { ?>
					<option value="<?= h($k) ?>"><?= h($v) ?></option>
				<?php
				}
				?>
			</select>
		</div>
	</div>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">Product:</div>
			</div>
			<input name="product" class="form-control product-autocomplete">
			<div class="input-group-append">
				<button class="btn btn-outline-secondary" type="button"><i class="fas fa-sync"></i></button>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">Strain:</div>
			</div>
			<input name="strain" class="form-control strain-autocomplete">
			<div class="input-group-append">
				<button class="btn btn-outline-secondary" type="button"><i class="fas fa-sync"></i></button>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="input-group">
			<div class="input-group-prepend">
				<div class="input-group-text">QTY:</div>
			</div>
			<input name="qty" class="form-control r" type="number" step="0.0001">
		</div>
	</div>

	<div>
		<button class="btn btn-outline-primary" name="a" value="save"><i class="fas fa-save"></i> Save</button>
	</div>

</div>
</form>

<?php
ob_start();
?>
<script>
$(function() {

	$('.license-autocomplete').autocomplete({
		source: 'https://directory.openthc.com/api/autocomplete/license',
	});

	$('.product-autocomplete').autocomplete({
		source: 'https://pdb.openthc.com/api/autocomplete',
	});

	$('.strain-autocomplete').autocomplete({
		source: 'https://sdb.openthc.org/api/autocomplete',
	});

});
</script>
<?php
$code = ob_get_clean();

$this->data['foot_script'] = $code;
