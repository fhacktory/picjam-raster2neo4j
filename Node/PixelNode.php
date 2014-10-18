<?php

class PixelNode extends ImageNode
{
	protected static $label;

	private $color = array("red" => null, "green" => null, "blue" => null, "alpha" => null);

	const NODE_LABEL = 'Pixel';

	public function setColor($rgb)
	{
		$this->color = $rgb;
		return $this;
	}

	public function __toString()
	{
		return parent::__toString() . " : " . json_encode($this->color);
	}

	public function createInBase()
	{
		$node = $this->getDbNode();
		if(! empty($node)) {
			return $this;
		}

		$this->neo4jClient->startBatch();
		$node = $this->neo4jClient->makeNode(
			array(
				'x' => $this->x,
				'y' => $this->y
			)
		);

		foreach($this->color as $key => $component) {
			$node->setProperty('color-' . substr($key, 0, 1), $component);
		}

		$node->save();
		$this->neo4jClient->commitBatch();
		$node->addLabels(array(self::$label));

		return $this;
	}

	public function createNodePoints()
	{
		$corners = array(
			$this->x => array(),
			($this->x + 1) => array()
		);


		for($px = $this->x; $px <= $this->x + 1; $px++) {
			for($py = $this->y; $py <= $this->y + 1; $py++) {
				$pointNode = new PointNode($px, $py, $this->neo4jClient);
				$pointNode->setPixelNode($this)
					->createInBase()
					->createCornerRelationships();

				$corners[$px][$py] = $pointNode;
			}
		}

		// Pour chaque bordure (haut, bas, gauche, droite)
		$topLeft = $corners[$this->x][$this->y];
		$topRight = $corners[$this->x + 1][$this->y];
		$bottomLeft = $corners[$this->x][$this->y + 1];
		$bottomRight = $corners[$this->x + 1][$this->y + 1];

		$topLeft->createBorderWith($topRight)
			->createBorderWith($bottomLeft);

		$bottomRight->createBorderWith($topRight)
			->createBorderWith($bottomLeft);
	}
}
