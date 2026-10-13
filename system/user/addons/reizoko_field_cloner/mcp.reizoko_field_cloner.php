<?php

use Reizoko\FieldCloner\Engine\Cloner;

class Reizoko_field_cloner_mcp
{
	/**
	 * Main CP page — clone form
	 */
	public function index()
	{
		// Cloning creates fields and field groups, so restrict to Super Admins
		if ( ! ee('Permission')->isSuperAdmin())
		{
			show_error(lang('unauthorized_access'), 403);
		}

		// Handle clone submission
		if ( ! empty($_POST) && ee()->input->post('action') === 'clone')
		{
			return $this->_process_clone();
		}

		// Load field groups
		$field_groups = ee('Model')->get('ChannelFieldGroup')
			->order('group_name', 'asc')
			->all();

		// Load individual fields
		$fields = ee('Model')->get('ChannelField')
			->order('field_label', 'asc')
			->all();

		// Load channels
		$channels = ee('Model')->get('Channel')
			->order('channel_title', 'asc')
			->all();

		$cloner = new Cloner();

		$vars = array(
			'field_groups'    => $field_groups,
			'fields'          => $fields,
			'channels'        => $channels,
			'supported_types' => $cloner->getSupportedTypes(),
			'base_url'        => ee('CP/URL')->make('addons/settings/reizoko_field_cloner'),
			'cp_page_title'   => lang('field_cloner_module_name'),
		);

		return array(
			'heading' => lang('field_cloner_module_name'),
			'body'    => ee('View')->make('reizoko_field_cloner:index')->render($vars),
			'breadcrumb' => array(
				ee('CP/URL')->make('addons/settings/reizoko_field_cloner')->compile() => lang('field_cloner_module_name'),
			),
		);
	}

	/**
	 * Process clone form submission
	 */
	private function _process_clone()
	{
		$source_type = ee()->input->post('source_type');
		$affix_position = ee()->input->post('affix_position');
		$language_codes = ee()->input->post('language_codes');
		$target_channels = ee()->input->post('target_channels');

		$affix_position = ($affix_position === 'prefix') ? 'prefix' : 'suffix';

		// Parse language codes (comma-separated)
		$codes = array_unique(array_filter(array_map('trim', explode(',', (string) $language_codes))));

		if (empty($codes))
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('clone_error'))
				->addToBody(lang('no_language_codes'))
				->now();

			return $this->index();
		}

		// Codes become part of field and group names, which EE only allows
		// to contain letters, numbers, dashes and underscores. Saving a model
		// does not run EE's validation, so check here.
		foreach ($codes as $code)
		{
			if ( ! preg_match('/^[A-Za-z0-9_-]{1,10}$/', $code))
			{
				ee('CP/Alert')->makeInline('shared-form')
					->asIssue()
					->withTitle(lang('clone_error'))
					->addToBody(lang('invalid_language_codes'))
					->now();

				return $this->index();
			}
		}

		// Gather source fields
		$source_fields = array();

		if ($source_type === 'group')
		{
			$group_id = (int) ee()->input->post('field_group_id');

			// Get field IDs from pivot table
			$rows = ee()->db->where('group_id', $group_id)
				->get('channel_field_groups_fields')
				->result_array();

			$field_ids = array();
			foreach ($rows as $row)
			{
				$field_ids[] = (int) $row['field_id'];
			}

			if ( ! empty($field_ids))
			{
				$source_fields = ee('Model')->get('ChannelField', $field_ids)->all()->asArray();
			}
		}
		else
		{
			$field_ids = array_map('intval', (array) ee()->input->post('field_ids'));

			if ( ! empty($field_ids))
			{
				$source_fields = ee('Model')->get('ChannelField', $field_ids)->all()->asArray();
			}
		}

		if (empty($source_fields))
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('clone_error'))
				->addToBody(lang('no_fields_selected'))
				->now();

			return $this->index();
		}

		// EE limits field names to 32 characters
		foreach ($source_fields as $source)
		{
			foreach ($codes as $code)
			{
				if (strlen($source->field_name) + 1 + strlen($code) > 32)
				{
					ee('CP/Alert')->makeInline('shared-form')
						->asIssue()
						->withTitle(lang('clone_error'))
						->addToBody(sprintf(lang('field_name_too_long'), $source->field_name, $code))
						->now();

					return $this->index();
				}
			}
		}

		// Clone
		$source_group_id = ($source_type === 'group') ? (int) ee()->input->post('field_group_id') : 0;
		$cloner = new Cloner();
		$results = $cloner->cloneFields($source_fields, $codes, $affix_position, array_map('intval', (array) $target_channels), $source_group_id);

		$url = ee('CP/URL')->make('addons/settings/reizoko_field_cloner');
		$url->setQueryStringVariable('created', $results['created']);
		$url->setQueryStringVariable('reused', $results['reused']);
		$url->setQueryStringVariable('sources', count($source_fields));
		$url->setQueryStringVariable('codes', count($codes));
		ee()->functions->redirect($url);
	}
}
