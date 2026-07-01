<?php

use Reizoko\FieldCloner\Engine\Cloner;

class Reizoko_field_cloner_mcp
{
	/**
	 * Main CP page — clone form
	 */
	public function index()
	{
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

		// Parse language codes (comma-separated)
		$codes = array_filter(array_map('trim', explode(',', $language_codes)));

		if (empty($codes))
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('clone_error'))
				->addToBody(lang('no_language_codes'))
				->now();

			return $this->index();
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
			$field_ids = ee()->input->post('field_ids');

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

		// Clone
		$source_group_id = ($source_type === 'group') ? (int) ee()->input->post('field_group_id') : 0;
		$cloner = new Cloner();
		$results = $cloner->cloneFields($source_fields, $codes, $affix_position, $target_channels ?: array(), $source_group_id);

		$url = ee('CP/URL')->make('addons/settings/reizoko_field_cloner');
		$url->setQueryStringVariable('created', $results['created']);
		$url->setQueryStringVariable('sources', count($source_fields));
		$url->setQueryStringVariable('codes', count($codes));
		ee()->functions->redirect($url);
	}
}
