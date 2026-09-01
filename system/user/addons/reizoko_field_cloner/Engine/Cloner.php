<?php

namespace Reizoko\FieldCloner\Engine;

class Cloner
{
	/**
	 * Whether the most recent _clone_single() call reused an existing field
	 */
	private $_last_reused = FALSE;

	/**
	 * Properties to copy from source to clone
	 */
	private static $_field_properties = array(
		'site_id',
		'field_type',
		'field_list_items',
		'field_pre_populate',
		'field_pre_channel_id',
		'field_pre_field_id',
		'field_ta_rows',
		'field_maxl',
		'field_required',
		'field_text_direction',
		'field_search',
		'field_is_hidden',
		'field_fmt',
		'field_show_fmt',
		'field_content_type',
		'field_settings',
		'enable_frontedit',
	);

	/**
	 * Supported field types for v1
	 */
	private static $_supported_types = array(
		'text',
		'textarea',
		'rte',
		'toggle',
		'selectable_buttons',
		'select',
		'date',
		'fluid_field',
		'grid',
		'file',
		'url',
		'email_address',
		'number',
		'colorpicker',
		'relationship',
	);

	/**
	 * Clone fields across language codes
	 *
	 * @param  array  $source_fields   Array of ChannelField models
	 * @param  array  $codes           Language code strings (e.g. ['en', 'se', 'de'])
	 * @param  string $affix_position  'suffix' or 'prefix'
	 * @param  array  $target_channels Channel IDs to assign clones to
	 * @return array  Results with 'created', 'skipped', 'errors' counts
	 */
	public function cloneFields($source_fields, $codes, $affix_position = 'suffix', $target_channels = array(), $source_group_id = 0)
	{
		$created = 0;
		$reused = 0;
		$skipped = 0;
		$errors = array();

		// Get source group info for naming new groups
		$source_group = NULL;
		if ($source_group_id > 0)
		{
			$source_group = ee('Model')->get('ChannelFieldGroup', $source_group_id)->first();
		}

		foreach ($codes as $code)
		{
			// Create a new field group per language code
			$new_group_id = 0;
			if ($source_group)
			{
				$new_group_id = $this->_create_language_group($source_group, $code, $affix_position);
			}

			foreach ($source_fields as $source)
			{
				if ( ! in_array($source->field_type, self::$_supported_types))
				{
					$skipped++;
					$errors[] = sprintf('%s: unsupported type "%s"', $source->field_name, $source->field_type);
					continue;
				}

				$result = $this->_clone_single($source, $code, $affix_position);

				if ($result === FALSE)
				{
					$errors[] = sprintf('%s → %s: clone failed', $source->field_name, $code);
					continue;
				}

				// Add to new language group
				if ($new_group_id > 0)
				{
					$this->_assign_to_field_group($result, $new_group_id);
				}

				// Assign to target channels
				if ( ! empty($target_channels))
				{
					$this->_assign_to_channels($result, $target_channels);
				}

				if ($this->_last_reused)
				{
					$reused++;
				}
				else
				{
					$created++;
				}
			}
		}

		return array(
			'created' => $created,
			'reused'  => $reused,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Create a new field group for a language code
	 *
	 * @param  object $source_group Source ChannelFieldGroup model
	 * @param  string $code         Language code
	 * @param  string $affix_position 'suffix' or 'prefix'
	 * @return int    New group ID
	 */
	private function _create_language_group($source_group, $code, $affix_position)
	{
		$delimiter = '_';
		$code_upper = strtoupper($code);

		if ($affix_position === 'prefix')
		{
			$group_name = $code_upper . ' ' . $source_group->group_name;
			$short_name = $code . $delimiter . $source_group->short_name;
		}
		else
		{
			$group_name = $source_group->group_name . ' ' . $code_upper;
			$short_name = $source_group->short_name . $delimiter . $code;
		}

		// Check if group already exists
		$existing = ee('Model')->get('ChannelFieldGroup')
			->filter('short_name', $short_name)
			->first();

		if ($existing)
		{
			return $existing->group_id;
		}

		$group = ee('Model')->make('ChannelFieldGroup');
		$group->site_id = $source_group->site_id;
		$group->group_name = $group_name;
		$group->short_name = $short_name;

		try
		{
			$group->save();

			return $group->group_id;
		}
		catch (\Exception $e)
		{
			return 0;
		}
	}

	/**
	 * Clone a single field with one affix code
	 *
	 * @param  object $source         Source ChannelField model
	 * @param  string $code           Affix code (e.g. 'se')
	 * @param  string $affix_position 'suffix' or 'prefix'
	 * @return object|false           New ChannelField model or FALSE on failure
	 */
	private function _clone_single($source, $code, $affix_position)
	{
		$delimiter = '_';

		if ($affix_position === 'prefix')
		{
			$new_name = $code . $delimiter . $source->field_name;
		}
		else
		{
			$new_name = $source->field_name . $delimiter . $code;
		}

		$new_label = $source->field_label;

		// Check if clone already exists
		$existing = ee('Model')->get('ChannelField')
			->filter('field_name', $new_name)
			->first();

		if ($existing)
		{
			$this->_last_reused = TRUE;

			return $existing;
		}

		$this->_last_reused = FALSE;

		$clone = ee('Model')->make('ChannelField');

		foreach (self::$_field_properties as $prop)
		{
			$clone->$prop = $source->$prop;
		}

		$clone->field_name = $new_name;
		$clone->field_label = $new_label;

		try
		{
			$clone->save();

			// Clone grid columns if grid type
			if ($source->field_type === 'grid')
			{
				$this->_clone_grid_columns($source->field_id, $clone->field_id);
			}

			return $clone;
		}
		catch (\Exception $e)
		{
			return FALSE;
		}
	}

	/**
	 * Clone grid columns from source field to new field
	 *
	 * @param int $source_field_id Source grid field ID
	 * @param int $clone_field_id  New grid field ID
	 */
	private function _clone_grid_columns($source_field_id, $clone_field_id)
	{
		ee()->load->model('grid_model');

		// Cloning columns alone (the old behavior here) only copied the
		// grid_columns metadata rows. It never created the field's own
		// data table (exp_channel_grid_field_N), nor the per-column
		// storage columns inside it, so every cloned Grid field crashed
		// as soon as an entry using it was opened. Do what EE's own
		// Grid_lib::apply_settings() does when a Grid field is saved
		// from the CP: create the base table, then let each column's
		// fieldtype add its own storage column to it.
		ee()->grid_model->create_field((int) $clone_field_id, 'channel');

		$ft_settings = array(
			'id_field' => 'col_id',
			'type_field' => 'col_type',
			'col_settings_method' => 'grid_settings_modify_column',
			'col_prefix' => 'col',
			'fields_table' => 'grid_columns',
			'data_table' => 'channel_grid_field_' . (int) $clone_field_id,
		);

		$columns = ee()->db
			->where('field_id', (int) $source_field_id)
			->order_by('col_order', 'asc')
			->get('grid_columns')
			->result_array();

		foreach ($columns as $col)
		{
			unset($col['col_id']);
			$col['field_id'] = (int) $clone_field_id;

			ee()->db->insert('grid_columns', $col);
			$col_id = ee()->db->insert_id();

			$col_settings = is_array($col['col_settings'])
				? $col['col_settings']
				: json_decode($col['col_settings'], true);
			$col_settings = is_array($col_settings) ? $col_settings : array();

			ee()->api_channel_fields->setup_handler($col['col_type']);
			ee()->api_channel_fields->set_datatype(
				$col_id,
				$col_settings,
				array(),
				true,
				false,
				$ft_settings
			);
		}
	}

	/**
	 * Assign a field to a field group via pivot table
	 *
	 * @param object $field    ChannelField model
	 * @param int    $group_id Field group ID
	 */
	private function _assign_to_field_group($field, $group_id)
	{
		$exists = ee()->db
			->where('group_id', (int) $group_id)
			->where('field_id', (int) $field->field_id)
			->get('channel_field_groups_fields')
			->num_rows();

		if ($exists === 0)
		{
			ee()->db->insert('channel_field_groups_fields', array(
				'group_id' => (int) $group_id,
				'field_id' => (int) $field->field_id,
			));
		}
	}

	/**
	 * Assign a field to channels via pivot table
	 *
	 * @param object $field       ChannelField model
	 * @param array  $channel_ids Array of channel IDs
	 */
	private function _assign_to_channels($field, $channel_ids)
	{
		foreach ($channel_ids as $channel_id)
		{
			// Check if already assigned
			$exists = ee()->db
				->where('channel_id', (int) $channel_id)
				->where('field_id', (int) $field->field_id)
				->get('channels_channel_fields')
				->num_rows();

			if ($exists === 0)
			{
				ee()->db->insert('channels_channel_fields', array(
					'channel_id' => (int) $channel_id,
					'field_id'   => (int) $field->field_id,
				));
			}
		}
	}

	/**
	 * Find fields by affix pattern (shared Engine contract for Grouper)
	 *
	 * @param  int    $channel_id Channel ID
	 * @param  array  $affix_rule Array with 'delimiter', 'position', 'tokens'
	 * @return array  Fields grouped by token + 'unmatched' bucket
	 */
	public function findFieldsByAffix($channel_id, $affix_rule)
	{
		$channel = ee('Model')->get('Channel', $channel_id)
			->with('CustomFields')
			->first();

		if ( ! $channel)
		{
			return array();
		}

		$delimiter = $affix_rule['delimiter'] ?? '_';
		$position = $affix_rule['position'] ?? 'suffix';
		$tokens = $affix_rule['tokens'] ?? array();

		$grouped = array();
		$unmatched = array();

		foreach ($channel->CustomFields as $field)
		{
			$matched = FALSE;

			foreach ($tokens as $token)
			{
				if ($position === 'suffix')
				{
					$pattern = $delimiter . $token;

					if (substr($field->field_name, -strlen($pattern)) === $pattern)
					{
						$grouped[$token][] = $field;
						$matched = TRUE;
						break;
					}
				}
				else
				{
					$pattern = $token . $delimiter;

					if (substr($field->field_name, 0, strlen($pattern)) === $pattern)
					{
						$grouped[$token][] = $field;
						$matched = TRUE;
						break;
					}
				}
			}

			if ( ! $matched)
			{
				$unmatched[] = $field;
			}
		}

		$grouped['unmatched'] = $unmatched;

		return $grouped;
	}

	/**
	 * Get list of supported field types
	 *
	 * @return array
	 */
	public function getSupportedTypes()
	{
		return self::$_supported_types;
	}
}
