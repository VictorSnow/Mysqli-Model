<?php

/**
 * 基础查询类
 *
 * Class Models
 */
class Models
{
    /**
     * @var mysqli
     */
    private static $db;

    protected $pk = 'id';
    protected $table = 'model';

    protected $_params = [];
    protected $_params_bind = [];

    protected $_sql = '';
    protected $_conditions = [];

    protected $_order = [];
    protected $_limit = '';
    protected $_fields = '*';
    protected $_joins = [];

    protected $_data = [];

    protected $_affect_rows = -1;

    /**
     * last error
     * @var string
     */
    protected $_last_error = '';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        if (empty(self::$db)) {
            self::$db = get_instance()->db->conn_id;
        }
    }

    /**
     * @return mysqli
     */
    public function getDb()
    {
        return self::$db;
    }

    /**
     * @return string
     */
    public function getLastSql()
    {
        return $this->_sql;
    }

    /**
     * @return string
     */
    public function getLastError()
    {
        return $this->_last_error;
    }

    /**
     * 绑定的参数
     *
     * @param $params
     */
    public function params($params = [])
    {
        $this->_params = $params;
        return $this;
    }

    public function data($data = [])
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * 清理环境
     */
    public function clear()
    {
        $this->_params = [];
        $this->_conditions = [];
        $this->_joins = [];
        $this->_order = [];
        $this->_limit = '';
        $this->_fields = '*';
        $this->_data = [];
        $this->_affect_rows = -1;
        $this->_last_error = '';
    }

    public function from($table)
    {
        $this->table = $table;
        return $this;
    }

    public function fields($fields)
    {
        if (is_array($fields)) {
            $fields = join(',', $fields);
        }

        $this->_fields = $fields;
        return $this;
    }

    /**
     * 默认以and的形式查询
     *
     * @param $conditions
     */
    public function where($conditions = [])
    {
        if (empty($conditions)) {
            return $this;
        }

        $params = [];
        $this->_conditions[] = $this->parse($conditions, $params);
        $this->_params = array_merge($this->_params, $params);
        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        $this->_limit = $offset.','.$limit;
        return $this;
    }

    /**
     * 联合
     *
     * @param $tables
     * @param $condition
     * @param string $type
     */
    public function join($tables, $condition = '', $type = 'left')
    {
        if (!is_array($tables)) {
            $this->_joins[] = [$tables, $condition, $type];
            return $this;
        }

        $this->_joins = array_merge($this->_joins, $tables);
        return $this;
    }

    /**
     * 排序
     *
     * @param $column
     * @param $direction
     */
    public function order($column, $direction = 'asc') {
        if (!is_array($column)) {
            $this->_order[$column] = $direction;
        } elseif(is_array($column)) {
            $this->_order = array_merge($this->_order, $column);
        }
        return $this;
    }

    /**
     * 转换查询
     * @param $conditions
     * @return string | bool
     */
    protected function parse($conditions, &$params)
    {
        if (empty($conditions)) {
            return '';
        }

        if (is_numeric($conditions)) {
            $params = $conditions;
            return $this->pk.'=?';
        }

        if (is_string($conditions)) {
            return $conditions;
        }

        // 数字 in 格式的 [1,2,3]
        if (is_array($conditions) && isset($conditions[0])) {
            $con = '';
            foreach ($conditions as $key => $value) {
                $con.=',?';
                $params[] = $value;
            }
            return $this->pk.' in ('.trim($con, ',').')';
        }

        // $or $and  嵌套查询
        if (isset($conditions['$or'])) {
            $tmp = [];
            foreach ($conditions['$or'] as $key => $cons) {
                if (is_numeric($key)) {
                    $tmp[] = $this->parse($cons, $params);
                } else {
                    $tmp[] = $this->parse([$key => $cons], $params);
                }
            }
            return '('. join(' ) or ( ', $tmp) . ')';
        } elseif (isset($conditions['$and'])) {
            $tmp = [];
            foreach ($conditions['$and'] as $key => $cons) {
                if (is_numeric($key)) {
                    $tmp[] = $this->parse($cons, $params);
                } else {
                    $tmp[] = $this->parse([$key => $cons], $params);
                }
            }
            return '('. join(' ) and ( ', $tmp) . ')';
        }

        // 简单的key value类型 [a => b]
        $con = [];
        foreach ($conditions as $key => $value) {
            if (!is_array($value)) {
                $con[] = $key .'=?';
                $params[] = $value;
                continue;
            }

            switch ($value[0]) {
                case '=':
                case '>':
                case '!=':
                case '<':
                case '<>':
                    $con[] = $key. ' '. $value[0]. ' ?';
                    $params[] = $value[1];
                    break;
                case 'and':
                    $con[] = $key. ' between ? and ?';
                    $params[] = $value[1];
                    $params[] = $value[2];
                    break;
                case 'notin':
                    $tmp = '';
                    foreach ($value[1] as $k => $v) {
                        $tmp .= ',?';
                        $params[] = $v;
                    }
                    $con[] = $key .'not in ('.trim($tmp, ',').')';
                    break;
                case 'in':
                    $tmp = '';
                    foreach ($value[1] as $k => $v) {
                        $tmp .= ',?';
                        $params[] = $v;
                    }
                    $con[] = $key .' in ('.trim($tmp, ',').')';
                    break;
                case 'null':
                    $con[] = $key.' is null';
                    break;
                case 'notnull':
                    $con[] = $key. ' is not null ';
                    break;
                case 'like':
                    $con[] = $key.' like ? ';
                    $params[] = $value[1];
                    break;
                default:
                    // array 类型的
                    $tmp = '';
                    foreach ($value as $k => $v) {
                        $tmp .= ',?';
                        $params[] = $v;
                    }
                    $con[] = $key .' in ('.trim($tmp, ',').')';
                    break;
            }
        }
        return join(' and ', $con);
    }

    public function select()
    {
        $sql = $this->buildSql('select');
        return $this->exec($sql, 'read');
    }

    public function get()
    {
        $result = $this->limit(1)->select();
        return $result ? $result->fetch_row() : false;
    }

    public function getScala()
    {
        $result = $this->select();
        return $result ? $result->fetch_row()[0] : false;
    }

    public function getAll()
    {
        $result = $this->select();
        return $result ? $result->fetch_all(MYSQLI_NUM) : false;
    }

    public function getAllWithKey($key)
    {
        $result = $this->select();
        if (!$result) {
            return false;
        }

        $ret = [];
        while($row = $result->fetch_assoc()) {
            $ret[$row[$key]] = $row;
        }
        return $ret;
    }
    
    /**
     * @param $conditions
     * @param $data
     * @return bool
     */
    public function update($data, $conditions = [])
    {
        $sql = $this->data($data)->where($conditions)->buildSql('update');
        $this->exec($sql, 'write');
        return $this->_affect_rows;
    }

    /**
     * @param $data
     * @return bool
     */
    public function insert($data)
    {
        $sql = $this->data($data)->buildSql('insert');
        return $this->exec($sql, 'write');
    }

    public function delete($conditions = [])
    {
        $sql = $this->where($conditions)->buildSql('delete');
        $this->exec($sql, 'write');
        return $this->_affect_rows;
    }

    /**
     * 构建查询
     *
     * @param string $type
     * @return string
     * @throws Exception
     */
    public function buildSql($type = 'select')
    {
        switch ($type) {
            case 'select':
                $sql = 'select '. $this->_fields.' from '. $this->table. " \n";

                if ($this->_joins) {
                    foreach ($this->_joins as $join) {
                        $sql .= $join[2].' join '. $join[0]. ' on '. $join[1]. "\n";
                    }
                }

                if ($this->_conditions) {
                    $sql .= ' where ('. join(' ) and ( ', $this->_conditions) .') '." \n ";
                }

                if ($this->_order) {
                    $sql .= ' order by ';
                    foreach ($this->_order as $column => $order) {
                        $sql .= $column.' '.$order.',';
                    }
                    $sql = trim($sql, ',');
                    $sql .= "\n";
                }

                if ($this->_limit) {
                    $sql .= ' limit '. $this->_limit;
                    $sql .= "\n";
                }
                break;
            case 'update':
                $con = '';
                $params = [];
                foreach ($this->_data as $key => $value) {
                    $con = $key.'=?';
                    $params[] = $value;
                }
                $this->_params = array_merge($params, $this->_params);

                $sql = ' update '.$this->table.' set '. trim($con);
                if ($this->_conditions) {
                    $sql .= ' where ('. join(' ) and ( ', $this->_conditions) .') '." \n ";
                } else {
                    throw new Exception('禁止无条件更新');
                }
                break;
            case 'insert':
                $con = [];
                $keys = [];
                foreach ($this->_data as $key => $value) {
                    $con[] = '?';
                    $keys[] = $key;
                    $this->_params[] = $value;
                }
                $sql = 'insert into '.$this->table.' ('.join(',', $keys).') values ('. join(',', $con).')';
                break;
            case 'delete':
                $sql = 'delete from '.$this->table;
                if ($this->_conditions) {
                    $sql .= ' where ('. join(' ) and ( ', $this->_conditions) .') '." \n ";
                } else {
                    throw new Exception('禁止无条件删除');
                }
                break;
            default:
                $sql = '';
                break;
        }

        $this->_sql = $sql;
        $this->_params_bind = $this->_params;

        // 清理查询
        $this->clear();

        return $sql;
    }

    /**
     * 执行inset update delete
     *
     * @param $sql
     * @param array $params
     * @param string $mode
     * @return mysqli_result | bool
     */
    public function exec($sql, $mode = 'read')
    {
        $bindParams = [];

        // 替换查询中的 :str 别名参数
        $sql = preg_replace_callback('/:(\w+)/', function($matches){
            $bindParams[] = $this->_params_bind[$matches[1]];
            return '?';
        }, $sql);

        if (empty($sql)) {
            return false;
        }

        // 如果是按照一般的参数绑定
        if (empty($bindParams)) {
            $bindParams = $this->_params_bind;
        }

        $stmt = $this->getDb()->prepare($sql);

        if ($stmt === false) {
            $this->_last_error = $this->getDb()->error;
            return false;
        }

        if ($bindParams) {
            $type = '';
            $params = [];
            foreach ($bindParams as $key => &$value) {
                switch (gettype($value)) {
                    case 'boolean':
                        $type .= 'i';
                        $value = intval($value);
                        break;
                    case 'integer':
                        $type .= 'i';
                        break;
                    case 'double':
                        $type .= 'd';
                        break;
                    case 'string':
                        $type .= 's';
                        break;
                    default:
                        return false;
                }
                $params[] = $value;
            }
            array_unshift($params, $type);

            // 参数绑定 ref
            $ref    = new ReflectionClass('mysqli_stmt');
            $method = $ref->getMethod("bind_param");
            $method->invokeArgs($stmt, $params);
        }

        $isSuccess = $stmt->execute();

        if (!$isSuccess) {
            $this->_last_error = $this->getDb()->error;
            return false;
        }

        $this->_affect_rows = $stmt->affected_rows;

        if ($mode != 'read') {
            return $isSuccess;
        }

        $result = $stmt->get_result();
        return $result;
    }
}