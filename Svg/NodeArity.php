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
}
