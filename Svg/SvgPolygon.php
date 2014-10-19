<?php

class SvgPolygon
{
	private $color;
	private $points = array();

	public function setColor($id) {
		$this->color = $id;
		return $this;
	}

	public function getColor() {
		return $this->color;
	}

	public function getTrueColor() {
		return $this->color . $this->color . $this->color;
	}

	public function addPoint(SvgNode $node) {
		$this->points[] = $node;
		return $this;
	}

	public function getPoints() {
		return $this->points;
	}

	public function isEmpty() {
		return empty($this->points);
	}

	public function getLastPoint() {
		return $this->points[count($this->points) - 1];
	}

	public function isClosed(SvgNode $node) {
		return $this->points[0] == $node;
	}
}
