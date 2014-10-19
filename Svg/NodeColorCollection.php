<?php

class NodeColorCollection
{
	private $nodeColor = array();

	public function initNodeColor($nodeId) {
		$this->nodeColor[$nodeId] = new NodeColor();
	}

	public function isExist($nodeId) {
		return isset($this->nodeColor[$nodeId]);
	}

	/**
	 * @param $nodeId
	 * @return null|NodeColor
	 */
	public function getNodeColor($nodeId) {
		if ($this->isExist($nodeId)) {
			return $this->nodeColor[$nodeId];
		}
		return null;
	}
}
