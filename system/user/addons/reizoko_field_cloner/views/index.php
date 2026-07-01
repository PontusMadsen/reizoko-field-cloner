<style>
.fc-form { max-width: 800px; }
.fc-section { margin-bottom: 24px; padding: 20px; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; }
.fc-section h3 { margin: 0 0 16px; font-size: 1.1em; font-weight: 600; }
.fc-row { margin-bottom: 16px; }
.fc-row label { display: block; font-weight: 500; margin-bottom: 4px; }
.fc-row .fc-desc { font-size: 0.85em; color: #8f90b6; margin-bottom: 6px; }
.fc-row input[type="text"],
.fc-row select,
.fc-row textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 4px; font-size: 14px; }
.fc-row select[multiple] { height: 160px; }
.fc-radio-group { display: flex; gap: 16px; margin-bottom: 12px; }
.fc-radio-group label { display: flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer; }
.fc-supported { font-size: 0.85em; color: #8f90b6; margin-top: 8px; }
.fc-supported code { background: #f0f0f8; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
.fc-actions { margin-top: 24px; }
.fc-actions button { background: #5D63F1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; }
.fc-actions button:hover { background: #4a50d4; }
.fc-conditional { display: none; }
.fc-conditional.active { display: block; }
</style>

<div class="fc-form">
	<?= ee('CP/Alert')->getAllInlines() ?>
	<?= form_open($base_url) ?>
		<input type="hidden" name="action" value="clone">

		<!-- Source Selection -->
		<div class="fc-section">
			<h3><?= lang('source_type') ?></h3>

			<div class="fc-radio-group">
				<label>
					<input type="radio" name="source_type" value="group" checked onchange="toggleSource(this.value)">
					<?= lang('source_field_group') ?>
				</label>
				<label>
					<input type="radio" name="source_type" value="individual" onchange="toggleSource(this.value)">
					<?= lang('source_individual') ?>
				</label>
			</div>

			<div id="source-group" class="fc-conditional active">
				<div class="fc-row">
					<label><?= lang('select_field_group') ?></label>
					<select name="field_group_id">
						<option value="">—</option>
						<?php foreach ($field_groups as $group): ?>
						<option value="<?= $group->group_id ?>"><?= htmlspecialchars($group->group_name, ENT_QUOTES, 'UTF-8') ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div id="source-individual" class="fc-conditional">
				<div class="fc-row">
					<label><?= lang('select_fields') ?></label>
					<select name="field_ids[]" multiple>
						<?php foreach ($fields as $field): ?>
						<?php $supported = in_array($field->field_type, $supported_types); ?>
						<option value="<?= $field->field_id ?>"<?php if ( ! $supported): ?> disabled style="color:#aaa"<?php endif; ?>><?= htmlspecialchars($field->field_label, ENT_QUOTES, 'UTF-8') ?> (<?= $field->field_type ?>)<?php if ( ! $supported): ?> — not supported<?php endif; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="fc-supported">
				<?= lang('supported_types') ?>:
				<?php foreach ($supported_types as $type): ?><code><?= $type ?></code> <?php endforeach; ?>
				<br><?= lang('unsupported_warning') ?>
			</div>
		</div>

		<!-- Affix Settings -->
		<div class="fc-section">
			<h3><?= lang('affix_settings') ?></h3>

			<div class="fc-row">
				<label><?= lang('affix_position') ?></label>
				<div class="fc-radio-group">
					<label>
						<input type="radio" name="affix_position" value="suffix" checked>
						<?= lang('affix_suffix') ?>
					</label>
					<label>
						<input type="radio" name="affix_position" value="prefix">
						<?= lang('affix_prefix') ?>
					</label>
				</div>
			</div>

			<div class="fc-row">
				<label><?= lang('language_codes') ?></label>
				<div class="fc-desc"><?= lang('language_codes_desc') ?></div>
				<input type="text" name="language_codes" placeholder="en, se, de, ja" value="">
			</div>
		</div>

		<!-- Target Channels -->
		<div class="fc-section">
			<h3><?= lang('target_channels') ?></h3>
			<div class="fc-desc"><?= lang('target_channels_desc') ?></div>

			<?php if (count($channels) > 0): ?>
			<div class="fc-row">
				<select name="target_channels[]" multiple>
					<?php foreach ($channels as $channel): ?>
					<option value="<?= $channel->channel_id ?>"><?= htmlspecialchars($channel->channel_title, ENT_QUOTES, 'UTF-8') ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php else: ?>
			<p><?= lang('no_channels') ?></p>
			<?php endif; ?>
		</div>

		<!-- Submit -->
		<div class="fc-actions">
			<button type="submit"><?= lang('clone_btn') ?></button>
		</div>
	<?= form_close() ?>
</div>

<script>
function toggleSource(type) {
	document.getElementById('source-group').classList.toggle('active', type === 'group');
	document.getElementById('source-individual').classList.toggle('active', type === 'individual');
}
</script>
