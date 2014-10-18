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

		$pixelRelationship = $this->neo4jClient->makeRelationship();
		$pixelRelationship->setStartNode($node)
			->setEndNode($this->pixelNode->getDbNode())
			->setType('CORNER_OF')
			->save();

		$this->neo4jClient->commitBatch();
		$node->addLabels(array(self::$label));

		return $this;
	}
}
