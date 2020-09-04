<?php

namespace PluginEver\QueryBuilder;

defined( 'ABSPATH' ) || exit();

class Query {
	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var array
	 */
	protected $select = [];

	/**
	 * @var null
	 */
	protected $from = null;

	/**
	 * @var array
	 */
	protected $join = [];

	/**
	 * @var array
	 */
	protected $where = [];

	/**
	 * @var array
	 */
	protected $order = [];

	/**
	 * @var array
	 */
	protected $group = [];

	/**
	 * @var null
	 */
	protected $having = null;

	/**
	 * @var null
	 */
	protected $limit = null;

	/**
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * Static constructor.
	 *
	 *
	 * @since 1.0.0
	 *
	 */
	public static function init( $id = null ) {
		$builder     = new self();
		$builder->id = ! empty( $id ) ? $id : uniqid();

		return $builder;
	}

	/**
	 * Adds select statement.
	 *
	 * @param $statement
	 *
	 * @return $this
	 * @since 1.0.0
	 */
	public function select( $statement ) {
		$this->select[] = $statement;

		return $this;
	}

	/**
	 * Adds from statement.
	 *
	 * @param string $name
	 * @param bool $add_prefix
	 *
	 * @since 1.0.0
	 *
	 */
	public function table( $name, $add_prefix = true ) {
		global $wpdb;
		$table      = ( $add_prefix ? $wpdb->prefix : '' ) . $name;
		$this->from = $table;

		return $this;
	}

	/**
	 * Adds from statement.
	 *
	 * @param string $from
	 * @param bool $add_prefix Should DB prefix be added.
	 *
	 * @return Query this for chaining.
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	public function from( $from, $add_prefix = true ) {
		global $wpdb;
		$this->from = $this->from . ' ' . ( $add_prefix ? $wpdb->prefix : '' ) . $from;

		return $this;
	}

	/**
	 * Adds search statement.
	 *
	 * @param $search
	 * @param $columns
	 * @param $joint
	 *
	 * @since 1.0.0
	 */
	public function search( $search, $columns, $joint = 'AND' ) {
		if ( ! empty( $search ) ) {
			global $wpdb;
			foreach ( explode( ' ', $search ) as $word ) {
				$word          = '%' . $this->sanitize_value( true, $word ) . '%';
				$this->where[] = [
					'joint'     => $joint,
					'condition' => '(' . implode( ' OR ', array_map( function ( $column ) use ( &$wpdb, &$word ) {
							return $wpdb->prepare( $column . ' LIKE %s', $word );
						}, $columns ) ) . ')',
				];
			}
		}

		return $this;
	}

	/**
	 * Create a where statement.
	 *
	 *     ->where('name', 'sultan')
	 *     ->where('age', '>', 18)
	 *     ->where('name', 'in', array('ayaan', 'ayaash', 'anaan'))
	 *        ->where(function($q){
	 *       $q->where('ID', '>', 21);
	 * })
	 *
	 * @param string $column The SQL column
	 * @param mixed $param1 Operator or value depending if $param2 isset.
	 * @param mixed $param2 The value if $param1 is an operator.
	 * @param string $joint the where type ( and, or )
	 *
	 * @return Query The current query builder.
	 */
	public function where( $column, $param1 = null, $param2 = null, $joint = 'and' ) {
		global $wpdb;
		if ( ! in_array( strtolower( $joint ), [ 'and', 'or', 'where' ] ) ) {
			$this->exception( 'Invalid where type "' . $joint . '"' );
		}

		// when column is an array we assume to make a bulk and where.
		if ( is_array( $column ) ) {
			// create new query object
			$subquery = new Query();
			foreach ( $column as $key => $val ) {
				$subquery->where( $key, $val, null, $joint );
			}

			$this->where = array_merge( $this->where, $subquery->where );

			return $this;
		}

		if ( is_object( $column ) && ( $column instanceof \Closure ) ) {
			// create new query object
			$subquery = new Query();

			// run the closure callback on the sub query
			call_user_func_array( $column, array( &$subquery ) );
			$condition = '';
			for ( $i = 0; $i < count( $subquery->where ); ++ $i ) {
				$condition .= ( $i === 0 ? ' ' : ' ' . $subquery->where[ $i ]['joint'] . ' ' )
				              . $subquery->where[ $i ]['condition'];
			}

			$this->where = array_merge( $this->where, array(
				array(
					'joint'     => $joint,
					'condition' => "($condition)"
				)
			) );

			return $this;
		}

		// when param2 is null we replace param2 with param one as the
		// value holder and make param1 to the = operator.
		// However, if param1 is either 'is' or 'is not' we need to leave param2 as null.
		// This is used for the whereNull() and whereNotNull() methods.
		if ( is_null( $param2 ) && ! in_array( $param1, [ 'is', 'is not' ], true ) ) {
			$param2 = $param1;
			$param1 = '=';
		}

		// if the param2 is an array we filter it. when param2 is an array we probably
		// have an "in" or "between" statement which has no need for duplicates.
		if ( is_array( $param2 ) ) {
			$param2 = array_unique( $param2 );
		}

		// Between?
		if ( is_array( $param2 ) && strpos( $param1, 'BETWEEN' ) !== false ) {
			$min = isset( $param2[0] ) ? $param2[0] : false;
			$max = isset( $param2[1] ) ? $param2[1] : false;
			if ( ! $min || ! $max ) {
				$this->exception( "BETWEEN min or max is missing" );
			}

			$min = $wpdb->prepare( is_numeric( $min ) ? '%d' : '%s', $min );
			$max = $wpdb->prepare( is_numeric( $max ) ? '%d' : '%s', $max );

			$this->where[] = [
				'joint'     => $joint,
				'condition' => "($column BETWEEN $min AND $max)",
			];

			return $this;
		}

		// Not Between?
		if ( is_array( $param2 ) && strpos( $param1, 'NOT BETWEEN' ) !== false ) {
			$min = isset( $param2[0] ) ? $param2[0] : false;
			$max = isset( $param2[1] ) ? $param2[1] : false;
			if ( ! $min || ! $max ) {
				$this->exception( "NOT BETWEEN min or max is missing" );
			}

			$min = $wpdb->prepare( is_numeric( $min ) ? '%d' : '%s', $min );
			$max = $wpdb->prepare( is_numeric( $max ) ? '%d' : '%s', $max );

			$this->where[] = [
				'joint'     => $joint,
				'condition' => "($column NOT BETWEEN $min AND $max)",
			];

			return $this;
		}


		//first check if is array if so then make a string out of array
		//if not array but null then set value as null
		//if not null does it contains . it could be column so dont parse as string
		//If not column then use wpdb prepare
		//if contains $prefix
		$contain_join = preg_replace( '/^(\s?AND ?|\s?OR ?)|\s$/i', '', $param2 );

		$param2 = is_array( $param2 ) ? ( '("' . implode( '","', $param2 ) . '")' ) : ( $param2 === null
			? 'null'
			: ( strpos( $param2, '.' ) !== false || strpos( $param2, $wpdb->prefix ) !== false ? $param2 : $wpdb->prepare( is_numeric( $param2 ) ? '%d' : '%s', $param2 ) )
		);

		$this->where[] = [
			'joint'     => $joint,
			'condition' => implode( ' ', [ $column, $param1, $param2 ] ),
		];

		return $this;
	}


	/**
	 * Create an or where statement
	 *
	 * This is the same as the normal where just with a fixed type
	 *
	 * @param string $column The SQL column
	 * @param mixed $param1
	 * @param mixed $param2
	 *
	 * @return Query The current query builder.
	 */
	public function orWhere( $column, $param1 = null, $param2 = null ) {
		return $this->where( $column, $param1, $param2, 'or' );
	}

	/**
	 * Create an and where statement
	 *
	 * This is the same as the normal where just with a fixed type
	 *
	 * @param string $column The SQL column
	 * @param mixed $param1
	 * @param mixed $param2
	 *
	 * @return Query The current query builder.
	 */
	public function andWhere( $column, $param1 = null, $param2 = null ) {
		return $this->where( $column, $param1, $param2, 'and' );
	}

	/**
	 * Creates a where in statement
	 *
	 *     ->whereIn('id', [42, 38, 12])
	 *
	 * @param string $column
	 * @param array $options
	 *
	 * @return Query The current query builder.
	 */
	public function whereIn( $column, array $options = array() ) {
		// when the options are empty we skip
		if ( empty( $options ) ) {
			return $this;
		}

		return $this->where( $column, 'in', $options );
	}

	/**
	 * Creates a where not in statement
	 *
	 *     ->whereNotIn('id', [42, 38, 12])
	 *
	 * @param string $column
	 * @param array $options
	 *
	 * @return Query The current query builder.
	 */
	public function whereNotIn( $column, array $options = array() ) {
		// when the options are empty we skip
		if ( empty( $options ) ) {
			return $this;
		}

		return $this->where( $column, 'not in', $options );
	}

	/**
	 * Creates a where something is null statement
	 *
	 *     ->whereNull('modified_at')
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function whereNull( $column ) {
		return $this->where( $column, 'is', null );
	}

	/**
	 * Creates a where something is not null statement
	 *
	 *     ->whereNotNull('created_at')
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function whereNotNull( $column ) {
		return $this->where( $column, 'is not', null );
	}

	/**
	 * Creates a or where something is null statement
	 *
	 *     ->orWhereNull('modified_at')
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function orWhereNull( $column ) {
		return $this->orWhere( $column, 'is', null );
	}

	/**
	 * Creates a or where something is not null statement
	 *
	 *     ->orWhereNotNull('modified_at')
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function orWhereNotNull( $column ) {
		return $this->orWhere( $column, 'is not', null );
	}


	/**
	 * Creates a where between statement
	 *
	 *     ->whereBetween('user_id', 1, 2000)
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function whereBetween( $column, $min, $max ) {
		return $this->where( $column, 'BETWEEN', array( $min, $max ) );
	}

	/**
	 * Creates a where not between statement
	 *
	 *     ->whereNotBetween('user_id', 1, 2000)
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function whereNotBetween( $column, $min, $max ) {
		return $this->where( $column, 'NOT BETWEEN', array( $min, $max ) );
	}

	/**
	 * Creates a where date between statement
	 *
	 *     ->whereDateBetween('date', '2014-02-01', '2014-02-28')
	 *
	 * @param string $column
	 *
	 * @return Query The current query builder.
	 */
	public function whereDateBetween( $column, $start, $end ) {
		global $wpdb;
		$stat_date = $wpdb->get_var( $wpdb->prepare( 'SELECT CAST(%s as DATE)', $start ) );
		$end_date  = $wpdb->get_var( $wpdb->prepare( 'SELECT CAST(%s as DATE)', $end ) );

		return $this->where( $column, 'BETWEEN', array( $stat_date, $end_date ) );
	}


	/**
	 *
	 * @param $query
	 * @param string $joint
	 *
	 * @since 1.0.1
	 */
	public function whereRaw( $query, $joint = 'AND' ) {
		$this->where[] = [
			'joint'     => $joint,
			'condition' => $query,
		];

		return $this;
	}


	/**
	 * Add a join statement to the current query
	 *
	 *     ->join('avatars', 'users.id', '=', 'avatars.user_id')
	 *
	 * @param array|string $table The table to join. (can contain an alias definition.)
	 * @param string $localKey
	 * @param string $operator The operator (=, !=, <, > etc.)
	 * @param string $referenceKey
	 * @param string $type The join type (inner, left, right, outer)
	 * @param string $joint The join AND or Or
	 * @param bool $add_prefix Add table prefix or not
	 *
	 * @return Query The current query builder.
	 */
	public function join( $table, $localKey, $operator = null, $referenceKey = null, $type = 'left', $joint = 'AND', $add_prefix = true ) {
		global $wpdb;
		$type = is_string( $type ) ? strtoupper( trim( $type ) ) : ( $type ? 'LEFT' : '' );
		if ( ! in_array( $type, [ '', 'LEFT', 'RIGHT', 'INNER', 'CROSS', 'LEFT OUTER', 'RIGHT OUTER' ] ) ) {
			$this->exception( "Invalid join type." );
		}

		$join = [
			'table' => ( $add_prefix ? $wpdb->prefix : '' ) . $table,
			'type'  => $type,
			'on'    => [],
		];

		// to make nested joins possible you can pass an closure
		// which will create a new query where you can add your nested where
		if ( is_object( $localKey ) && ( $localKey instanceof \Closure ) ) {
			//create new query object
			$subquery = new Query();
			// run the closure callback on the sub query
			call_user_func_array( $localKey, array( &$subquery ) );

			$join['on'] = array_merge( $join['on'], $subquery->where );
			$this->join = array_merge( $this->join, array( $join ) );

			return $this;
		}

		// when param2 is null we replace param2 with param one as the
		// value holder and make param1 to the = operator.
		if ( is_null( $referenceKey ) ) {
			$referenceKey = $operator;
			$operator = '=';
		}

		$referenceKey = is_array( $referenceKey ) ? ( '(\'' . implode( '\',\'', $referenceKey ) . '\')' )
			: ( $referenceKey === null
				? 'null'
				: ( strpos( $referenceKey, '.' ) !== false || strpos( $referenceKey, $wpdb->prefix ) !== false ? $referenceKey : $wpdb->prepare( is_numeric( $referenceKey ) ? '%d' : '%s', $referenceKey ) )
			);

		$join['on'][] = [
			'joint'     => $joint,
			'condition' => implode( ' ', [ $localKey, $operator, $referenceKey ] ),
		];

		$this->join[] = $join;

		return $this;
	}

	/**
	 * Left join same as join with special type
	 *
	 * @param array|string $table The table to join. (can contain an alias definition.)
	 * @param string $localKey
	 * @param string $operator The operator (=, !=, <, > etc.)
	 * @param string $referenceKey
	 *
	 * @return Query The current query builder.
	 */
	public function leftJoin( $table, $localKey, $operator = null, $referenceKey = null ) {
		return $this->join( $table, $localKey, $operator, $referenceKey, 'left' );
	}

	/**
	 * Alias of the `join` method with join type right.
	 *
	 * @param array|string $table The table to join. (can contain an alias definition.)
	 * @param string $localKey
	 * @param string $operator The operator (=, !=, <, > etc.)
	 * @param string $referenceKey
	 *
	 * @return Query The current query builder.
	 */
	public function rightJoin( $table, $localKey, $operator = null, $referenceKey = null ) {
		return $this->join( $table, $localKey, $operator, $referenceKey, 'right' );
	}

	/**
	 * Alias of the `join` method with join type inner.
	 *
	 * @param array|string $table The table to join. (can contain an alias definition.)
	 * @param string $localKey
	 * @param string $operator The operator (=, !=, <, > etc.)
	 * @param string $referenceKey
	 *
	 * @return Query The current query builder.
	 */
	public function innerJoin( $table, $localKey, $operator = null, $referenceKey = null ) {
		return $this->join( $table, $localKey, $operator, $referenceKey, 'inner' );
	}

	/**
	 * Alias of the `join` method with join type outer.
	 *
	 * @param array|string $table The table to join. (can contain an alias definition.)
	 * @param string $localKey
	 * @param string $operator The operator (=, !=, <, > etc.)
	 * @param string $referenceKey
	 *
	 * @return Query The current query builder.
	 */
	public function outerJoin( $table, $localKey, $operator = null, $referenceKey = null ) {
		return $this->join( $table, $localKey, $operator, $referenceKey, 'outer' );
	}

	/**
	 *
	 * @param $query
	 * @param string $joint
	 *
	 * @since 1.0.1
	 */
	public function joinRaw( $query, $joint = 'AND' ) {
		$this->join['on'][] = [
			'joint'     => $joint,
			'condition' => $query,
		];

		return $this;
	}


	/**
	 * Adds group by statement.
	 *     ->groupBy('category')
	 *     ->gorupBy(['category', 'price'])
	 *
	 * @param string $field
	 *
	 * @return Query this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function group_by( $field ) {
		if ( empty( $field ) ) {
			return $this;
		}

		if ( is_array( $field ) ) {
			foreach ( $field as $groupby ) {
				$this->group[] = $groupby;
			}
		} else {
			$this->group[] = $field;
		}

		return $this;
	}

	/**
	 * Adds having statement.
	 *
	 *  ->group_by('user.id')
	 *  ->having('count(user.id)>1')
	 *
	 * @param string $statement
	 *
	 * @return Query this for chaining.
	 * @since 1.0.0
	 *
	 */
	public function having( $statement ) {
		if ( ! empty( $statement ) ) {
			$this->having = $statement;
		}

		return $this;
	}

	/**
	 * Adds order by statement.
	 *
	 *     ->orderBy('created_at')
	 *     ->orderBy('modified_at', 'desc')
	 *
	 * @param string $key
	 * @param string $direction
	 *
	 * @return Query this for chaining.
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public function order_by( $key, $direction = 'ASC' ) {
		$direction = trim( strtoupper( $direction ) );
		if ( $direction !== 'ASC' && $direction !== 'DESC' ) {
			$this->exception( 'Invalid direction value.' );
		}
		if ( ! empty( $key ) ) {
			$this->order[] = $key . ' ' . $direction;
		}

		return $this;
	}

	/**
	 * Set the query limit
	 *
	 *     // limit(<limit>)
	 *     ->limit(20)
	 *
	 *     // limit(<offset>, <limit>)
	 *     ->limit(60, 20)
	 *
	 * @param int $limit
	 * @param int $limit2
	 *
	 * @return Query The current query builder.
	 */
	public function limit( $limit, $limit2 = null ) {
		if ( ! is_null( $limit2 ) ) {
			$this->offset = (int) $limit;
			$this->limit  = (int) $limit2;
		} else {
			$this->limit = (int) $limit;
		}

		return $this;
	}

	/**
	 * Adds offset statement.
	 *
	 * ->offset(20)
	 *
	 * @param int $offset
	 *
	 * @return Query this for chaining.
	 *
	 */
	public function offset( $offset ) {
		$this->offset = $offset;

		return $this;
	}

	/**
	 * Create a query limit based on a page and a page size
	 *
	 * //page(<page>, <size>)
	 *  ->page(2, 20)
	 *
	 * @param int $page
	 * @param int $size
	 *
	 * @return Query The current query builder.
	 * @since 1.0.0
	 */
	public function page( $page, $size = 20 ) {
		if ( ( $page = (int) $page ) <= 1 ) {
			$page = 0;
		}

		$this->limit  = (int) $size;
		$this->offset = (int) $size * $page;

		return $this;
	}

	/**
	 * Find something, means select one item by key
	 *
	 * ->find('manikdrmc@gmail.com', 'email')
	 *
	 * @param int $id
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function find( $id, $key = 'id' ) {
		return $this->where( $key, $id )->one();
	}

	/**
	 * Get the first result ordered by the given key.
	 *
	 * @param string $key By what should the first item be selected? Default is: 'id'
	 *
	 * @return mixed The first result.
	 */
	public function first( $key = 'id' ) {
		return $this->order_by( $key, 'asc' )->one();
	}

	/**
	 * Get the last result by key
	 *
	 * @param string $key
	 *
	 * @return mixed the last result.
	 */
	public function last( $key = 'id' ) {
		return $this->order_by( $key, 'desc' )->one();
	}

	/**
	 * Pluck item.
	 * ->find('post_title')
	 * @return Object
	 * @since 1.0.1
	 */
	public function pluck() {
		$selects      = func_get_args();
		$this->select = $selects;

		return $this->get();
	}


	/**
	 * Returns results from builder statements.
	 *
	 * @param int $output WPDB output type.
	 * @param callable $row_map Function callable to filter or map results to.
	 * @param bool $calc_rows Flag that indicates to SQL if rows should be calculated or not.
	 *
	 * @return Object || Array
	 * @since 1.0.0
	 *
	 * @global object $wpdb
	 *
	 */
	public function get( $output = OBJECT, $row_map = null, $calc_rows = false ) {
		global $wpdb;
		do_action( 'wp_query_builder_get_builder', $this );
		do_action( 'wp_query_builder_get_builder_' . $this->id, $this );

		$query = '';
		$this->_query_select( $query, $calc_rows );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );

		// Process
		$query = apply_filters( 'wp_query_builder_get_query', $query );
		$query = apply_filters( 'wp_query_builder_get_query_' . $this->id, $query );

		$results = $wpdb->get_results( $query, $output );
		if ( $row_map ) {
			$results = array_map( function ( $row ) use ( &$row_map ) {
				return call_user_func_array( $row_map, [ $row ] );
			}, $results );
		}

		return $results;
	}

	/**
	 * Sets the limit to 1, executes and returns the first result using get.
	 *
	 * @param string $output
	 *
	 * @return mixed The single result.
	 */
	public function one( $output = OBJECT ) {
		global $wpdb;
		do_action( 'wp_query_builder_one_builder', $this );
		do_action( 'wp_query_builder_one_builder_' . $this->id, $this );

		$this->_query_select( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$query .= ' LIMIT 1';
		$this->_query_offset( $query );

		$query = apply_filters( 'wp_query_builder_one_query', $query );
		$query = apply_filters( 'wp_query_builder_one_query_' . $this->id, $query );

		return $wpdb->get_row( $query, $output );
	}

	/**
	 * Just return the number of results
	 *
	 * @param string|int $column
	 *
	 * @return int
	 */
	public function count( $column = 1 ) {
		global $wpdb;
		do_action( 'wp_query_builder_count_builder', $this );
		do_action( 'wp_query_builder_count_builder_' . $this->id, $this );

		$query = 'SELECT count(' . $column . ') as `count`';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Just get a single value from the result
	 *
	 * @param string $column The index of the column.
	 * @param bool $calc_rows Flag that indicates to SQL if rows should be calculated or not.
	 *
	 * @return mixed The columns value
	 */
	public function column( $column = 0, $calc_rows = false ) {
		global $wpdb;
		do_action( 'wp_query_builder_column_builder', $this );
		do_action( 'wp_query_builder_column_builder_' . $this->id, $this );

		$query = '';
		$this->_query_select( $query, $calc_rows );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );

		return $wpdb->get_col( $query, $column );
	}

	/**
	 * Returns a value.
	 *
	 * @param int $x Column of value to return. Indexed from 0.
	 * @param int $y Row of value to return. Indexed from 0.
	 *
	 * @return mixed
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	public function value( $x = 0, $y = 0 ) {
		global $wpdb;
		do_action( 'wp_query_builder_value_builder', $this );
		do_action( 'wp_query_builder_value_builder_' . $this->id, $this );

		// Build
		// Query
		$query = '';
		$this->_query_select( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );

		return $wpdb->get_var( $query, $x, $y );
	}


	/**
	 * Update or insert.
	 *
	 * @param $data
	 *
	 * @return array|string
	 */
	public function updateOrInsert( $data ) {
		if ( $this->first() ) {
			return $this->update( $data );
		} else {
			return $this->insert( $data );
		}
	}

	/**
	 * Find or insert.
	 *
	 * @param $data
	 *
	 * @return array|string
	 */
	public function findOrInsert( $data ) {
		if ( $this->first() ) {
			return $this->update( $data );
		} else {
			return $this->insert( $data );
		}
	}

	/**
	 * Get max value.
	 *
	 * @param $column
	 *
	 * @return int
	 * @since 1.0.1
	 */
	public function max( $column ) {
		global $wpdb;
		$query = 'SELECT MAX(' . $column . ')';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Get min value.
	 *
	 * @param $column
	 *
	 * @return int
	 * @since 1.0.1
	 */
	public function min( $column ) {
		global $wpdb;
		$query = 'SELECT MIN(' . $column . ')';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Get avg value.
	 *
	 * @param $column
	 *
	 * @return int
	 * @since 1.0.1
	 */
	public function avg( $column ) {
		global $wpdb;
		$query = 'SELECT AVG(' . $column . ')';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Get sum value.
	 *
	 * @param $column
	 *
	 * @return int
	 * @since 1.0.1
	 */
	public function sum( $column ) {
		global $wpdb;
		$query = 'SELECT SUM(' . $column . ')';
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Returns flag indicating if query has been executed.
	 *
	 * @param string $sql
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 * @global object $wpdb
	 *
	 */
	public function query( $sql = '' ) {
		global $wpdb;
		$query = $sql;
		if ( empty( $query ) ) {
			$this->_query_select( $query, false );
			$this->_query_from( $query );
			$this->_query_join( $query );
			$this->_query_where( $query );
			$this->_query_group( $query );
			$this->_query_having( $query );
			$this->_query_order( $query );
			$this->_query_limit( $query );
			$this->_query_offset( $query );
		}

		return $wpdb->query( $query );
	}

	/**
	 * Returns query from builder statements.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function toSql() {
		$query = '';
		$this->_query_select( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );
		$this->_query_group( $query );
		$this->_query_having( $query );
		$this->_query_order( $query );
		$this->_query_limit( $query );
		$this->_query_offset( $query );

		return $query;
	}

	/**
	 * Returns found rows in last query, if SQL_CALC_FOUND_ROWS is used and is supported.
	 * @return array
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	public function rows_found() {
		global $wpdb;
		$query = 'SELECT FOUND_ROWS()';
		// Process
		$query = apply_filters( 'wp_query_builder_found_rows_query', $query );
		$query = apply_filters( 'wp_query_builder_found_rows_query_' . $this->id, $query );

		return $wpdb->get_var( $query );
	}

	/**
	 * Returns flag indicating if delete query has been executed.
	 * @return bool
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	public function delete() {
		global $wpdb;
		do_action( 'wp_query_builder_delete_builder', $this );
		do_action( 'wp_query_builder_delete_builder_' . $this->id, $this );

		$query = '';
		$this->_query_delete( $query );
		$this->_query_from( $query );
		$this->_query_join( $query );
		$this->_query_where( $query );

		return $wpdb->query( $query );
	}

	/**
	 * Update
	 * @return bool
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	public function update( $data ) {
		global $wpdb;
		$conditions = '';
		$this->_query_where( $conditions );

		if ( empty( trim( $conditions ) ) ) {
			return false;
		}

		$fields = array();
		foreach ( $data as $column => $value ) {

			if ( is_null( $value ) ) {
				$fields[] = "`$column` = NULL";
				continue;
			}

			$fields[] = "`$column` = " . $wpdb->prepare( is_numeric( $value ) ? '%d' : '%s', $value );
		}

		$table  = trim( $this->from );
		$fields = implode( ', ', $fields );

		$query = "UPDATE `$table` SET $fields  $conditions";

		return $wpdb->query( $query );
	}

	/**
	 * Insert data.
	 *
	 * @param $data
	 * @param array $format
	 *
	 * @return bool|int
	 * @since 1.0.1
	 */
	public function insert( $data, $format = array() ) {
		global $wpdb;

		if ( false !== $wpdb->insert( trim( $this->from ), $data, $format ) ) {
			return $wpdb->insert_id;
		};

		return false;
	}

	/**
	 * Return a cloned object from current builder.
	 *
	 * @return Query
	 * @since 1.0.0
	 */
	public function copy() {
		return clone( $this );
	}

	/**
	 * Builds query's select statement.
	 *
	 * @param string &$query
	 * @param bool $calc_rows
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_select( &$query, $calc_rows = false ) {
		$query = 'SELECT ' . ( $calc_rows ? 'SQL_CALC_FOUND_ROWS ' : '' ) . (
			is_array( $this->select ) && count( $this->select )
				? implode( ',', $this->select )
				: '*'
			);
	}

	/**
	 * Builds query's from statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_from( &$query ) {
		$query .= ' FROM ' . $this->from;
	}

	/**
	 * Builds query's join statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_join( &$query ) {
		foreach ( $this->join as $join ) {
			$query .= ( ! empty( $join['type'] ) ? ' ' . $join['type'] . ' JOIN ' : ' JOIN ' ) . $join['table'];
			for ( $i = 0; $i < count( $join['on'] ); ++ $i ) {
				$query .= ( $i === 0 ? ' ON ' : ' ' . $join['on'][ $i ]['joint'] . ' ' )
				          . $join['on'][ $i ]['condition'];
			}
		}
	}

	/**
	 * Builds query's where statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	public function _query_where( &$query ) {
		for ( $i = 0; $i < count( $this->where ); ++ $i ) {
			$query .= ( $i === 0 ? ' WHERE ' : ' ' . $this->where[ $i ]['joint'] . ' ' )
			          . $this->where[ $i ]['condition'];
		}
	}

	/**
	 * Builds query's group by statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_group( &$query ) {
		if ( count( $this->group ) ) {
			$query .= ' GROUP BY ' . implode( ',', $this->group );
		}
	}

	/**
	 * Builds query's having statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_having( &$query ) {
		if ( $this->having ) {
			$query .= ' HAVING ' . $this->having;
		}
	}

	/**
	 * Builds query's order by statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_order( &$query ) {
		if ( count( $this->order ) ) {
			$query .= ' ORDER BY ' . implode( ',', $this->order );
		}
	}

	/**
	 * Builds query's limit statement.
	 *
	 * @param string &$query
	 *
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_limit( &$query ) {
		global $wpdb;
		if ( $this->limit ) {
			$query .= $wpdb->prepare( ' LIMIT %d', $this->limit );
		}
	}

	/**
	 * Builds query's offset statement.
	 *
	 * @param string &$query
	 *
	 * @global object $wpdb
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_offset( &$query ) {
		global $wpdb;
		if ( $this->offset ) {
			$query .= $wpdb->prepare( ' OFFSET %d', $this->offset );
		}
	}

	/**
	 * Builds query's delete statement.
	 *
	 * @param string &$query
	 *
	 * @since 1.0.0
	 *
	 */
	private function _query_delete( &$query ) {
		$query .= trim( 'DELETE ' . ( count( $this->join )
				? preg_replace( '/\s[aA][sS][\s\S]+.*?/', '', $this->from )
				: ''
			) );
	}

	/**
	 * Sanitize value.
	 *
	 * @param string|bool $callback Sanitize callback.
	 * @param mixed $value
	 *
	 * @return mixed
	 * @since 1.0.0
	 *
	 */
	private function sanitize_value( $callback, $value ) {
		if ( $callback === true ) {
			$callback = ( is_numeric( $value ) && strpos( $value, '.' ) !== false )
				? 'floatval'
				: ( is_numeric( $value )
					? 'intval'
					: ( is_string( $value )
						? 'sanitize_text_field'
						: null
					)
				);
		}
		if ( strpos( $callback, '_builder' ) !== false ) {
			$callback = [ &$this, $callback ];
		}
		if ( is_array( $value ) ) {
			for ( $i = count( $value ) - 1; $i >= 0; -- $i ) {
				$value[ $i ] = $this->sanitize_value( true, $value[ $i ] );
			}
		}

		return $callback && is_callable( $callback ) ? call_user_func_array( $callback, [ $value ] ) : $value;
	}

	/**
	 * @param $message
	 *
	 * @throws \Exception
	 * @since 1.0.0
	 */
	private function exception( $message ) {
		throw new \Exception( $message );
	}
}
