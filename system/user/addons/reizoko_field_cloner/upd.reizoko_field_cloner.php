<?php

class Reizoko_field_cloner_upd
{
	public $version = '1.0.0';

	public function install()
	{
		ee('Model')->make('Module', array(
			'module_name'        => 'Reizoko_field_cloner',
			'module_version'     => $this->version,
			'has_cp_backend'     => 'y',
			'has_publish_fields' => 'n',
		))->save();

		return TRUE;
	}

	public function update($current = '')
	{
		return TRUE;
	}

	public function uninstall()
	{
		ee()->db->where('module_name', 'Reizoko_field_cloner')->delete('modules');

		return TRUE;
	}
}
