<?php
class SvgBuilder
{
	public function traceSvg($polygons) {
		$svg = '';

		foreach($polygons as $polygon) {
			$svg .= $this->polygonToSvg($polygon);
		}

		$header = '
	<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
	<svg width="100" height="100"
     xmlns="http://www.w3.org/2000/svg" version="1.1">';
		return $header . $svg . '</svg>';
	}

	private function polygonToSvg($polygon) {
		$path = '<path d="';
		$first = true;
		foreach($polygon as $node) {
			$x = $node->getDbNode()->getProperty('x');
			$y = $node->getDbNode()->getProperty('y');

			if ($first) {
				$path .= ' M ';
				$first = false;
			} else {
				$path .= ' L ';
			}
			$path .= $x . ' ' . $y;

		}

		$path .= 'z" style="stroke: black;fill:none;"/>';

		return $path;
	}
}
