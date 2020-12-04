<?php 

/**
 * Database Main Abstraction Layer
 * This will handle all database query and manipulation
 * 
 * @package friendsher
 */

 // Include the query builder exception
 require_once dirname(__FILE__) . '/Exception/QueryBuilderException.php';

 class QueryBuilder {

    /**
     * $this->connection (PDO/MYSQL)
     */
    private $connection;

    /**
     * $driver (PDO/MYSQL)
     */
    private $driver;

    /**
     * stored queried string in this variable
     */

    private $query;

    /** 
     * Does raw statement?
     */

    private $doesRaw;

    /**
     * Check if query is closed
     */

    private $query_closed = FALSE;

    /**
     * Query bindings
     */

    private $bind = '';

    /**
     * Query array binding values
     */

    private $bindingArrays;

    /**
     * Does where callable?
     */

    private $doesCallable;

    /**
     * Bindings array 
     */

    private $bindings = [
        'select' => false,
        'update' => false
    ];

    /**
     * Data from get 
     * 
     * @var array
     */

    protected $data = array();

     /**
     * A list of incompatible SQL modes.
     */

    private $incompatible_modes = [
        'NO_ZERO_DATE',
        'ONLY_FULL_GROUP_BY',
        'STRICT_TRANS_TABLES',
        'STRICT_ALL_TABLES',
        'TRADITIONAL',
        'ANSI',
    ];

    /**
     * Database array
     */

    private $database_array;

    /**
     * Initialize the constructor
     * @param array $database info
     */

    public function __construct( $database ) {

        if($database['driver'] === 'mysql') {

            $this->connection = new mysqli(
                $database['host'],
                $database['username'],
                $database['password'],
                $database['database'],
                $database['port']
            );

            if($this->connection->connect_error) {

                throw new QueryBuilderException('MYSQL Database Error : ' . $this->connection->connect_error);

            }

            $this->connection->set_charset($database['charset']);

        } else if($database['driver'] === 'pdo') {

            try {

                $this->connection = new PDO(
                    sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    $database['host'],
                    $database['port'],
                    $database['database'],
                    $database['charset'] ),
                    $database['username'],
                    $database['password']
                );

                $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

            } catch(PDOException $e) {
                
                throw new QueryBuilderException('PDO Connection Error : ' . $e->getMessage());

            }

        } else {

            throw new QueryBuilderException("Driver Error","Database driver is not defined");

        }

        // Assign database array
        $this->database_array = $database;

        // Offset of timezone
        $offset = self::getOffset( $database['timezone'] );

        $this->driver = $database['driver'];
        $this->connection->query("SET time_zone = '$offset' ");

        // Set for the first time 
        // If sql mode is not set
        if(isset($database['sql_mode']) && !empty($database['sql_mode'])) {
            $this->set_sql_mode($database['sql_mode']);
        } else {
            $this->set_sql_mode();
        }
        
    }

     /**
     * Changes the current SQL mode, and ensures its FQB compatibility.
     *
     * If no modes are passed, it will ensure the current MySQL server modes are compatible.
     *
     * @param array $modes Optional. A list of SQL modes to set. Default empty array.
     * 
     * @return boolean true on Success
     */

    public function set_sql_mode($sql_mode = array()){
        
        if( empty ($sql_mode) ) {
            $result = array();
            $conn = $this->connection->query('SELECT @@SESSION.sql_mode');

            if($this->driver == 'mysql') {
                while($row = $conn->fetch_assoc()) {
                    $result[] = $row;
                }
            } else {
                $result = $conn->fetchAll(\PDO::FETCH_ASSOC);
            }

            $modes = explode(',' , array_shift($result[0]));
        } else {
            $modes = $sql_mode;
        }

        $modes = array_change_key_case( $modes, CASE_UPPER );

        if(isset($modes) && !empty($modes)) {

            foreach($modes as $k => $mode) {

                if(in_array($mode,$this->incompatible_modes)) {
                    unset($modes[$k]);
                }

            }

            $modes_str = implode(',' , $modes);
            $this->connection->query("SET SESSION sql_mode='$modes_str'");

            return true;
        } 

        return false;
    }

    /**
     * getOffset
     * get offset of timezone
     * 
     * @return int offset of timezone
     */

    private static function getOffset($timezone) {
		date_default_timezone_set($timezone);
		$now = new DateTime();
		$mins = $now->getOffset() / 60;
		$sgn = ($mins < 0 ? -1 : 1);
		$mins = abs($mins);
		$hrs = floor($mins / 60);
		$mins -= $hrs * 60;
		return sprintf("%+d:%02d",$hrs * $sgn, $mins);
	}

    /**
     * Close database conenction
     * Function close the database connection
     * 
     * @return mixed (void,boolean)
     */

    public function close() {
        
        if($this->driver == 'pdo') {
            return $this->connection = null;
        } else if($this->driver == 'mysql') {
            return $this->connection->close();
        } else {
            throw new QueryBuilderException("Driver Error : Database driver is not defined");
        }

    }

    /**
     * Query the database
     * This will be first call
     * 
     * @param string a query_string
     * @param array a binding_array (supplied data)
     * 
     * @return object (this class object)
     */

    public function query( $query_string , $bindArray = array() ) {

        if( $this->query_closed && $this->driver == 'mysql') {
            $this->query->close();
        }

        // before execute
        // a new query
        // Be sure to flush
        // it first
        $this->flush();

        if( $this->query = $this->connection->prepare($query_string) ) {

            if(!empty($bindArray)) {

                if(is_array($bindArray)) {
    
                    if($this->driver == 'pdo') {
    
                        foreach($bindArray as $key => $value) {
                            $this->query->bindValue(($key + 1), $value);
                        }
        
                    } else if($this->driver == 'mysql') {
        
                        $data_type = '';

                        foreach($bindArray as $bind) {
                            $data_type .= $this->get_data_type($bind);
                        }
            
                        array_unshift($bindArray,$data_type);
                        $this->query->bind_param(...$bindArray);
        
                    }
    
                }
    
            }

            $this->query->execute();
            $this->query_closed = TRUE;
                
            if($this->driver == 'mysql' && $this->query->errno) {
                throw new QueryBuilderException('MYSQL Database error : Unable to process MySQL query (check your params) - ' . $this->query->error);
            } else if( $this->driver == 'pdo' && ! in_array( '00000', $this->connection->errorInfo() ) ) {
                throw new QueryBuilderException('PDO Database error : Unable to process PDO query (check your params) - '. implode(' ', $this->connection->errorInfo() ) );
            }

        } else {

            if( $this->driver == 'mysql' ) {
                throw new QueryBuilderException('Unable to prepare MySQL statement (check your syntax) - ' . $this->connection->error);
            }
        }

        return $this;
    }

    /**
     * QueryRaw (Warning : vulnerable from sql injection use query(@param) instead)
     * 
     * @param string a query string
     * 
     * @return boolean true/false
     */

    public function queryRaw( $query_string ) {

        if( $this->query_closed && $this->driver == 'mysql') {
            $this->query->close();
        }

        $this->query = $this->connection->query($query_string);
        $this->doesRaw = true;
        $this->query_closed = TRUE;

        return $this;
    }

    /**
     * Fetch from select database
     * 
     * @return array (from database data)
     */

    public function get( $callback = NULL ) {
      
        if($this->bindings['select']) {

            if(!empty($bindingArrays)) {
                $bind_array = call_user_func_array('array_merge', $this->bindingArrays);
                $this->query($this->bind,$bind_array);
            } else {
                $this->query($this->bind);
            }
          
        }

        if($this->driver == 'pdo') {

            $this->data = $this->query->fetchAll(\PDO::FETCH_ASSOC);

        } else {

            if($this->doesRaw) {
                $result = $this->query;
            } else {
                $result = $this->query->get_result();
            }

            if(isset ( $result->num_rows ) && $result->num_rows > 0) { 
                while ($row = $result->fetch_assoc()) {
                    $this->data[] = $row;
                }
            }

        }

        if(! is_null($callback) && is_callable($callback)) {
            call_user_func($callback, $this->data);
        } else {
            return $this->data;
        }

    }  

    /**
     * Last inserted Id
     * 
     * @return int (last increment id)
     */

    public function last_id() {

        if($this->driver == 'pdo') {
            return $this->connection->lastInsertId();
        } elseif($this->driver == 'mysql') {
            return $this->connection->insert_id;
        } else {
            throw new QueryBuilderException("Driver Error : Database driver is not defined");
        }

    }

    /**
     * Find by column name
     * 
     * @param string column name
     * @param mixed column value
     * 
     * @return array retrieve from db with a condition
     */

    public function find( $table, $column_name , $column_value ) {

        $this->query(sprintf('SELECT * FROM %s WHERE %s = ? ', $table , $column_name), [
            $column_value
        ]);

        return $this;
    }

    /**
     * Escape string
     * 
     * @param string unscaped string
     * 
     * @return string escaped string
     */

    public function escape( $string ) {

        if($this->driver == 'mysql') {
            return $this->connection->real_escape_string($string);
        }

        return '';
    }

    /**
     * Pluck Column
     * 
     * Fetch selected column (in array)
     * 
     * @param string colum name
     * 
     * @return array (Column name data rows)
     */

    public function pluck( $column_name ) {

        $array = $this->get();
        $data = array();

        foreach($array as $k => $v) {
            $data[] = $v[$column_name];
        }

        return $data;
    }

    /**
     * Get First rows
     * 
     * @return array (Filtered rows)
     */

    public function first() {
        $first = $this->get();
        $shift = array_shift($first);
        return $shift;
    }

    /**
     * Get Latest rows
     * 
     * @return array (Filtered rows)
     */

    public function latest() {
        $last = $this->get();
        $pop = array_pop($last);
        return $pop;
    }

    /**
     * Insert rows data
     * 
     * @param string table name
     * @param array columns
     * 
     * @return boolean true on success
     */

    public function insert($table,$columns) {
        $column_name = implode(',',array_keys($columns));
        $column_val = rtrim( str_repeat(' ? ,', count($columns) ) , ',' );
        $this->query( sprintf('INSERT INTO %s (%s) VALUES (%s)',$table , $column_name, $column_val) ,
            array_values($columns)
        );
        return $this->has_error();
    }

    /**
     * Select Binding query
     * 
     * @param array selection param
     * 
     * @return object (the binding from selection)
     */

    public function select( $array = ['*'] ) {
        $this->bindings['select'] = true;
        $this->bind = 'SELECT ' . implode(' , ' , $array);
        return $this;
    }

    /**
     * From Binding query
     * 
     * @param string table name
     * 
     * @return object (the binding object)
     */

    public function from($table) {
        $this->bind .= " FROM $table ";
        return $this;
    }

    /** 
     * Where Binding query
     * 
     * @param array (Array(key,value))
     * 
     * @return object (the bnding from where)
     */

    public function where($wheres , $prefix = 'AND') {

       if(is_callable($wheres)) {
           $this->bind .= " $prefix (";
           $this->doesCallable = TRUE;
           call_user_func($wheres,$this);
       } else {

            if(!$this->doesCallable) {
                $this->bind .= ' WHERE ';
            }
          
            if( count($wheres) == count($wheres, COUNT_RECURSIVE)) {
                $end = end($wheres);
                $k = array_search($end,$wheres);
                $pop[] = $end;
                $wheres[$k] = '?';
                $this->bind .= implode(' ' , $wheres );
            } else {
                $query_string = '';
                foreach($wheres as $where) {
                    $end = end($where);
                    $k = array_search($end,$where);
                    $pop[] = $end;
                    $where[$k] = '?';
                    $join_where = implode(' ' , $where );
                    $query_string .= $join_where . " $prefix ";
                }
                $this->bind .= rtrim($query_string,"$prefix ");
            }
            $this->bindingArrays[] =  $pop;

            if($this->doesCallable) {
                $this->bind .= ')';
            }
        }

       return $this;
    }

    /**
     * And Where Query
     * 
     * @param array binding array
     * 
     * @return object (this object class)
     */

    public function andWhere( $wheres ) {

        $this->bind .= ' AND ';

        if( count($wheres) == count($wheres, COUNT_RECURSIVE)) {
            $end = end($wheres);
            $k = array_search($end,$wheres);
            $pop[] = $end;
            $wheres[$k] = '?';
            $this->bind .= implode(' ' , $wheres );
        } else {
            $query_string = '';
            foreach($wheres as $where) {
                $end = end($where);
                $k = array_search($end,$where);
                $pop[] = $end;
                $where[$k] = '?';
                $join_where = implode(' ' , $where );
                $query_string .= $join_where . " $prefix ";
            }
            $this->bind .= rtrim($query_string,"$prefix ");
        }
        $this->bindingArrays[] =  $pop;
        return $this;
    }

       /**
     * OR Where Query
     * 
     * @param array binding array
     * 
     * @return object (this object class)
     */

    public function orWhere( $wheres ) {

        $this->bind .= ' OR ';

        if( count($wheres) == count($wheres, COUNT_RECURSIVE)) {
            $end = end($wheres);
            $k = array_search($end,$wheres);
            $pop[] = $end;
            $wheres[$k] = '?';
            $this->bind .= implode(' ' , $wheres );
        } else {
            $query_string = '';
            foreach($wheres as $where) {
                $end = end($where);
                $k = array_search($end,$where);
                $pop[] = $end;
                $where[$k] = '?';
                $join_where = implode(' ' , $where );
                $query_string .= $join_where . " $prefix ";
            }
            $this->bind .= rtrim($query_string,"$prefix ");
        }
        $this->bindingArrays[] =  $pop;
        return $this;
    }

    /**
     * Flush Cache
     * 
     * Clear Cache for the previous execution
     * 
     * @return void Nothing
     */

    public function flush() {

        // reset this bindings
        $this->bindings = [
            'select' => false,
            'update' => false
        ];

        // Reset the data store
        // By get if the query is
        // called
        $this->data = array();

        // Reset the bindings value
        $this->bindingArrays = array();

        // Reset the binding string
        $this->bind = '';
    }

    /**
     * Update rows data
     * 
     * @param string table name
     * @param array columns to update
     * 
     * @return boolean true on success
     */

    public function update($table,$columns) {

        $query_array = array();
        foreach($columns as $name => $value) {
            $query_array[] = sprintf('%s = ?',$name);
        }

        $query_string = implode(' , ',$query_array);

        if(!empty($this->bindingArrays)) {
            $flatten = call_user_func_array('array_merge', $this->bindingArrays);
            $this->query( sprintf('UPDATE %s SET %s %s' , $table, $query_string, $this->bind) , array_merge( array_values($columns) , array_values($flatten) ) );
        } else {
            $this->query(sprintf('UPDATE %s SET %s' , $table, $query_string), array_values($columns));
        }

        $this->bindingArrays = array();

        return $this->has_error();
    }

    /**
     * Delete rows data
     * 
     * @return boolean true on success
     */

    public function delete() {

        if(!empty($this->bindingArrays)) {
            $flatten = call_user_func_array('array_merge', $this->bindingArrays);
            $this->query( sprintf('DELETE %s ', $this->bind ) , array_values($flatten) );
        } else {
            $this->query( sprintf('DELETE %s ', $this->bind ) );
        }

        $this->bindingArrays = array();

        return $this->has_error();
    }

    /**
     * Offset (start index from)
     * 
     * @param int start index
     * @param int end index
     * 
     * @return array filtered array
     */

    public function offset( $from = null, $to = null ) {

        $data = $this->get();
        $arr = array();

        if( ! is_null($to) && ! is_null($from) ) {

            $arr = array_slice($data,$from,null,true);
            $arr = array_slice($arr,-$to,null,true);

        } elseif( ! is_null($from) ) {

            $arr = array_slice($data,$from,null,true);

        } elseif( ! is_null($to) ) {

            $arr = array_slice($data,-$to,null,true);

        } else {

            $arr = $data;

        }

        return $arr;
       
    }

    /**
     * Reverse Collection
     * 
     * @param boolean Preversed key (default is true)
     * 
     * @return array Reversed Arrays
     */

    public function reverse( $preserve_keys = TRUE) {
        return array_reverse($this->get() , $preserve_keys);
    }

    /**
     * Shuffle Array
     * 
     * @return array shuffled array
     */

    public function shuffle() {
        $data = $this->get();
        shuffle($data);
        return $data;
    }

    /**
     * Count rows
     * 
     * @return int counted arrays
     */

    public function count() {
        return count($this->get());
    }

    /**
     * Sum Add column value from selected column name
     * 
     * @param string column name
     * 
     * @return mixed the sum of value
     */

    public function sum( $column_name ) {
        $sum = 0;
        foreach($this->get() as $k => $w) {
            if(is_numeric($w[$column_name])) {
                $sum += $w[$column_name];
            }
        }
        return $sum;
    }

    /**
     * Max
     * 
     * @param string column name
     * 
     * @return int (max)
     */

    public function max( $column_name ) {
        $data = array();
        foreach($this->get() as $k => $v) {
            if(is_numeric($v[$column_name])) {
                $data[] = $v[$column_name];
            }
        }
        return max($data);
    }

    /**
     * Avg
     * 
     * @param string column name
     * @param boolean empty values
     * 
     * @return int average
     */

    public function avg( $column_name , $empty_values = FALSE) {
        $data = array();
        foreach($this->get() as $k => $w) {
            if( is_numeric ($w[$column_name]) ) {
                $data[] = $w[$column_name];
            }
        }
        $array_count = $empty_values ? $data : array_filter($data);
        return (array_sum($data) / count($array_count));
    }

    /**
     * Does Exist ?
     * 
     * @param object callable function
     * @param int flags 
     * 
     * @return boolean true on success
     */

    public function exists() {
        return ($this->count() > 0);
    }

    /**
     * Filter
     * 
     * @return array (the filtered array)
     */

    public function filter( $callback , $flags = 0 ) {
        return array_filter($this->get() , $callback , $flags);
    }

    /**
     * Chunk
     * 
     * @param int the chunk size 
     * @param boolean flags (preversed keys)
     * @param object (function callback ) use if defined
     * 
     * @return array the chunk array
     */

    public function chunk($size, $flags = TRUE, $callback = NULL) {
        $chunk_array = array_chunk($this->get() , $size , $flags);
        if( is_callable($callback) ) {
            call_user_func($callback,$chunk_array);
        }
        return $chunk_array;
    }

    /**
     * Sort by 
     * 
     * @param boolean (Ascending Order)
     * 
     * @return array sorted arrays
     */

    public function sort( $acsending = TRUE ) {
        $data = $this->get();
        if($acsending) {
            sort($data);
        } else {
            rsort($data);
        }
        return $data;
    }

    /**
     * Pop by value and/or key
     * 
     * @param string column name
     * @param string|int key and/or value
     * 
     * @return array the filtered array
     */

    public function pop( $column_name , $pop_str ) {
        $data = $this->get();
        $column = array_column($data , $column_name );
        $search = array_search( $pop_str , $column );
        if($search !== false) {
            unset($data[$search]);
        }
        return $data;
    }

    /**
     * Object (Convert Assoc array to object)
     * 
     * @return object (object from array)
     */

    public function toObject() {
        return convert_arr_to_obj($this->get() , true);
    }

    /**
     * To JSON (return to json encoded)
     * 
     * @param int flags (JSON Encode Flags)
     * 
     * @return string (the json encoded)
     */

    public function toJson( $flags = 0 ) {
        return json_encode($this->get() , $flags);
    }
    
    /**
     * Serialize Array
     * 
     * @return string (Serialized array)
     */

    public function serialize() {
        return serialize($this->get());
    }

    /**
     * Distinct (Remove duplicate values)
     * 
     * @return array (filtered array (Remove duplicates))
     */

    public function distinct() {
        $serialized = array_map('serialize', $this->get() );
        $unique = array_unique($serialized);
        return array_intersect_key($this->get(), $unique);    
    }

    /**
     * Affected Rows
     * 
     * @return int (Affected rows from (INSERT,DELETE,UPDATE))
     */

    public function affected_rows() {
        return (intval(
                $this->driver == 'mysql' ? 
                $this->connection->affected_rows : 
                $this->connection->rowCount()
        ));
    }

    /**
     * Truncate Table
     *
     * @param string table name
     * 
     * @return boolean TRUE on success
     */

    public function truncate($table) {
        return $this->query('TRUNCATE TABLE ' . $table)->has_error();
    }

    /**
     * DROP Table
     * 
     * @param string table name
     * 
     * @return boolean TRUE on success
     */

    public function drop($table) {
        return $this->query('DROP TABLE ' . $table)->has_error();
    }

    /**
     * IN the rows
     *
     * @param string (column name)
     * @param array (search for this array)
     * 
     * @return boolean (true has given array else false)
     */

    public function in( $column_name , $search ) {
        $ret = false;
        foreach($search as $k) {
            if(in_array($k,$this->pluck($column_name))) {
                $ret = true;
            }
        }
        return $ret;
    }

    /**
     * LIKE (search for a specified pattern in a column.)
     * 
     * @param array (array key(column_name) && value(needle))
     * @param boolean use AND => TRUE / OR => FALSE
     * @param int search method (0 => ANY position , 1 => First position , 2 => Last position)
     * @param string hasWhere Operator  (If WHERE is exist then you can defined the operator (OR/AND))
     * 
     * @return array (filtered array)
     */

    public function like( $search , $use_and = TRUE, $position = 0 , $hasWhereOP = 'AND') {

        $hasWhere = false;
        if( stripos($this->bind, trim(' WHERE ')) !== FALSE ) {
            $hasWhere = true;
        } 
        
        $toQuery = array();
        if (count($search) == count($search, COUNT_RECURSIVE)) {
            
            $key = implode('',array_keys($search));
            if($position == 0) {
                $this->bindingArrays[] = array_values(array_map(function($v) { return '%' . $v . '%'; },$search));
            } elseif($position == 1) {
                $this->bindingArrays[] = array_values(array_map(function($v) { return '%' . $v; },$search));
            } else {
                $this->bindingArrays[] = array_values(array_map(function($v) { return $v . '%'; },$search));
            }

            $toQuery[] = $key . ' LIKE ?';
        } else{
            foreach($search as $k => $v) {

                $key = implode('',array_keys($v));
                if($position == 0) {
                    $this->bindingArrays[] = array_values(array_map(function($v) { return '%' . $v . '%'; },$v));
                } elseif($position == 1) {
                    $this->bindingArrays[] = array_values(array_map(function($v) { return '%' . $v; },$v));
                } else {
                    $this->bindingArrays[] = array_values(array_map(function($v) { return $v . '%'; },$v));
                }

                $toQuery[] = $key . ' LIKE ?';
            }
        }

        $operator = $use_and ? ' AND ' : ' OR ';
        $toQueryString =  ( $hasWhere ? " $hasWhereOP " : ' WHERE ' ) . '(' . implode($operator , $toQuery) . ')';
        $this->bind .= $toQueryString;
        $flatten = call_user_func_array('array_merge', $this->bindingArrays);

        return $this->query($this->bind,$flatten);
    }

    /**
     * Limit By
     * 
     * @param int start
     * @param int end
     * 
     * @return object (this object field)
     */

    public function limitBy( $start = 0, $end = 0 ) {
        if($end === 0) {
            $this->bind .= sprintf(' LIMIT %d ' , $start);
        } else {
            $this->bind .= sprintf(' LIMIT %d , %d', $start , $end);
        }
        return $this;
    }

    /**
     * Order By
     * 
     * @param string column name
     * @param string ASC/DESC (The order)
     * 
     * @return object (this object field)
     */

    public function orderBy( $column_name , $order = 'ASC' ) {
        $this->bind .= sprintf('ORDER BY %s %s' , $column_name , $order);
        return $this;
    }

    /** 
     * Join table (Private)
     * 
     * @param string type of join
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    private function _join($type = '' , $table , $array , $separator = 'AND') {
        $wildcard = array();
        foreach($array as $k => $v) {
            $wildcard[$k] = implode(' = ', $v);
        }
        $rejoin = implode( " $separator " , $wildcard );
        $this->bind .= sprintf(' %s JOIN %s ON %s ',$type,$table,$rejoin);
        return $this;
    }

    /** 
     * INNER Join
     * 
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    public function innerJoin($table, $array , $separator = 'AND') {
        return $this->_join( 'INNER' , $table , $array , $separator );
    }

    /** 
     * LEFT Join
     * 
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    public function leftJoin($table, $array , $separator = 'AND') {
        return $this->_join( 'LEFT' , $table , $array , $separator );
    }

    /** 
     * RIGHT Join
     * 
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    public function rightJoin($table, $array , $separator = 'AND') {
        return $this->_join( 'RIGHT' , $table , $array , $separator );
    }

    /** 
     * FULL outer Join
     * 
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    public function fullOuterJoin($table, $array , $separator = 'AND') {
        return $this->_join( 'FULL OUTER' , $table , $array , $separator );
    }

    /**
     * Group By
     * 
     * @param string column name
     * 
     * @return object this field
     */

    public function groupBy($column_name) {
        $this->bind .= ' GROUP BY ' . $column_name;
        return $this;
    }

    /**
     * Having
     * 
     * @param string condition
     * 
     * @return object this field
     */

    public function having($condition) {
        $this->bind .= ' HAVING ' . $condition;
        return $this;
    }

    /**
     * Where IN
     * 
     * @param string column name
     * @param mixed (Array to search)
     * @param boolean doesUseSelectCondition
     * 
     * @return object this field
     */

    public function whereIn($column_name, $toSearch, $doesSelect = FALSE) {

        if(! $doesSelect) {
            $this->bind .= ' WHERE ' . $column_name . ' IN ( ' . rtrim(str_repeat('?,',count($toSearch)),',') . ' )';
            $this->bindingArrays[] = array_values($toSearch);
        } else {
            $this->bind .= ' WHERE ' . $column_name . ' IN ( ' . $toSearch . ' )';
        }
      
        return $this;
    }

    /**
     * Where NOT IN
     * 
     * @param string column name
     * @param mixed (Array to search)
     * @param boolean doesUseSelectCondition
     * 
     * @return object this field
     */

    public function whereNotIn($column_name, $toSearch, $doesSelect = FALSE) {

        if(! $doesSelect) {
            $this->bind .= ' WHERE ' . $column_name . ' NOT IN ( ' . rtrim(str_repeat('?,',count($toSearch)),',') . ' )';
            $this->bindingArrays[] = array_values($toSearch);
        } else {
            $this->bind .= ' WHERE ' . $column_name . ' NOT IN ( ' . $toSearch . ' )';
        }
      
        return $this;
    }

    /**
     * Min 
     * 
     * @param string column name
     * 
     * @return int (min int of column name)
     */

    public function min($column_name) {
        return intval(min($this->pluck($column_name)));
    }

    /**
     * Where Between
     * 
     * @param string column name
     * @param array condition
     * 
     * @return object this field object
     */

    public function whereBetween($column_name,$array) {
        $this->bind .= ' WHERE ' . $column_name . ' BETWEEN ';

        if(is_array($array)) {
            $store = '';
            foreach($array as $k) {
               $store .= $k . ' ';
            }
            $this->bind .= $store;
        } else {
            $this->bind .= " $array";
        }

        return $this;
    }

    /**
     * Where NOT Between
     * 
     * @param string column name
     * @param array condition
     * 
     * @return object this field object
     */

    public function whereNotBetween($column_name,$array) {
        $this->bind .= ' WHERE ' . $column_name . ' NOT BETWEEN ';
        if(is_array($array)) {
            $this->bind .= implode(' ' , $array);
        } else {
            $this->bind .= " $array";
        }
        return $this;
    }
    
    /**
     * Load all rows data 
     * 
     * @param string table name
     * 
     * @return array (all rows data from a table)
     */

    public function all($table) {
        return $this->select()->from($table)->get();
    }


    /** 
     * JOIN only
     * 
     * @param string table name
     * @param string array to join
     * @param string separator
     * 
     * @return object this field
     */

    public function join($table, $array , $separator = 'AND') {
        return $this->_join( '' , $table , $array , $separator );
    }

    /**
     * Get data type
     * 
     * string,integer,boolean,etc
     * 
     * @return string data type short
     */

    protected function get_data_type( $variable ) {
        if (is_string($variable)) return 's';
	    if (is_float($variable)) return 'd';
	    if (is_int($variable)) return 'i';
	    return 'b';
    }

    /**
     * Has Error
     * 
     * @return boolean true on failure
     */

    public function has_error() {
        return $this->query;
    }

    /**
     * Dump Database tables
     * 
     * @param array tables ( specific table )
     * @param string backup name (otherwise it will generate random name)
     * 
     * @return string contents (it will download .sql file)
     * 
     * Originally this script fork from https://github.com/ttodua/useful-php-scripts
     * Credits to the author of this script
     * 
     * The orignal link can be found here https://github.com/ttodua/useful-php-scripts/blob/master/my-sql-export%20(backup)%20database.php
     * 
     * I made little changes for this scripts
     */

    public function dump( $tables = false , $backup_name = false) { 
        set_time_limit(3000); 
        $name = $this->database_array['database'];
        $mysqli = new mysqli($this->database_array['host'],$this->database_array['username'],$this->database_array['password'],$name); 
        $mysqli->select_db($this->database_array['database']); 
        $charset = $this->database_array['charset'];
        $mysqli->query("SET NAMES '$charset'");

        $queryTables = $mysqli->query('SHOW TABLES'); 
        while($row = $queryTables->fetch_row()) { 
            $target_tables[] = $row[0]; 
        }	
        
        if($tables !== false) { 
            $target_tables = array_intersect( $target_tables, $tables);
        } 

        $content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `".$name."`\r\n--\r\n\r\n\r\n";
        foreach($target_tables as $table){
            if (empty($table)){ 
                continue; 
            } 

            $result	= $mysqli->query('SELECT * FROM `'.$table.'`');  	
            $fields_amount=$result->field_count;  
            $rows_num=$mysqli->affected_rows; 	
            $res = $mysqli->query('SHOW CREATE TABLE '.$table);	
            $TableMLine=$res->fetch_row(); 

            $content .= "\n\n".$TableMLine[1].";\n\n";   
            $TableMLine[1] = str_ireplace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `',$TableMLine[1]);

            for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) {
                while($row = $result->fetch_row())	{ //when started (and every after 100 command cycle):
                    if ($st_counter%100 == 0 || $st_counter == 0 )	{
                        $content .= "\nINSERT INTO ".$table." VALUES";
                    }
                    $content .= "\n(";    
                    for($j=0; $j<$fields_amount; $j++){ 
                        $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                        if (isset($row[$j])){
                            $content .= '"'.$row[$j].'"' ;
                        }  else {
                            $content .= '""';
                        }	   
                        
                        if ($j<($fields_amount-1)){
                            $content.= ',';
                        }   
                    }        
                    
                    $content .=")";

                    //every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
                    if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) {
                        $content .= ";";
                    } else {
                        $content .= ",";
                    }	
                    
                    $st_counter = $st_counter+1;
                }
            } $content .="\n\n\n";
        }

        $content .= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
        $backup_name = $backup_name ? $backup_name : $name.'___('.date('H-i-s').'_'.date('d-m-Y').').sql';
        ob_get_clean();
        header('Content-Type: application/octet-stream');  
        header("Content-Transfer-Encoding: Binary");  
        header('Content-Length: '. (function_exists('mb_strlen') ? mb_strlen($content, '8bit') : strlen($content)) );    
        header("Content-disposition: attachment; filename=\"".$backup_name."\""); 
        echo $content; 
        exit;
    }

 }