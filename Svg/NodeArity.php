<?php
class NodeArity
{
	private $neighbours;
	private $count;

	public function __construct($neighbours)
	{
		$this->neighbours = $neighbours;
	}

	public function addNeighbour($neighbour)
	{
		$this->neighbours[] = $neighbour;
	}

	public function count()
	{
		if (! empty($this->count)) {
			return $this->count;
		}
		$this->count = count($this->neighbours);

		return $this->count;
	}

	public function removeNeighbour($n) {
		unset($this->neighbours[$n]);
		$this->count = null;
	}

	public function getNeighbours() {
		return $this->neighbours;
	}

	public function getPriorizedNeighbours(SvgNode $currentNode, SvgNode $lastNode, $index) {
		$lastDirection = $this->getLastDirection($currentNode, $lastNode);

		$neighbours = array();
		foreach($this->getNeighbours() as $neighbourId) {
			// we doesn't want to come back to the previous node
			if ($neighbourId == $lastNode->getDbNode()->getId()) {
				continue;
			}
			$neighbour = $index[$neighbourId];
			$direction = array(
				$neighbour->getDbNode()->getProperty('x') - $currentNode->getDbNode()->getProperty('x'),
				$neighbour->getDbNode()->getProperty('y') - $currentNode->getDbNode()->getProperty('y')
			);

			$scalarProduct = $this->doScalarProduct($lastDirection, $direction);
			if ($lastDirection[0] != 1) {
				$scalarProduct *= -1;
			}

			$neighbours[$scalarProduct] = $neighbour;
		}

		return $neighbours;
	}

	private function getLastDirection(SvgNode $currentNode, SvgNode $lastNode) {
		if ($currentNode->getDbNode()->getId() == $lastNode->getDbNode()->getId()) {
			return array(0, -1);
		}

		return array(
			$currentNode->getDbNode()->getProperty('x') - $lastNode->getDbNode()->getProperty('x'),
			$currentNode->getDbNode()->getProperty('y') - $lastNode->getDbNode()->getProperty('y')
		);
	}

	private function doScalarProduct($vector1, $vector2) {
		return $vector1[0] * $vector2[1] + $vector1[1] * $vector2[0];
	}
}
