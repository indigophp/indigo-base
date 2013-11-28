<?php

namespace Admin;

trait Model_Skeleton
{
	protected static $_columns_cached = array();

	public static function properties($relations = false)
	{
		$properties = parent::properties();
		$class = get_called_class();

		// If already determined
		if ( ! array_key_exists($class, static::$_columns_cached))
		{
			static::$_columns_cached[$class] = $properties;

			foreach ($properties as $key => $value)
			{
				if (strpos($key, '.') !== false or \Arr::get($value, 'eav', false) === true)
				{
					unset($properties[$key]);
				}
			}

			static::$_properties_cached[$class] = $properties;
		}

		if ($relations === true)
		{
			return static::$_columns_cached[$class];
		}
		else
		{
			return static::$_properties_cached[$class];
		}
	}

	/**
	 * Fetches a property description array, or specific data from it
	 *
	 * @param   string  property or property.key
	 * @param   mixed   return value when key not present
	 * @return  mixed
	 */
	public static function column($key, $default = null)
	{
		$class = get_called_class();

		// If already determined
		if ( ! array_key_exists($class, static::$_columns_cached))
		{
			static::properties(true);
		}

		return \Arr::get(static::$_columns_cached[$class], $key, $default);
	}

	public static function view()
	{
		$properties = static::properties(true);

		return array_filter($properties, function($item) {
			return \Arr::get($item, 'view', true) === true;
		});
	}

	public static function lists()
	{
		$properties = static::properties(true);

		return array_filter($properties, function($item) {
			return \Arr::get($item, 'list.type', false) !== false;
		});
	}

	public static function form()
	{
		$properties = static::properties(true);

		return array_filter($properties, function($item) {
			return \Arr::get($item, 'form.type', false) !== false;
		});
	}

	public function options($field)
	{
		$column = static::column($field, array());
		$column = \Arr::merge(\Arr::get($column, 'form', array()), \Arr::get($column, 'list', array()));

		// Get options and parse it if it is a Closure
		$options = array_key_exists('options', $column) ? $column['options'] : null;

		if ($options instanceof \Closure)
		{
			$options = $options($this);
		}

		// We have a string
		if (is_string($options))
		{
			// Is it comma delimited or the name of an enum?
			if (strpos($options, ','))
			{
				$options = explode(',', $options);
			}
			else
			{
				$options = \Model_Enum::query()
					->related('default')
					->related('items')
					->related('items.meta')
					->where('slug', $options)
					->get_one()
					->to_array();

				$options = \Arr::pluck($options['items'], 'name', 'id');
			}
		}

		return $options;
	}
}