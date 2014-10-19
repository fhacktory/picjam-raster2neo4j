<?php

class SvgNode
{
	/**
	 * @var \Everyman\Neo4j\Node
	 */
	private $dbNode;

	public function __construct(\Everyman\Neo4j\Node $dbNode) {
		$this->dbNode = $dbNode;
	}

	public function isBorder() {
		$width = 100;
		$height = 100;
		$x = $this->dbNode->getProperty('x');
		$y = $this->dbNode->getProperty('y');

		return $x == 0 || $y == 0 || $x == $width || $y == $height;
	}

	public function getDbNode() {
		return $this->dbNode;
	}
}
