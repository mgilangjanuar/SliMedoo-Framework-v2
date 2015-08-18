<?php

use \App;
use \Valitron\Validator;

class BaseModel extends App
{
    public $scenario = [];
    
    protected $_cols = [];

    protected $_where = [];

    protected $_errors = [];

    protected $_join = [];

    public function __construct()
    {
        if ($this->tableName() != null && $this->fields() != null) {
            $this->_cols = array_flip($this->fields());
            foreach ($this->_cols as $key => $value) {
                $this->_cols[$key] = isset($_POST[$key]) ? $_POST[$key] : null;
            }
            foreach (get_class_methods($this) as $func) {
                if (substr($func, 0, 3) == 'set') {
                    $attribute = lcfirst(substr($func, 3));
                    $this->$attribute = isset($_POST[$attribute]) ? $_POST[$attribute] : null;
                }
            }
        }
    }

    public function __get($key)
    {
        $attribute = 'get' . $key;
        if (array_key_exists($key, $this->_cols)) {
            return $this->_cols[$key];
        } elseif (method_exists($this, $attribute)) {
            return $this->$attribute();
        } else {
            throw new Exception("Can't get $key");
        }
    }

    public function __set($key, $value)
    {
        $attribute = 'set' . $key;
        if (array_key_exists($key, $this->_cols)) {
            $this->_cols[$key] = $value;
        } elseif (method_exists($this, $attribute)) {
            $this->$attribute($value);
        } else {
            throw new Exception("Can't set $key");
        }
    }

    public static function find($where=[])
    {
        $class = static::className();
        $class = new $class;
        $class->where($where);
        return $class;

    }

    public function tableName()
    {
        return null;
    }

    public function fields()
    {
        return [];
    }

    public function attributeLabel($attribute)
    {
        $labels = $this->attributeLabels();
        if (array_key_exists($attribute, $labels)) {
            return $labels[$attribute];
        } elseif (array_key_exists($attribute, $this->_cols)) {
            if ($attribute == 'id') {
                return 'ID';
            } else {
                // http://stackoverflow.com/questions/5546120/php-capitalize-after-dash
                return str_replace('_', ' ', preg_replace_callback('/(\w+)/', create_function('$m','return ucfirst($m[1]);'), $attribute));
            }
        } elseif (method_exists($this, 'get' . $attribute)) {
            // http://stackoverflow.com/questions/6254093/how-to-parse-camel-case-to-human-readable-string
            return ucwords(preg_replace(array('/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'), ' $0', $attribute));
        }
        return $attribute;
    }

    public function attributeLabels()
    {
        return [];
    }

    public function rules()
    {
        return [];
    }

    public function messages()
    {
        return [];
    }

    protected function loadAllRuleMethods()
    {
        foreach (get_class_methods($this) as $funcRule) {
            if (substr($funcRule, 0, 4) == 'rule' && $funcRule != 'rules') {
                Validator::addRule(lcfirst(substr($funcRule, 4)), $this->$funcRule()['function'], $this->$funcRule()['message']);
            }
        }
    }

    public function validate()
    {
        $this->loadAllRuleMethods();

        if ($this->isNewRecord()) {
            $datas = $this->_cols;
        } else {
            $datas = App::db()->get($this->tableName(), '*', $this->_where);
        }

        foreach (get_class_methods($this) as $funcGet) {
            if (substr($funcGet, 0, 3) == 'get') {
                $datas[lcfirst(substr($funcGet, 3))] = $this->$funcGet();
            }
        }

        $validator = new Validator($datas);
        
        foreach ($this->rules() as $value) {
            if (array_key_exists($value[1], $this->messages())) {
                if (count($value) == 2) {
                    $validator->rule($value[1], $value[0])->message($this->messages()[$key]);
                } else {
                    $validator->rule($value[1], $value[0], $value[2])->message($this->messages()[$key]);
                }
                $labels = [];
                foreach ($value as $key1) {
                    $labels[$key1] = $this->attributeLabel($key1);
                }
                $validator->labels($labels);
            } else {
                if (count($value) == 2) {
                    $validator->rule($value[1], $value[0]);
                } else {
                    $validator->rule($value[1], $value[0], $value[2]);
                }
            }
        }
        if ($validator->validate()) {
            return true;
        } else {
            $this->_errors = $validator->errors();
            return false;
        }
    }

    public function load($request)
    {
        if ($request == null) {
            return false;
        }
        foreach ($this->_cols as $key => $value) {
            if (array_key_exists($key, $request)) {
                $this->$key = $request[$key];
            }
        }
        foreach (get_class_methods($this) as $func) {
            if (substr($func, 0, 3) == 'set') {
                $attribute = lcfirst(substr($func, 3));
                if (array_key_exists($attribute, $request)) {
                    $this->$attribute = $request[$attribute];
                }
            }
        }

    }

    public function beforeSave()
    {
        return true;
    }

    public function afterSave()
    {
        return true;
    }

    public function save($validate=true)
    {
        $data = [];
        foreach ($this->_cols as $key => $value) {
            if ($value != null) {
                $data[$key] = $value;
            }
        }
        if ((! $validate || $this->validate()) && $this->beforeSave()) {
            $this->afterSave();
            if ($this->isNewRecord()) {
                return App::db()->insert($this->tableName(), $data);
            } else {
                return App::db()->update($this->tableName(), $data, $this->_where);
            }
        }
        return false;
    }

    public function where($where=[])
    {
        $this->_where = $where;
        return $this;
    }

    public function join($join=[])
    {
        $this->_join = $join;
        return $this;
    }

    public function limit($value='')
    {
        $this->_where['LIMIT'] = $value;
        return $this;
    }

    public function orderBy($value='')
    {
        $this->_where['ORDER'] = $value;
        return $this;
    }

    public function all()
    {
        $results = [];
        if ($this->_join == null) {
            $query = App::db()->select($this->tableName(), '*', $this->_where);
        } else {
            $query = App::db()->select($this->tableName(), $this->_join, '*', $this->_where);
        }
        foreach ($query as $row) {
            foreach (get_class_methods($this) as $func) {
                if (substr($func, 0, 3) == 'get') {
                    foreach ($this->_cols as $key => $value) {
                        $this->$key = $row[$key];
                    }
                    $row[lcfirst(substr($func, 3))] = $this->$func();
                }
            }
            $results[] = (object) $row;
        }
        return $results;
    }

    public function one()
    {
        $results = App::db()->get($this->tableName(), '*', $this->_where);
        foreach (get_class_methods($this) as $func) {
            if (substr($func, 0, 3) == 'get') {
                foreach ($this->_cols as $key => $value) {
                    $this->$key = $results[$key];
                }
                $results[lcfirst(substr($func, 3))] = $this->$func();
            }
        }
        return (object) $results;
    }

    public function beforeDelete()
    {
        return true;
    }

    public function delete()
    {
        if ($this->_where != null && $this->beforeDelete()) {
            return App::db()->delete($this->tableName(), $this->_where);
        }
        return false;
    }

    public function count()
    {
        return App::db()->count($this->tableName(), $this->_where);
    }

    public function sum($column)
    {
        return App::db()->sum($this->tableName(), $column, $this->_where);
    }

    public function avg($column)
    {
        return App::db()->avg($this->tableName(), $column, $this->_where);
    }

    public function max($column)
    {
        return App::db()->max($this->tableName(), $column, $this->_where);
    }

    public function min($column)
    {
        return App::db()->min($this->tableName(), $column, $this->_where);
    }

    public function isNewRecord()
    {
        return $this->_where == null;
    }

    public function hasErrors()
    {
        return $this->_errors != null;
    }

    public function errors()
    {
        if ($this->hasErrors()) {
            return $this->_errors;
        }
        return false;
    }

    public function has($class, $datas)
    {
        $where = [];
        foreach ($datas as $key => $value) {
            $where[$key] = $this->$value;
        }
        return $class::find()->where($where);
    }

    public function timestampBehaviour()
    {
        if (array_key_exists('created_at', $this->_cols)) {
            if ($this->isNewRecord()) {
                $this->created_at = time();
            }
        } elseif (array_key_exists('updated_at', $this->_cols)) {
            $this->updated_at = time();
        }
    }

}