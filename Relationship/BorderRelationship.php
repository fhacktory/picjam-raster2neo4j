<?php
class BorderRelationship
{
	const BORDER_RELATIONSHIP_NAME = 'BORDER';

	private $point1;
	private $point2;

	/**
	 * @var Everyman\Neo4j\Relationship
	 */
	private $dbRelationship;

	protected $neo4jClient;

	public function __construct(PointNode $point1, PointNode $point2, Everyman\Neo4j\Client $neo4jClient)
	{
		$this->point1 = $point1;
		$this->point2 = $point2;
		$this->neo4jClient = $neo4jClient;
	}

	public function isExists()
	{
		if (! empty($this->dbRelationship)) {
			return true;
		}

		$relationshipList = $this->point1->getDbNode()->getRelationships(array(self::BORDER_RELATIONSHIP_NAME));
		foreach($relationshipList as $relationship)
		{
			/**
			 * @var Everyman\Neo4j\Relationship $relationship
			 */
			if($this->isMe($relationship)) {
				$this->dbRelationship = $relationship;
				return true;
			}
		}
		return false;
	}

	private function isMe($relationship)
	{
		$neighbourId = $this->point2->getDbNode()->getId();

		return ($relationship->getEndNode()->getId() == $neighbourId
		|| $relationship->getStartNode()->getId() == $neighbourId
		);
	}

	/**
	 * @param PointNode $neighbour
	 * @param $relationship
	 * @return BorderRelationship|null
	 * @throws Exception
	 */
	public function getDbRelationship(PointNode $neighbour, $relationship)
	{
		$neighbourId = $neighbour->getDbNode()->getId();
		$borderRelationship = null;
		if ($relationship->getEndNode()->getId() == $neighbourId)
		{
			$borderRelationship = new BorderRelationship($this, $neighbour, $this->neo4jClient);
		}
		if ($relationship->getStartNode()->getId() == $neighbourId)
		{
			$borderRelationship = new BorderRelationship($neighbour, $this, $this->neo4jClient);
		}

		return $borderRelationship;
	}

	public function updateWeight()
	{
		if($this->isExists())
		{
			$weight = $this->calculateWeight();
			$this->dbRelationship
				->setProperty("weight", $weight)
				->save();
		} else {
			$this->createInBase();
		}

		return $this;
	}

	public function createInBase()
	{
		$relationship = $this->neo4jClient->makeRelationship();
		$relationship->setStartNode($this->point1->getDbNode())
			->setEndNode($this->point2->getDbNode())
			->setType(self::BORDER_RELATIONSHIP_NAME)
			->setProperty("weight", 2)
			->save();

		$this->dbRelationship = $relationship;

		return $this;
	}

	private function calculateWeight()
	{
		// récupérer pixels 1 et 2
		$queryString = "
			MATCH (c1:Point)-[:CORNER_OF]->(p:Pixel)<-[:CORNER_OF]-(c2:Point)
			WHERE ID(c1) = {id1} AND ID(c2) = {id2}
			RETURN p
		";

		$query = new Everyman\Neo4j\Cypher\Query(
			$this->neo4jClient,
			$queryString,
			array(
				'id1' => $this->point1->getDbNode()->getId(),
				'id2' => $this->point2->getDbNode()->getId()
			)
		);
		$result = $query->getResultSet();

		if($result->count() != 2) {
			throw new Exception("Bad count of Pixel around the border relationship : " . $result->count());
		}

		$colors = array();
		foreach($result as $row) {
			/**
			 * @var \Everyman\Neo4j\Node $node
			 */
			$node = $row['p'];
			$colors[]= $node->getProperty('color-r');
		}

		return abs($colors[0] - $colors[1]) / 255;
	}
}
