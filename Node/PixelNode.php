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
		// 4 coins : haut-gauche, haut-droit, bas-gauche, bas-droit
		for($px = $this->x; $px <= $this->x + 1; $px++) {
			for($py = $this->y; $py <= $this->y; $py++) {
				$pointNode = new PointNode($px, $py, $this->neo4jClient);
				$pointNode->setPixelNode($this)
					->createInBase();
			}
		}
		// Pour chaque bordure (haut, bas, gauche, droite)
			// si n'existe pas
				// créer la relation avec poids de 2
			// si existe
				// calculer et mettre à jour le poids
	}
}
