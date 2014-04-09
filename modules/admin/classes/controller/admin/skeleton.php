<?php

namespace Admin;

use Fuel\Validation\Validator;
use Fuel\Validation\RuleProvider\FromArray;
use Orm\Model;

abstract class Controller_Admin_Skeleton extends Controller_Admin
{
	/**
	 * Parsed module name
	 *
	 * @var string
	 */
	protected $_module;

	/**
	 * Parsed url of module
	 *
	 * @var string
	 */
	protected $_url;

	/**
	 * Name of the module
	 *
	 * @var string
	 */
	protected $_name;

	/**
	 * Parsed model name
	 *
	 * @var string
	 */
	protected $_model;

	protected static $translate = array();

	public function before($data = null)
	{
		parent::before($data);

		$translate = $this->translate();
		$this->access();

		\View::set_global('module', $this->module());
		\View::set_global('module_name', $this->name());
		\View::set_global('url', $this->url());
	}

	/**
	 * Parse module name
	 *
	 * @return string
	 */
	protected function module()
	{
		if ( ! empty($this->_module))
		{
			return $this->_module;
		}

		if ($this->request->module == 'admin')
		{
			$module = \Inflector::denamespace($this->request->controller);
			$module = strtolower(str_replace('Controller_', '', $module));

			return $this->_module = $module;
		}
		else
		{
			return $this->_module = $this->request->module;
		}
	}

	protected function url()
	{
		if ( ! empty($this->_url))
		{
			return $this->_url;
		}

		return $this->_url = \Uri::admin() . str_replace('_', '/', $this->module());
	}

	abstract protected function name();

	/**
	 * Parse model name
	 *
	 * @return string
	 */
	protected function model()
	{
		if ( ! empty($this->_model))
		{
			return $this->_model;
		}

		return $this->_model = ucfirst($this->request->module) . '\\' . 'Model_' . \Inflector::classify($this->module());
	}

	/**
	 * Overrideable method to create new view
	 *
	 * @param   string  $view         View name
	 * @param   array   $data         View data
	 * @param   bool    $auto_filter  Auto filter the view data
	 * @return  View    New View object
	 */
	protected function view($view, $data = array(), $auto_filter = null)
	{
		return $this->theme->view($view, $data, $auto_filter);
	}

	protected function translate()
	{
		return array();
	}

	/**
	 * Check whether user has acces to view page
	 */
	protected function access($access = null)
	{
		if ( ! $this->has_access($this->request->action))
		{
			\Session::set_flash('error', \Arr::get(static::$translate, $this->request->action . '.access', gettext('You are not authorized to do this.')));
			return \Response::redirect_back(\Uri::admin(false));
		}
	}

	/**
	 * Check whether user has access to something
	 *
	 * @param  string  $access Resource
	 * @return boolean
	 */
	protected function has_access($access)
	{
		return \Auth::has_access($this->module() . '.' . $access);
	}

	/**
	 * Creates a new query with optional settings up front
	 *
	 * @param   array
	 * @return  Query
	 */
	protected function query($options = array())
	{
		$model = $this->model();

		return $model::query($options);
	}

	/**
	 * Finds an entity of model
	 *
	 * @param   int
	 * @return  \Orm\Model
	 */
	protected function find($id = null)
	{
		$query = $this->query();
		$query->where('id',  $id)->rows_limit(1);

		if (is_null($id) or is_null($model = $query->get_one()))
		{
			throw new \HttpNotFoundException();
		}

		return $model;
	}

	protected function forge($data = array(), $new = true, $view = null, $cache = true)
	{
		$model = $this->model();
		$model = $model::forge($data, $new, $view, $cache);
		return $model;
	}

	/**
	 * Process query for ajax request
	 *
	 * @param  \Orm\Query $query    Query object
	 * @param  array      $columns  Column definitions
	 * @param  array      $defaults Default column values
	 * @return int                  Items count
	 */
	protected function process_query(\Orm\Query $query, array $columns = array(), array $defaults = array())
	{
		// Count all items
		$all_items_count = $query->count();

		// Process incoming sortng values
		$sort = array();
		for ($i = 0; $i < \Input::param('iSortingCols'); $i++)
		{
			$sort[\Input::param('iSortCol_'.$i)] = \Input::param('sSortDir_'.$i);
		}

		$i = 0;
		$order_by = array();
		$where = array();
		$global_filter = \Input::param('sSearch');

		foreach ($columns as $key => $value)
		{
			$rels = explode('.', $key);

			$rel = '';

			for ($j=0; $j < count($rels) - 1 and count($rels) > 1; $j++)
			{
				if (empty($rel))
				{
					$rel = $rels[$j];
				}
				else
				{
					$rel .= '.' . $rels[$j];
				}

				$query->related($rel);
			}

			$value = \Arr::merge($defaults, $value);

			if ($eav = \Arr::get($value, 'eav', false))
			{
				$query->related($rel . '.' . $eav);
			}

			if (\Input::param('bSortable_'.$i, true) and \Arr::get($value, 'list.sort', true) and array_key_exists($i,  $sort))
			{
				$order_by[$key] = $sort[$i];
			}

			$filter = \Input::param('sSearch_'.$i);

			$filter = json_decode($filter);

			if ( ! in_array($filter, array(null, '', 'null')) and \Input::param('bSearchable_'.$i, true) and \Arr::get($value, 'list.search', true))
			{
				switch (\Arr::get($value, 'list.type', 'text'))
				{
					case 'select-multiple':
					case 'select':
					case 'enum':
						$query->where($key, 'IN', $filter);
						break;
					case 'select-single':
					case 'number':
						$query->where($key, $filter);
						break;
					case 'text':
						$query->where($key, 'LIKE', '%' . $filter . '%');
						break;
					case 'range':
						$query->where($key, 'BETWEEN', $filter);
						break;
					default:
						break;
				}
			}

			if ( ! empty($global_filter))
			{
				if (\Arr::get($value, 'list.search', true) === true and \Arr::get($value, 'list.global', true) === true)
				{
					$where[] = array($key, 'LIKE', '%' . $global_filter . '%');
				}
			}

			$i++;
		}

		if ( ! empty($where))
		{
			$query->where_open();
			foreach ($where as $where)
			{
				$query->or_where($where[0], $where[1], $where[2]);
			}
			$query->where_close();
		}

		// Order query
		$query->order_by($order_by);

		$partial_items_count = $query->count();

		// Limit query
		$query
			->limit(\Input::param('iDisplayLength', 10))
			->offset(\Input::param('iDisplayStart', 0));

		return array($all_items_count, $partial_items_count);
	}

	/**
	 * This function is called in array_map to process the response
	 *
	 * @param  \Orm\Model $model      Returned model instance
	 * @param  array      $properties Properties to use
	 * @return array                  Array of returned elements
	 */
	protected function map(\Orm\Model $model, array $properties)
	{
		$data = $model->to_array(false, false, true);
		$data = \Arr::subset($data, array_keys($properties));
		$data = \Arr::flatten_assoc($data, '.');

		// Check for options and set value
		foreach ($properties as $key => $value)
		{
			if ( ! empty($data) and $options = \Arr::get($value, 'list.options', false))
			{
				$data[$key] = $options[$data[$key]];
			}
		}

		$actions = array();

		if ($this->has_access('view'))
		{
			array_push($actions, array(
				'url' => \Uri::create($this->url() . '/view/' . $model->id),
				'icon' => 'glyphicon glyphicon-eye-open',
			));
		}

		if ($this->has_access('edit'))
		{
			array_push($actions, array(
				'url' => \Uri::create($this->url() . '/edit/' . $model->id),
				'icon' => 'glyphicon glyphicon-edit',
			));
		}

		if ($this->has_access('delete'))
		{
			array_push($actions, array(
				'url' => \Uri::create($this->url() . '/delete/' . $model->id),
				'icon' => 'glyphicon glyphicon-remove text-danger',
			));
		}

		$data['action'] = $this->view('admin/skeleton/list/action')->set('actions', $actions, false);

		return $data;
	}

	/**
	 * Return validation object
	 *
	 * @param  \Orm\Model $model
	 * @return \Fuel\Validation\Validator
	 */
	protected function validation(Model $model = null)
	{
		$validator = new Validator;

		if ($model === null)
		{
			return $validator;
		}

		foreach ($model->form() as $fieldName => $fieldParams)
		{
			$label = \Arr::get($fieldParams, 'label', gettext('Unidentified Property'));

			$validator->addField($fieldName, $label);

			$rules = \Arr::get($fieldParams, 'validation', array());

			foreach ($rules as $rule => $params)
			{
				$ruleInstance = $validator->createRuleInstance($rule, $params);
				$validator->addRule($fieldName, $ruleInstance);
			}
		}

		return $validator;
	}

	protected function redirect($url = '', $method = 'location', $code = 302)
	{
		return \Response::redirect($url, $method, $code);
	}

	protected function is_ajax()
	{
		if (\Fuel::$env == \Fuel::DEVELOPMENT)
		{
			return \Input::extension();
		}

		return \Input::is_ajax();
	}

	public function action_index()
	{
		$model = $this->model();

		if ($this->is_ajax())
		{
			$properties = $model::lists();

			$query = $this->query();

			$count = $this->process_query($query, $properties);

			$models = $query->get();

			$data = array(
				'sEcho' => \Input::param('sEcho'),
				'iTotalRecords' => $count[0],
				'iTotalDisplayRecords' => $count[1],
				'aaData' => array_values(array_map(function($model) use($properties) {
					$model = $this->map($model, $properties);

					if (array_key_exists('action', $model) and $model['action'] instanceof \View)
					{
						$model['action'] = $model['action']->render();
					}

					return array_values($model);
				}, $models))
			);

			$ext = \Input::extension();
			in_array($ext, array('xml', 'json')) or $ext = 'json';

			$data = \Format::forge($data)->{'to_' . $ext}();

			return \Response::forge($data, 200, array('Content-type' => 'application/' . $ext));
		}
		else
		{
			$this->template->set_global('title', ucfirst($this->name()[1]));
			$this->template->content = $this->view('admin/skeleton/list');
			$this->template->content->set('model', $this->forge(), false);
		}
	}

	public function action_create()
	{
		$this->template->set_global('title', ucfirst(
			\Str::trans(gettext('New %item%'), '%item%', $this->name()[0])
		));
		$this->template->content = $this->view('admin/skeleton/create');
		$this->template->content->set('model', $this->forge(), false);
	}

	public function post_create()
	{
		$model = $this->model();
		$properties = $model::form();
		$model = $this->forge();

		$val = $this->val($properties);

		if ($val->run() === true)
		{
			$model->set($val->validated())->save();

			\Session::set_flash('success', ucfirst(
				\Str::trans(gettext('%item% successfully created.'), '%item%', $this->name()[0])
			));

			return $this->redirect($this->url() . '/view/' . $model->id);
		}
		else
		{
			$this->template->set_global('title', ucfirst(
				\Str::trans(gettext('New %item%'), '%item%', $this->name()[0])
			));
			$this->template->content = $this->view('admin/skeleton/create');
			$this->template->content->set('model', $model->set($val->input()), false);
			$this->template->content->set('val', $val, false);
			\Session::set_flash('error', gettext('There were some errors.'));
		}

		return false;
	}

	public function action_view($id = null)
	{
		$model = $this->find($id);
		$this->template->set_global('title', ucfirst(
			\Str::trans(gettext('View %item%'), '%item%', $this->name()[0])
		));
		$this->template->content = $this->view('admin/skeleton/view');
		$this->template->content->set('model', $model, false);
	}

	public function action_edit($id = null)
	{
		$model = $this->find($id);
		$this->template->set_global('title', ucfirst(
			\Str::trans(gettext('Edit %item%'), '%item%', $this->name()[0])
		));
		$this->template->content = $this->view('admin/skeleton/edit');
		$this->template->content->set('model', $model, false);
	}

	public function post_edit($id = null)
	{
		$model = $this->find($id);
		$properties = $model->form();

		$val = $this->val($properties);

		if ($val->run() === true)
		{
			$model->set($val->validated())->save();
			\Session::set_flash('success', ucfirst(
				\Str::trans(gettext('%item% successfully updated.'), '%item%', $this->name()[0])
			));
			return $this->redirect($this->url());
		}
		else
		{
			$this->template->set_global('title', ucfirst(
				\Str::trans(gettext('Edit %item%'), '%item%', $this->name()[0])
			));
			$this->template->content = $this->view('admin/skeleton/edit');
			$this->template->content->set('model', $model->set($val->input()), false);
			$this->template->content->set('val', $val, false);
			\Session::set_flash('error', gettext('There were some errors.'));
		}

		return false;
	}

	public function action_delete($id = null)
	{
		$model = $this->find($id);

		if ($model->delete())
		{
			\Session::set_flash('success', ucfirst(
				\Str::trans(gettext('%item% successfully deleted.'), '%item%', $this->name()[0])
			));
			return \Response::redirect_back();
		}
		else
		{
			\Session::set_flash('success', ucfirst(
				\Str::trans(gettext('%item% cannot be deleted.'), '%item%', $this->name()[0])
			));
			return \Response::redirect_back();
		}
	}
}
