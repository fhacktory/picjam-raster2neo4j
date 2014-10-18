<?php
class PointNode extends ImageNode
{
	protected static $label;
	/**
	 * @var PixelNode
	 */
	private $pixelNode;

	const NODE_LABEL = 'Point';

	public function setPixelNode(PixelNode $node)
	{
		$this->pixelNode = $node;
		return $this;
	}

	public function createInBase()
	{
		$node = $this->getDbNode();
		if(! empty($node)) {
			return $this;
		}

		if (empty($this->pixelNode)) {
			throw new Exception("The Pixel Node can't be null to insert a Point Node in DB");
		}

		$this->neo4jClient->startBatch();
		$node = $this->neo4jClient->makeNode(
			array(
				'x' => $this->x,
				'y' => $this->y
			)
		);

		$node->save();

		$this->neo4jClient->commitBatch();
		$node->addLabels(array(self::$label));

		$this->dbNode = $node;
		return $this;
	}

	/**
	 * @return $this
	 * @throws Exception
	 * @throws \Everyman\Neo4j\Exception
	 */
	public function createCornerRelationships()
	{
		$node = $this->getDbNode();

		$pixelRelationship = $this->neo4jClient->makeRelationship();
		$pixelRelationship->setStartNode($node)
			->setEndNode($this->pixelNode->getDbNode())
			->setType('CORNER_OF')
			->save();

		return $this;
	}

	public function createBorderWith(PointNode $neighbour)
	{
		$borderRelationship = new BorderRelationship($this, $neighbour, $this->neo4jClient);
		if($borderRelationship->isExists()) {
			$borderRelationship->updateWeight();
		} else {
			$borderRelationship->createInBase();
		}
		return $this;
	}
}
