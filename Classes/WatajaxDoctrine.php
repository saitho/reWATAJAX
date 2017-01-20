<?php
require_once(__DIR__.'/WatajaxSql.php');

class WatajaxDoctrine extends Watajax {
	
	protected $latestResultCount = 0;
	/** @var \Doctrine\ORM\EntityManager $em */
	/** @var \Doctrine\ORM\QueryBuilder $qb */
	protected $em, $qb;
	protected $tables = [];
	
	protected $table = '';
	protected $encoding = 'UTF-8';
	protected $where = '';
	
	public function getGroupedColumnData($column) {
		
	}
	public function getNumberOfPages() {
		$items = $this->latestResultCount;
		return ['pages' => ceil($items/$this->perPage), 'items' => $items];
	}
	
	
	public function __construct(\Doctrine\ORM\EntityManager $entityManager) {
		parent::__construct();
		$this->table = $_GET['watajax_table'];
		
		$this->em = $entityManager;
		$this->qb = new \Doctrine\ORM\QueryBuilder($this->em);
	}
	
	public function transformColumn($col, $data, $row) {
		if(is_string($data) && $this->encoding == 'UTF-8') {
			$data = utf8_encode($data);
		}
		if (empty($this->columns[$col]['transform'])) {
			return $data;
		}
		
		$replaceValues = $this->getReplaceValues($col, $row);
		$replace = array_keys($replaceValues);
		$replaceRow = array_values($replaceValues);
		
		foreach(array_keys($this->columns) as $k) {
			$getterName = 'get'.ucfirst($this->camelCase($k));
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			
			if(is_array($row) && array_key_exists($k, $row) && (!is_object($row[0]) || !method_exists($row[0], $getterName))) {
				$value = $row[$k];
			}elseif(is_object($object)) {
				if(!method_exists($object, $getterName)) {
					continue;
				}
				$value = $object->$getterName();
			}else{
				continue;
			}
			if(is_object($value)) {
				continue;
			}
			$replaceRow[] = $value;
			$replace[] = "!".$k;
		}
		$data = str_replace($replace, $replaceRow, $this->columns[$col]['transform']);
		return $data;
	}
	
	private function getReplaceValues($col, $row) {
		$values = [];
		if(!empty($this->columns[$col]['dqlModelValue'])) {
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			
			foreach($this->columns[$col]['dqlModelValue'] AS $var => $classVar) {
				$value = $object;
				$split = explode('->', $classVar);
				foreach($split AS $item) {
					$optionSplit = explode(':', $item);
					$name = $optionSplit[0];
					$options = !empty($optionSplit[1]) ? $optionSplit[1] : '';
					
					$getterName = 'get'.ucfirst($this->camelCase($name));
					if(!is_object($value)) {
						throw new \Exception('Error at dqlModelValue transformation: not object as expected.');
					}else if(!method_exists($value, $getterName)) {
						throw new \Exception('Error at dqlModelValue transformation: Method '.$getterName.' does not exist.');
					}
					$value = $value->$getterName();
					if(!is_object($value) && !empty($options)) {
						switch($options) {
							case 'lower':
								$value = strtolower($value);
								break;
							case 'upper':
								$value = strtoupper($value);
								break;
							case 'camel':
								$value = $this->camelCase($value);
								break;
						}
					}
				}
				$values['!'.$var] = $value;
			}
		}
		return $values;
	}
	private function camelCase($string) {
		$camelCase = '';
		$split = preg_split('/[^A-Za-z]/', $string);
		foreach($split AS $item) {
			$camelCase .= ucfirst($item);
		}
		return lcfirst($camelCase);
	}
	
	/**
	 * @inheritdoc
	 */
	public function getData($ignore_pages=false) {
		
		$data = array();
		$limit_start = (($this->page-1)*$this->perPage);
		if(!empty($this->where)) {
			$this->qb->where($this->where);
		}
		
		$dql = $this->qb->getDQL();
		$query = $this->em->createQuery($dql);
		$result = $query->execute();
		$this->latestResultCount = count($result);
		$paginatorResult = $query->setFirstResult($limit_start)->setMaxResults($this->perPage)->execute();
		
		foreach($paginatorResult AS $row) {
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			$fixed_row = array();
			foreach($this->columns as $key => $value) {
				$rowValue = '';
				$getterName = 'get'.ucfirst($this->camelCase($key));
				if(method_exists($object, $getterName)) {
					$rowValue = $object->$getterName();
				}
				$fixed_row[$key] = $this->transformColumn($key, $rowValue, $row);
			}
			$data[] = $fixed_row;
		}
		return $data;
	}
	public function sortData() {
		$virtualSorting = false;
		foreach($this->columns as $key => $value) {
			if (!empty($value['virtual'])) {
				if(!empty($value['dqlSortValue']) && !empty($value['dqlSortFunc']) && $this->sortBy == $key) {
					$sortReference = 'id';
					if(!empty($value['dqlSortReference'])) {
						$sortReference = $value['dqlSortReference'];
					}
					$this->qb->addSelect($value['dqlSortFunc'].'(b) AS '.$key);
					$this->qb->join('a.'.$value['dqlSortValue'], 'b');
					$this->qb->groupBy('a.'.$sortReference);
					$virtualSorting = true;
				}
			}
		}
		
		if(!empty($this->sortBy)) {
			if(!$virtualSorting) {
				$this->sortBy = 'a.'.$this->sortBy;
			}
			$this->qb->orderBy($this->sortBy, $this->sortOrder);
		}
	}
	public function searchFilterData() {
		if (!empty($this->search)) {
			$where = '';
			foreach($this->columns as $key => $value) {
				if (empty($value['virtual'])) {
					$where .= "a.$key LIKE '%".$this->search."%' OR ";
				}
			}
			$where = '('.rtrim($where, ' OR ').')';
			$this->where = $where;
		}
	}
	
	/** Adjustments */
	public function addTable($modelClass) {
		if(!in_array($modelClass, $this->tables)) {
			$this->tables[] = $modelClass;
		}
	}
	public function getCurrentTable() {
		return $this->getTable($this->table);
	}
	public function getTable($index) {
		$arrayIndex = ($index-1);
		if(empty($this->tables[$arrayIndex])) {
			throw new \Exception('No table found at index '.$index.' (index in array is '.$arrayIndex.').');
		}
		return $this->tables[$arrayIndex];
	}
	function getBody() {
		$this->qb->select('a')->from($this->getCurrentTable(), 'a');
		return parent::getBody();
	}
}