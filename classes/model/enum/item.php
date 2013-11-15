<?php

namespace Indigo\Base;

class Model_Enum_Item extends \Orm\Model
{
	protected static $_belongs_to = array(
		'enum' => array(
			'model_to' => 'Model_Enum',
		)
	);

	protected static $_eav = array(
		'meta' => array(
			'attribute' => 'key',
			'value'     => 'value',
		)
	);

	protected static $_has_many = array(
		'meta' => array(
			'model_to'       => 'Model_Enum_Meta',
			'key_to'         => 'item_id',
			'cascade_delete' => true,
		),
	);

	protected static $_observers = array(
		'Orm\\Observer_Slug' => array('source' => 'name'),
		'Orm\\Observer_Typing',
		'Orm\\Observer_Self' => array(
			'events' => array('before_insert')
		)
	);

	protected static $_properties = array(
		'id',
		'enum_id' => array('data_type' => 'int'),
		'item_id' => array('data_type' => 'int'),
		'name',
		'slug',
		'description',
		'active' => array(
			'default'   => 1,
			'data_type' => 'boolean',
			'min'       => 0,
			'max'       => 1,
		),
		'sort' => array('data_type' => 'int'),
	);

	protected static $_sort = true;

	protected static $_table_name = 'enum_items';

	public function _event_before_insert()
	{
		$this->item_id = $this->query()->where('enum_id', $this->enum_id)->max('item_id') + 1;
		static::$_sort === true and $this->sort = $this->query()->where('enum_id', $this->enum_id)->max('sort') + 10;
	}

	public static function query($options = array())
	{
		$query = parent::query($options);

		if ( ! empty(static::$_enum))
		{
			if (is_numeric(static::$_enum))
			{
				$enum_id = static::$_enum;
			}
			elseif (is_string(static::$_enum))
			{
				$enum_id = static::enum()->id;
			}

			$query->where('enum_id', $enum_id);
		}

		return $query;
	}

	public static function forge($data = array(), $new = true, $view = null, $cache = true)
	{
		$model = parent::forge($data, $new, $view, $cache);

		$model->set('enum', static::enum());

		return $model;
	}

	public static function create_enum(array $options = array())
	{
		$enum = \Model_Enum::forge($options);
		$enum->save();

		return $enum;
	}

	public static function enum()
	{
		if ( ! empty(static::$_enum))
		{
			if (is_numeric(static::$_enum))
			{
				return \Model_Enum::find(static::$_enum);
			}
			elseif (is_string(static::$_enum))
			{
				return \Model_Enum::find_by_slug(static::$_enum);
			}

		}
	}
}