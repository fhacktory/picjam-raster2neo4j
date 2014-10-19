<?php

class NodeColor
{
	private $colors = array();
	private $count;

	public function addColor($color) {
		$this->colors[] = $color;
		$this->count = null;
		return $this;
	}

	public function count() {
		if (empty($this->count)) {
			$this->count = count($this->colors);
		}

		return $this->count;
	}
}
