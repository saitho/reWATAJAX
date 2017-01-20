<?php

/**
 * @info    Watajax JQUERY plugin server side
 * @author  West Art Communication AB
 * @version 1.1
 * @changelog
 *         20100106 1.1 - Fixed UTF encoding issue
 *         20100106 1.0 - First release
 * @see     http://code.google.com/p/watajax/
 */

class WatajaxSql extends Watajax {
	
	public $query = "";
	protected $database_table = "";
	protected $query_sort = "";
	protected $encoding = "UTF-8";
	protected $where = "";
	protected $groupBy = "";
	protected $sqlTables = "";
	protected $joins = "";
	protected $custom_sql = "";
	
	public function __construct($encoding = "UTF-8") {
		parent::__construct();
		$this->encoding = strtoupper($encoding);
		$this->table = $_GET["watajax_table"];
	}
	
	public function addWhere($where) {
		if ($this->where == "") {
			$this->where = " WHERE ";
		} else {
			$this->where .= " AND ";
		}
		$this->where .= $where . " ";
	}
	
	public function getWhere() {
		return $this->where;
	}
	
	public function addGroupBy($groupBy) {
		if ($this->groupBy == "") {
			$this->groupBy = " GROUP BY ";
		} else {
			$this->groupBy .= ", ";
		}
		$this->groupBy .= $groupBy . " ";
	}
	
	public function addSqlTable($table) {
		if ($this->sqlTables == "") {
			$this->sqlTables = ",";
		}
		$this->sqlTables .= Database::escape($table);
	}
	
	public function addSqlJoin($type, $table, $on) {
		$this->joins .= " ".$type." JOIN ".$table." ON ".$on." ";
		return true;
	}
	
	public function searchFilterData() {
		if ($this->search != "") {
			if ($this->searchColumn == NULL) {
				$where = "";
				foreach ($this->columns as $key => $value) {
					if ($value["virtual"] != true && stripos($key, " AS ") === false && $value["sub_query"] == "" && $value["skip_in_search"] != true) {
						$table = (isset($this->columns[$key]["table"]))? $this->columns[$key]["table"] : $this->table;
						$s = rawurldecode($this->checkEncoding($this->search));
						if ($this->fuzzy_search) {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` LIKE '" . Database::escape($s) . "%' OR ";
						} else if($this->contains_search) {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` LIKE '%" . Database::escape($s) . "%' OR ";
						} else {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` = '" . Database::escape($s) . "' OR ";
						}
					} else {
						if ($value["sub_query"] != "") {
							// $where .= "(".$value["sub_query"].") = '".$s."' OR "; // This was very slow, maybe someone has any better ideas? / CS
						}
					}
				}
				$where = "(" . rtrim($where, " OR ") . ")";
				$this->addWhere($where);
			} else {
				$table = (isset($this->columns[$this->searchColumn]["table"]))? $this->columns[$this->searchColumn]["table"] : $this->table;
				$s = rawurldecode($this->search);
				
				if ($this->fuzzy_search) {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` LIKE '" . Database::escape($s) . "%'";
				} elseif($this->contains_search) {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` LIKE '%" . Database::escape($s) . "%'";
				} else {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` = '" . Database::escape($s) . "'";
				}
				$this->addWhere($where);
			}
		}
		if ($appliedFilters = $this->getAppliedFilters()) {
			$where = "";
			foreach ($appliedFilters as $column => $value) {
				$table = (isset($this->columns[$column]["table"]))? $this->columns[$column]["table"] : $this->table;
				$s = $this->checkEncoding($value);
				$where .= "`" . Database::escape($table) . "`.`" . Database::escape($column) . "` = '" . Database::escape($s) . "' AND ";
			}
			$where = "(" . rtrim($where, " AND ") . ")";
			$this->addWhere($where);
		}
	}
	
	public function sortData() {
		if ($this->sortBy != "") {
			$order = (strtoupper($this->sortOrder) == "ASC")? "ASC" : "DESC";
			$table = (isset($this->columns[$this->sortBy]["table"]))? $this->columns[$this->sortBy]["table"] : $this->table;
			if($this->columns[$this->sortBy]["function"]) {
				$this->query_sort = " ORDER BY " . Database::escape($this->sortBy) . " " . $order;
			} else if (!isset($this->columns[$this->sortBy]["sub_query"])) {
				$this->query_sort = " ORDER BY `".Database::escape($table)."`.`" . Database::escape($this->sortBy) . "` " . $order;
			} else {
				$this->query_sort = " ORDER BY " . Database::escape($this->sortBy) . " " . $order;
			}
		}
	}
	
	public function getGroupedColumnData($column) {
		$column_data = array();
		$table = (isset($this->columns[$column]["table"]))? $this->columns[$column]["table"] : $this->table;
		$sql = "SELECT `".Database::escape($table)."`.`".Database::escape($column)."` FROM `" . Database::escape($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . " GROUP BY `".Database::escape($column)."` ORDER BY `".Database::escape($column)."`";
		$this->filter_query = $sql;
		$r = mysql_query($sql);
		while ($data = mysql_fetch_row($r)) {
			$column_data[] = $this->checkEncoding($data[0]);
		}
		return $column_data;
	}
	
	public function assebleColumns($columns) {
		$columns_sql = "";
		foreach ($columns as $c) {
			$col_name = explode(".", $c);
			$col_name = array_pop($col_name);
			if ($this->columns[$col_name]["distinct"] == true) {
				$columns_sql .= "DISTINCT ";
			}
			if ($this->columns[$col_name]["sub_query"] != "") {
				$columns_sql .= "(" . $this->columns[$col_name]["sub_query"] . ") AS " . $col_name . ",";
			} else if ($this->columns[$col_name]["function"] != "") {
				$columns_sql .= $this->columns[$col_name]["function"] . " AS " . $col_name . ",";
			} else {
				if (stripos($c, ".") === false) {
					$columns_sql .= "`" . Database::escape($this->table) . "`.`" . Database::escape($c) . "`,";
				} else {
					//$columns_sql .= Database::escape($c) . ",";
					$columns_sql .= $c . ",";
				}
			}
		}
		return rtrim($columns_sql, ",");
	}
	
	/**
	 * Use a custom SQL query instead of the watajax generated one.
	 * BE AWARE! The SQL query must return columns that you select in the column configuration
	 * BE AWARE! Do NOT inlcude LIMIT, that will be added by paging automaticly
	 * @param string/function $sql
	 */
	public function useCustomSql($sql) {
		$this->custom_sql = $sql;
	}
	
	public function getData($ignore_pages = false) {
		$data = array();
		$limit_start = (($this->page - 1) * $this->perPage);
		$columns = array();
		foreach ($this->columns as $key => $value) {
			if ($value["virtual"] != true) {
				$prefix = ($value["table"] != "")? "`" . $value["table"] . "`" . "." : "";
				$columns[] = $prefix . $key;
			}
		}
		if ($ignore_pages == false) {
			$limit = " LIMIT " . $limit_start . "," . $this->perPage;
		}
		
		if($this->custom_sql != "") {
			$sql = $this->custom_sql.$limit;
		} else {
			$sql = "SELECT " . $this->assebleColumns($columns) . " FROM `" . Database::escape($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . $this->groupBy . $this->query_sort . $limit;
		}
		$this->query = $sql;
		$r = mysql_query($sql);
		if(mysql_error()) {
			error_log(mysql_error());
			error_log($sql);
			if(defined("DEBUG_MODE") && DEBUG_MODE == true) {
				echo "<strong>A database error occured</strong> - ".$sql." | ".mysql_error().".<br/>\n";
			} else {
				echo "<strong>A database error occured</strong> - Please contact the support.<br/>\n";
			}
		}
		while ($row = @mysql_fetch_assoc($r)) {
			$fixed_row = array();
			$row = $this->encode($row);
			foreach ($this->columns as $key => $value) {
				$fixed_row[$key] = $this->transformColumn($key, $row[$key], $row);
			}
			$data[] = $fixed_row;
		}
		
		if($this->columns[$this->sortBy]["virtual"] === true) { // Post sort if virtual
			$Presort = new ArrayList($data);
			$data = $Presort->yaGetSortedByColumn($this->sortBy, $this->sortOrder, $this->columns[$this->sortBy]["sort_type"]);
		}
		
		return $data;
	}
	
	function getNumberOfPages() {
		$this->searchFilterData();
		$count_column = "*";
		foreach ($this->columns as $key => $value) {
			if ($value["distinct"] == true) {
				if (stripos($key, ".") === false) {
					$columns_sql .= "`" . $this->table . "`.`" . $key . "`";
				} else {
					$columns_sql .= $key;
				}
				$count_column = "DISTINCT " . $columns_sql;
			}
		}
		if($this->custom_sql != "") {
			$result = mysql_query($this->custom_sql);
			$num = mysql_num_rows($result);
		} else {
			$sql = "SELECT COUNT(" . $count_column . ") FROM `" . mysql_real_escape_string($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . $this->groupBy;
			$this->query = $sql;
			if($this->groupBy != "") {
				$num = @mysql_num_rows(mysql_query($sql));
			} else {
				$num = @mysql_result(mysql_query($sql), 0);
			}
		}
		$page_num = (is_numeric($num))? ceil($num / $this->perPage) : 0;
		return array("pages" => $page_num, "items" => $num);
	}
	
}