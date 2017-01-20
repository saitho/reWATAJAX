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

abstract class Watajax {

	public $columns = array();
	public $data = array();
	public $perPage = 10;
	public $page = 1;
	public $sortBy = NULL;
	public $sortOrder = "ASC";
	public $search = "";
	public $searchColumn = "";
	public $errorMsg = "Din s&ouml;kning gav inga resultat.";
	public $classes = array();
	public $fuzzy_search = true;
	public $contains_search = false;

	public $row_tag = "tr";
	public $column_tag = "td";
	public $header_column_tag = "th";
	
	public $encoding;

	/**
	 * Controls how numbers are formatted on export
	 * @var string .|,
	 */
	public $decimal_export_standard = ".";

	public function __construct() {
		if(defined('DEFAULT_TABLE_ROWS') && is_numeric(DEFAULT_TABLE_ROWS)){
			$per_page = DEFAULT_TABLE_ROWS;
		}else{
			$per_page = 10;
		}
		$this->perPage = (isset($_GET["watajax_per_page"]) && is_numeric($_GET["watajax_per_page"]))? $_GET["watajax_per_page"] : $per_page;
		$this->page = (isset($_GET["watajax_page"]) && is_numeric($_GET["watajax_page"]))? $_GET["watajax_page"] : 1;
		$this->sortBy = (isset($_GET["watajax_sortBy"]) && $_GET["watajax_sortBy"] != "")? $_GET["watajax_sortBy"] : NULL;
		$this->sortOrder = (isset($_GET["watajax_sortOrder"]) && $_GET["watajax_sortOrder"] != "")? $_GET["watajax_sortOrder"] : "ASC";
		$this->search = (isset($_GET["watajax_search"]) && $_GET["watajax_search"] != "")? $_GET["watajax_search"] : "";
		$this->filter = (isset($_GET["watajax_filter"]) && $_GET["watajax_filter"] != "")? $_GET["watajax_filter"] : "";
		$this->searchColumn = (isset($_GET["watajax_searchColumn"]) && $_GET["watajax_searchColumn"] != "" && $_GET["watajax_searchColumn"] != "undefined")? $_GET["watajax_searchColumn"] : NULL;
	}

	public function run() {
		$this->checkForAction();
	}

	public function checkForAction() {
		switch ($_GET["action"]) {
			case "watajax_load_head":
				$this->sendHead();
				break;
			case "watajax_load_settings":
				echo json_encode($this->getSettings());
				break;
			case "watajax_load_body":
				$this->sendBody();
				break;
			case "watajax_load_csv":
				$this->sendCSV();
				break;
			case "watajax_load_xls":
				$this->sendXLS();
				break;
			default:
				break;
		}
	}

	public function getFilter() {
		$filter = array();
		if (is_array($this->columns)) {
			foreach ($this->columns as $id => $data) {
				if (isset($data["filter"]) && ($data["filter"] == "select" || $data["filter"] == "text")) {
					$filter[$id]["type"] = $data["filter"];
					if ($data["filter"] == "select") {
						if (is_array($data["select_filter_values"])) {
							$filter[$id]["contents"] = $data["select_filter_values"];
							$filter[$id]["contents_override"] = true;
						} else {
							$filter[$id]["contents"] = $this->getGroupedColumnData($id);
                            $filter[$id]["order"] = array_keys($filter[$id]["contents"]);
							if(get_class($this) == "MagicAjaxTable" || get_class($this) == "MagicListAjaxHandler") {
								$filter[$id]["contents_override"] = true;
							}
						}
					}
				}
			}
		}
		if (count($filter) > 0) {
			$filter["watajax_has_filters"] = true;
		}
		return $filter;
	}

	public function getSettings() {
		$page_count = $this->getNumberOfPages();
		return array("pages" => $page_count["pages"],
			"items" => $page_count["items"],
			"filters" => $this->getFilter(),
			"search_columns" => $this->getSearchColumns()
		);
	}

	abstract public function getGroupedColumnData($column);

	abstract public function sortData();

	abstract public function getNumberOfPages();

	abstract public function getData($ignore_pages = false);

	abstract public function searchFilterData();

	/**
	 * Adds a class to the TR if condition in $test is med
	 *
	 * @param string $class
	 * @param string $column
	 * @param string $test
	 */
	public function addClass($class, $column, $test) {
		$this->classes[$column][] = array("test" => $test, "class" => $class);
	}

	public function getRowClasses($row_data) {
		$class = array();
		foreach ($this->classes as $column => $data) {
			foreach ($data as $test) {
				$column_data = $row_data[$column];
				if (eval($test["test"])) {
					if (!in_array($test["class"], $class)) {
						$class[] = $test["class"];
					}
				}
			}
		}
		return $class;
	}

	public function renderClass($row_data) {
		$class = $this->getRowClasses($row_data);
		if (count($class) > 0) {
			return ' class="' . implode(" ", $class) . '"';
		}
	}

	public function sendBody() {
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData();
		if (count($sorted_data) <= 0) {
			echo "no_rows_found";
		} else {
                        $outercol=0;
			foreach ($sorted_data as $row_id => $row_data) {
				$classes = " ".implode(" ",$this->getRowClasses($row_data));
				echo "<" . $this->row_tag . " class='row_".$outercol++."$classes' id='" . $_GET["watajax_table"] . "_row_$row_id'" . ">";
                                $innercol=0;
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true && $this->columns[$column_id]["load_and_hide"] != true) {
						echo "<" . $this->column_tag . " class='col_".$innercol++." ".$this->columns[$column_id]["class"]."' id='" . $column_id . "_data'>" . $row_data[$column_id] . "</" . $this->column_tag . ">";
					}
				}
				echo "</" . $this->row_tag . ">";
			}
		}
	}

	public function sendXLS($all_pages = true) {
		$filename = $_GET["watajax_table"] . "_" . date("Ymd_Hi") . ".xls";
		$Excel = new PHPExcel();
		$Excel->getProperties()->setCreator("Test");
		$Excel->getProperties()->setLastModifiedBy("Test");
		$Excel->getProperties()->setTitle("Office 2007 XLSX Test Document");
		$Excel->getProperties()->setSubject("Office 2007 XLSX Test Document");
		$Excel->getProperties()->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.");
		$Excel->setActiveSheetIndex(0);
		$Excel->getActiveSheet()->setTitle('Simple');
		$columns = range("A", "Z");
		$i=0;
		$column_count = 0;
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$Excel->getActiveSheet()->SetCellValue($columns[$i].'1', html_entity_decode($data["name"]));
				$column_count++;
				$i++;
			}
		}
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			$j = 2;
			foreach ($sorted_data as $row_id => $row_data) {
				$row = array();
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						$column_data_holder = str_replace(array("\n", "\t", "\r"), "", $row_data[$column_id]);
						$row[] = html_entity_decode(strip_tags($column_data_holder));
					}
				}
				$i = 0;
				foreach($row as $item) {
					$Excel->getActiveSheet()->SetCellValue($columns[$i].$j, $item);
					$i++;
				}
				$j++;
			}
		}
		for($i=0;$i<$column_count;$i++) {
			$Excel->getActiveSheet()->getColumnDimension($columns[$i])->setAutoSize(true);
		}
		$Excel->getActiveSheet()->getStyle('A1:'.$columns[$column_count].'1')->getFont()->setBold(true);
		$Excel->getActiveSheet()->setAutoFilter($Excel->getActiveSheet()->calculateWorksheetDimension());
		$ExcelWriter = PHPExcel_IOFactory::createWriter($Excel, 'Excel5');
		// We'll be outputting an excel file
		header('Content-type: application/vnd.ms-excel');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		// Write file to the browser
		$ExcelWriter->save('php://output');
		die();

		/**
		 * OLD
		 */
		$Excel = new ExcelWriter('UTF-8', false, $filename);
		$export_data = array();
		$header_row = array();
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$header_row[] = html_entity_decode($data["name"]);
			}
		}
		$export_data[] = $header_row;

		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			foreach ($sorted_data as $row_id => $row_data) {
				$row = array();
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						$column_data_holder = str_replace(array("\n", "\t", "\r"), "", $row_data[$column_id]);
						$row[] = html_entity_decode(strip_tags($column_data_holder));
					}
				}
				$export_data[] = $row;
			}
		}
		$Excel->addArray($export_data);
		$Excel->generateXML($filename);
	}

	public function sendCSV($all_pages = true) {
		$delimiter = ";";
		$header = "";
		$row = "";
		$column_wrapper = '';

		// Header
		header("Content-type: application/vnd.ms-excel; charset=ISO-8859-1; encoding=ISO-8859-1");
		header("Content-Disposition: attachment; filename=" . $_GET["watajax_table"] . "_" . date("Ymd_Hi") . ".csv");
		header("Pragma: no-cache");
		header("Expires: 0");

		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$header .= $column_wrapper . html_entity_decode($data["name"]) . $column_wrapper . $delimiter;
			}
		}
		echo mb_convert_encoding(rtrim($header, $delimiter), "ISO-8859-1") . PHP_EOL;

		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			foreach ($sorted_data as $row_id => $row_data) {
				$row = "";
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						if($this->decimal_export_standard == "," || (defined("WATAJAX_DECIMAL_EXPORT_STANDARD") && WATAJAX_DECIMAL_EXPORT_STANDARD == ",")) {
							$row_data[$column_id] = preg_replace('/^(\d*)\.(\d\d\d)$/', '$1$2', $row_data[$column_id]); // Remove thousand separator
							$row_data[$column_id] = preg_replace('/^(\d*)\.(\d\d?)$/', "$1,$2", $row_data[$column_id]); // Change to comma to be XLS compatible
						}
						$column_data_holder = str_replace(array($delimiter, $column_wrapper, "\n", "\t", "\r"), "", html_entity_decode($row_data[$column_id]));
						$row .= $column_wrapper . strip_tags($column_data_holder . $column_wrapper . $delimiter);
					}
				}
				echo mb_convert_encoding(rtrim($row, $delimiter), "ISO-8859-1") . PHP_EOL;
			}
		}
	}

	public function getSearchColumns() {
		$search_columns = array();
		foreach ($this->columns as $id => $data) {
			if ($data["virtual"] != true && $data["skip_in_search"] != true && $data['hide'] != true) {
				$search_columns[$id] = $data["name"];
			}
		}
		return $search_columns;
	}

	public function sendHead() {
		echo "<" . $this->row_tag . ">";
                $inner=0;
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$class = (isset($data["virtual"]) && $data["virtual"] == true)? "virtual" : "";
				echo "<" . $this->header_column_tag . " id='$id' class='$class col_".$inner++." ".$data["class"]."'>" . $data["name"] . "</" . $this->header_column_tag . ">";
			}
		}
		echo "</" . $this->row_tag . ">";
	}

	public function getAppliedFilters() {
		$appliedFilters = array();
		if ($this->filter != "") {
			$where = "";
			$filters = explode("|", rawurldecode($this->filter));

			if (is_array($filters) && count($filters) > 0) {
				foreach ($filters as $f) {
					list($column, $value) = explode(":", $f);
					$appliedFilters[$column] = $value;
				}
			}
		}
		return (count($appliedFilters) > 0)? $appliedFilters : false;
	}

	public function transformColumn($col, $data, $row) {
		$replace = array();
		$replacements = array();

		foreach (array_keys($this->columns) as $k) {
			if (isset($row[$k])) {
				$replace[] = "!" . $k;
				$replacements[] = $row[$k];
			}
		}

		if (isset($this->columns[$col]["transform"]) && $this->columns[$col]["transform"] != "") {
			$data = str_replace($replace, $replacements, $this->columns[$col]["transform"]);
			if ($this->columns[$col]["eval_transform"] == true || $this->columns[$col]["eval_transform"] == '1') {
				$data = eval($data);
			}
		}
		return $data;
	}

	public function encode($arr) {
		$encoded = array();
		if (isset($this->encoding) && $this->encoding === "UTF-8") {
			foreach ($arr as $key => $val) {
				if (!mb_detect_encoding($val, 'UTF-8', true)) {
					$encoded[$key] = utf8_encode($val);
				} else {
					$encoded[$key] = $val;
				}
			}
		} else {
			$encoded = $arr;
		}
		return $encoded;
	}

	function check_utf8($str) {
		$len = @strlen($str);
		for ($i = 0; $i < $len; $i++) {
			$c = ord($str[$i]);
			if ($c > 128) {
				if (($c > 247)) {
					return false;
				} elseif ($c > 239) {
					$bytes = 4;
				} elseif ($c > 223) {
					$bytes = 3;
				} elseif ($c > 191) {
					$bytes = 2;
				} else {
					return false;
				}
				if (($i + $bytes) > $len) {
					return false;
				}
				while ($bytes > 1) {
					$i++;
					$b = ord($str[$i]);
					if ($b < 128 || $b > 191) {
						return false;
					}
					$bytes--;
				}
			}
		}
		return true;
	}

	function checkEncoding($str) {
		if ($this->encoding == 'UTF-8' && !$this->check_utf8($str)) {
			$str = utf8_encode($str);
		} else {
			if ($this->encoding != 'UTF-8' && $this->check_utf8($str)) {
				$str = utf8_decode($str);
			}
		}
		return $str;
	}

}