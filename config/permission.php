<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [
	'models' => [
		'permission' => Permission::class,
		'role' => Role::class,
	],

	'table_names' => [
		'roles' => 'roles',
		'permissions' => 'permissions',
		'model_has_permissions' => 'model_has_permissions',
		'model_has_roles' => 'model_has_roles',
		'role_has_permissions' => 'role_has_permissions',
	],

	'column_names' => [
		'model_morph_key' => 'model_id',
	],

	'guards' => ['web'],

	'cache' => [
		'expiration_time' => \DateInterval::createFromDateString('24 hours'),
		'store' => 'default',
		'key' => 'spatie.permission.cache',
	],
];


