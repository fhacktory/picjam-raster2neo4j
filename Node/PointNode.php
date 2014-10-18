<?php
class PointNode extends ImageNode
{
	protected static $label;
	const BORDER_RELATIONSHIP_NAME = 'BORDER';

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
		$borderRelationshipList = $this->dbNode->getRelationships(array(self::BORDER_RELATIONSHIP_NAME));
		foreach($borderRelationshipList as $borderRelationship)
		{
			/**
			 * @var Everyman\Neo4j\Relationship $borderRelationship
			 */
			$neighbourId = $neighbour->getDbNode()->getId();
			if ($borderRelationship->getEndNode()->getId() == $neighbourId
				|| $borderRelationship->getStartNode()->getId() == $neighbourId
			)
			{
				// calculer poids
				return $this;
			}
		}

		$borderRelationship = $this->neo4jClient->makeRelationship();
		$borderRelationship->setStartNode($this->getDbNode())
			->setEndNode($neighbour->getDbNode())
			->setType(self::BORDER_RELATIONSHIP_NAME)
			->save();

		return $this;
		// si n'existe pas
				// créer la relation avec poids de 2
				// si existe
				// calculer et mettre à jour le poids
	}
}
