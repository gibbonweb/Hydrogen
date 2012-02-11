<?php
/*
 * Copyright (c) 2009 - 2012, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\sqlbeans;

class BeanRegistry {
	protected $beans = array();
	
	public function setBean($bean, $primaryKeyValue) {
		$beanClass = get_class($bean);
		if (!isset($this->beans[$beanClass]))
			$this->beans[$beanClass] = array();
		$this->beans[$beanClass][$primaryKeyValue] = $bean;
	}
	
	public function getBean($beanClass, $primaryKeyValue) {
		if ($beanClass[0] == '\\')
			$beanClass = substr($beanClass, 1);
		return isset($this->beans[$beanClass][$primaryKeyValue]) ? $this->beans[$beanClass][$primaryKeyValue] : false;
	}
}

?>